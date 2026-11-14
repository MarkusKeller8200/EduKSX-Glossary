<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Umzug SQLite → PostgreSQL (#191, #333, DB-Standard E6): kopiert alle Tabellen aus der SQLite-Datenbank in das Schema
 * `glossary` einer frisch migrierten PostgreSQL-Datenbank – ohne Datenverlust (Zählung vorher = nachher).
 *
 * Ablauf (docs/KONZEPT-DEPLOYMENT.md «Umzug nach PostgreSQL»):
 *  1. Sicherung der SQLite-Datei, Anwendung anhalten.
 *  2. PostgreSQL-Verbindung als «pgsql» konfigurieren, `php artisan migrate --database=pgsql`.
 *  3. `php artisan glossary:umzug-postgres --quelle=sqlite --ziel=pgsql` (Probelauf mit --trocken).
 *  4. DB_CONNECTION=pgsql setzen, Anwendung starten.
 *
 * Reihenfolge nach Fremdschlüsseln; gegenseitige Verweise (z. B. terms ↔ term_revisions) und Verweise auf die eigene
 * Tabelle werden zuerst leer gelassen und danach gesetzt. Zähler (Sequenzen) folgen dem höchsten Wert.
 */
class MoveToPostgres extends Command
{
    protected $signature = 'glossary:umzug-postgres
        {--quelle=sqlite : Verbindung der SQLite-Datenbank}
        {--ziel=pgsql : Verbindung der PostgreSQL-Datenbank (migriert)}
        {--leeren : Daten im Ziel vorher löschen (z. B. Grunddaten aus den Migrationen)}
        {--trocken : Nur prüfen und zählen, nichts schreiben}';

    protected $description = 'SQLite-Datenbank nach PostgreSQL (Schema glossary) übernehmen (#191)';

    private const CHUNK = 500;

    public function handle(): int
    {
        $source = DB::connection((string) $this->option('quelle'));
        $target = DB::connection((string) $this->option('ziel'));
        if ($source->getDriverName() !== 'sqlite' || $target->getDriverName() !== 'pgsql') {
            $this->error('Quelle muss SQLite, Ziel PostgreSQL sein.');

            return self::INVALID;
        }

        $tables = array_values(array_filter(
            array_column($source->getSchemaBuilder()->getTables(), 'name'),
            fn (string $t): bool => ! str_starts_with($t, 'sqlite_') && $t !== 'migrations',
        ));
        $this->assertSameMigrations($source, $target);
        $missing = array_diff($tables, array_column($target->getSchemaBuilder()->getTables(), 'name'));
        if ($missing !== []) {
            throw new RuntimeException('Im Ziel fehlen Tabellen: '.implode(', ', $missing));
        }

        [$order, $deferred] = $this->order($source, $tables);
        $counts = [];
        foreach ($order as $table) {
            $counts[$table] = $source->table($table)->count();
        }
        if ($this->option('trocken')) {
            $this->table(['Tabelle', 'Zeilen'], array_map(fn ($t) => [$t, $counts[$t]], $order));

            return self::SUCCESS;
        }

        $target->transaction(function () use ($source, $target, $order, $deferred): void {
            if ($this->option('leeren')) {
                $target->statement('truncate '.implode(', ', array_map(fn ($t) => '"'.$t.'"', $order)).' restart identity cascade');
            }
            foreach ($order as $table) {
                if ($target->table($table)->exists()) {
                    throw new RuntimeException("Ziel ist nicht leer: {$table} (Option --leeren).");
                }
                $this->copy($source, $target, $table, $deferred[$table] ?? []);
            }
            // aufgeschobene Verweise setzen
            foreach ($deferred as $table => $columns) {
                $key = $this->keyColumns($source, $table);
                foreach ($this->rows($source, $table, $key, [...$key, ...$columns]) as $row) {
                    $values = array_intersect_key((array) $row, array_flip($columns));
                    if (array_filter($values, fn ($v) => $v !== null) === []) {
                        continue;
                    }
                    $target->table($table)->where(array_intersect_key((array) $row, array_flip($key)))->update($values);
                }
            }
            $this->resetSequences($target, $order);
        });

        $rows = [];
        $ok = true;
        foreach ($order as $table) {
            $after = $target->table($table)->count();
            $ok = $ok && $after === $counts[$table];
            $rows[] = [$table, $counts[$table], $after];
        }
        $this->table(['Tabelle', 'SQLite', 'PostgreSQL'], $rows);
        if (! $ok) {
            $this->error('Zählung weicht ab.');

            return self::FAILURE;
        }
        $this->info('Übernommen: '.count($order).' Tabellen, Zählung gleich.');

        return self::SUCCESS;
    }

    private function assertSameMigrations(Connection $source, Connection $target): void
    {
        $a = $source->table('migrations')->orderBy('migration')->pluck('migration')->all();
        $b = $target->table('migrations')->orderBy('migration')->pluck('migration')->all();
        if ($a !== $b) {
            throw new RuntimeException('Quelle und Ziel haben nicht dieselben Migrationen – beide auf denselben Stand bringen.');
        }
    }

    /**
     * Reihenfolge nach Fremdschlüsseln; Verweise, die einen Kreis schliessen, werden aufgeschoben (müssen leer sein dürfen).
     *
     * @param  list<string>  $tables
     * @return array{0: list<string>, 1: array<string, list<string>>}
     */
    private function order(Connection $source, array $tables): array
    {
        $refs = [];
        $nullable = [];
        foreach ($tables as $table) {
            foreach ($source->getSchemaBuilder()->getColumns($table) as $column) {
                $nullable[$table][$column['name']] = (bool) $column['nullable'];
            }
            foreach ($source->getSchemaBuilder()->getForeignKeys($table) as $fk) {
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

    /** @param  list<string>  $nullColumns */
    private function copy(Connection $source, Connection $target, string $table, array $nullColumns): void
    {
        $types = [];
        foreach ($target->getSchemaBuilder()->getColumns($table) as $column) {
            $types[$column['name']] = (string) $column['type_name'];
        }
        $key = $this->keyColumns($source, $table);
        $batch = [];
        foreach ($this->rows($source, $table, $key) as $row) {
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
                $target->table($table)->insert($batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            $target->table($table)->insert($batch);
        }
    }

    /**
     * Zeilen seitenweise, sortiert nach dem ganzen Schlüssel (auch zusammengesetzt, z. B. term_categories).
     *
     * @param  list<string>  $key
     * @param  list<string>|null  $columns
     * @return iterable<object>
     */
    private function rows(Connection $source, string $table, array $key, ?array $columns = null): iterable
    {
        $query = $source->table($table)->select($columns ?? ['*']);
        foreach ($key as $column) {
            $query->orderBy($column);
        }

        return $query->lazy(self::CHUNK);
    }

    /** @return list<string> Spalten, die eine Zeile eindeutig bestimmen (Primärschlüssel) */
    private function keyColumns(Connection $source, string $table): array
    {
        foreach ($source->getSchemaBuilder()->getIndexes($table) as $index) {
            if ($index['primary']) {
                return $index['columns'];
            }
        }
        foreach ($source->getSchemaBuilder()->getIndexes($table) as $index) {
            if ($index['unique']) {
                return $index['columns'];
            }
        }

        return array_column($source->getSchemaBuilder()->getColumns($table), 'name');
    }

    /** @param  list<string>  $tables */
    private function resetSequences(Connection $target, array $tables): void
    {
        foreach ($tables as $table) {
            foreach ($target->getSchemaBuilder()->getColumns($table) as $column) {
                if ($column['auto_increment'] && in_array($column['type_name'], ['int2', 'int4', 'int8'], true)) {
                    $target->statement("select setval(pg_get_serial_sequence(?, ?), coalesce((select max(\"{$column['name']}\") from \"{$table}\"), 0) + 1, false)", [$table, $column['name']]);
                }
            }
        }
    }
}
