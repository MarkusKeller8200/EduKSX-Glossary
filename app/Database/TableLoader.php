<?php

namespace App\Database;

use Illuminate\Database\Connection;
use RuntimeException;

/**
 * Tabellen in eine leere Datenbank laden, unabhängig von der Herkunft der Zeilen (Umzug nach PostgreSQL #191,
 * Wiederherstellung aus einem Dump #353): Reihenfolge nach Fremdschlüsseln, Verweise in einem Kreis werden zuerst leer
 * eingefügt und danach gesetzt, Wahrheitswerte für PostgreSQL umgewandelt und Sequenzen nachgeführt.
 */
final class TableLoader
{
    public const CHUNK = 500;

    private Connection $schema;

    /** @param  Connection|null  $schema  Verbindung, deren Schema die Fremdschlüssel liefert (Vorgabe: das Ziel) */
    public function __construct(private readonly Connection $target, ?Connection $schema = null)
    {
        $this->schema = $schema ?? $target;
    }

    /**
     * Zeilen aller Tabellen laden. $rows liefert die Zeilen einer Tabelle, sortiert nach $key; mit $columns genügen
     * diese Spalten (für das Nachtragen der aufgeschobenen Verweise).
     *
     * @param  list<string>  $tables
     * @param  callable(string, list<string>, list<string>|null): iterable<array<string, mixed>|object>  $rows
     * @return list<string> geladene Tabellen in Ladereihenfolge
     */
    public function load(array $tables, callable $rows): array
    {
        [$order, $deferred] = $this->order($tables);
        foreach ($order as $table) {
            $this->copy($table, $rows($table, $this->keyColumns($table), null), $deferred[$table] ?? []);
        }
        // aufgeschobene Verweise setzen
        foreach ($deferred as $table => $columns) {
            $key = $this->keyColumns($table);
            foreach ($rows($table, $key, [...$key, ...$columns]) as $row) {
                $row = (array) $row;
                $values = array_intersect_key($row, array_flip($columns));
                if (array_filter($values, fn ($v) => $v !== null) === []) {
                    continue;
                }
                $this->target->table($table)->where(array_intersect_key($row, array_flip($key)))->update($values);
            }
        }
        if ($this->target->getDriverName() === 'pgsql') {
            $this->resetSequences($order);
        }

        return $order;
    }

    /**
     * Reihenfolge nach Fremdschlüsseln; Verweise, die einen Kreis schliessen, werden aufgeschoben (müssen leer sein dürfen).
     *
     * @param  list<string>  $tables
     * @return array{0: list<string>, 1: array<string, list<string>>}
     */
    public function order(array $tables): array
    {
        $refs = [];
        $nullable = [];
        foreach ($tables as $table) {
            foreach ($this->schema->getSchemaBuilder()->getColumns($table) as $column) {
                $nullable[$table][$column['name']] = (bool) $column['nullable'];
            }
            foreach ($this->schema->getSchemaBuilder()->getForeignKeys($table) as $fk) {
                $refs[$table][] = ['to' => $fk['foreign_table'], 'columns' => $fk['columns']];
            }
        }
        $order = [];
        $deferred = [];
        $deferredRefs = [];
        $pending = $tables;
        while ($pending !== []) {
            $progress = false;
            foreach ($pending as $i => $table) {
                $open = array_filter($refs[$table] ?? [], fn ($r) => $r['to'] !== $table && in_array($r['to'], $pending, true) && ! in_array($r['columns'], $deferredRefs[$table] ?? [], true));
                if ($open === []) {
                    $order[] = $table;
                    unset($pending[$i]);
                    $progress = true;
                }
            }
            if (! $progress) {
                // Kreis: den ersten nullbaren offenen Verweis einer wartenden Tabelle aufschieben
                $found = false;
                foreach ($pending as $table) {
                    foreach ($refs[$table] ?? [] as $r) {
                        $open = in_array($r['to'], $pending, true) && $r['to'] !== $table && ! in_array($r['columns'], $deferredRefs[$table] ?? [], true);
                        // zusammengesetzte Verweise: eine leere Spalte genügt (MATCH SIMPLE prüft dann nicht)
                        $nullableColumns = array_values(array_filter($r['columns'], fn ($c) => $nullable[$table][$c] ?? false));
                        if ($open && $nullableColumns !== []) {
                            $deferred[$table] = array_values(array_unique([...($deferred[$table] ?? []), ...$nullableColumns]));
                            $deferredRefs[$table][] = $r['columns'];
                            $found = true;
                            break 2;
                        }
                    }
                }
                if (! $found) {
                    throw new RuntimeException('Kreis zwischen '.implode(', ', $pending).' lässt sich nicht auflösen.');
                }
            }
            $pending = array_values($pending);
        }
        // Verweise auf die eigene Tabelle ebenfalls aufschieben
        foreach ($refs as $table => $list) {
            foreach ($list as $r) {
                if ($r['to'] === $table) {
                    $own = array_values(array_filter($r['columns'], fn ($c) => $nullable[$table][$c] ?? false));
                    $deferred[$table] = array_values(array_unique([...($deferred[$table] ?? []), ...$own]));
                }
            }
        }

        return [$order, $deferred];
    }

    /** @return list<string> Spalten, die eine Zeile eindeutig bestimmen (Primärschlüssel) */
    public function keyColumns(string $table): array
    {
        foreach ($this->schema->getSchemaBuilder()->getIndexes($table) as $index) {
            if ($index['primary']) {
                return $index['columns'];
            }
        }
        foreach ($this->schema->getSchemaBuilder()->getIndexes($table) as $index) {
            if ($index['unique']) {
                return $index['columns'];
            }
        }

        return array_column($this->schema->getSchemaBuilder()->getColumns($table), 'name');
    }

    /**
     * @param  iterable<array<string, mixed>|object>  $rows
     * @param  list<string>  $nullColumns
     */
    private function copy(string $table, iterable $rows, array $nullColumns): void
    {
        $types = [];
        foreach ($this->target->getSchemaBuilder()->getColumns($table) as $column) {
            $types[$column['name']] = (string) $column['type_name'];
        }
        $batch = [];
        foreach ($rows as $row) {
            $values = [];
            foreach ((array) $row as $column => $value) {
                if (in_array($column, $nullColumns, true)) {
                    $value = null;
                } elseif ($value !== null && ($types[$column] ?? '') === 'bool') {
                    $value = (bool) $value;
                }
                $values[$column] = $value;
            }
            $batch[] = $values;
            if (count($batch) >= self::CHUNK) {
                $this->target->table($table)->insert($batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            $this->target->table($table)->insert($batch);
        }
    }

    /** @param  list<string>  $tables */
    private function resetSequences(array $tables): void
    {
        foreach ($tables as $table) {
            foreach ($this->target->getSchemaBuilder()->getColumns($table) as $column) {
                if ($column['auto_increment'] && in_array($column['type_name'], ['int2', 'int4', 'int8'], true)) {
                    $this->target->statement("select setval(pg_get_serial_sequence(?, ?), coalesce((select max(\"{$column['name']}\") from \"{$table}\"), 0) + 1, false)", [$table, $column['name']]);
                }
            }
        }
    }
}
