<?php

namespace App\Database;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Schema;

/**
 * Tabellen nach dem DB-Standard (A4, #266): SQLite als STRICT-Tabelle mit CHECK-Regeln in der ersten Migration,
 * PostgreSQL mit denselben CHECK-Regeln als benannte Constraints. Die Spalten beschreibt wie gewohnt ein Blueprint;
 * für SQLite wird das erzeugte CREATE TABLE auf die STRICT-Typen (INTEGER, REAL, TEXT, BLOB) abgebildet.
 *
 * Datenbankspezifische Umsetzung gebündelt hier (A5): `if ($driver === 'pgsql')` nur in diesem Helfer.
 */
final class StrictSchema
{
    /** Laravel-Typen in SQLite → STRICT-Typ. */
    private const SQLITE_TYPES = [
        'varchar' => 'TEXT', 'text' => 'TEXT', 'datetime' => 'TEXT', 'date' => 'TEXT', 'time' => 'TEXT',
        'tinyint(1)' => 'INTEGER', 'tinyint' => 'INTEGER', 'integer' => 'INTEGER', 'numeric' => 'TEXT',
        'float' => 'REAL', 'double' => 'REAL', 'blob' => 'BLOB',
    ];

    /**
     * @param  array<string, string>  $checks  Name der Regel → SQL-Ausdruck
     */
    public static function create(string $table, Closure $callback, array $checks = []): void
    {
        /** @var Connection $db */
        $db = Schema::getConnection();
        if ($db->getDriverName() !== 'sqlite') {
            Schema::create($table, $callback);
            foreach ($checks as $name => $expression) {
                $db->statement("alter table \"{$table}\" add constraint \"{$table}_{$name}_check\" check ({$expression})");
            }

            return;
        }

        $queries = $db->pretend(fn () => Schema::create($table, $callback));
        foreach ($queries as $i => $query) {
            $sql = (string) $query['query'];
            if ($i === 0 && str_starts_with(strtolower($sql), 'create table')) {
                $sql = self::strictSql($sql, $checks);
            }
            $db->statement($sql, $query['bindings']);
        }
    }

    /** @param  array<string, string>  $checks */
    public static function strictSql(string $sql, array $checks): string
    {
        $types = implode('|', array_map(fn (string $t): string => preg_quote($t, '/'), array_keys(self::SQLITE_TYPES)));
        $sql = (string) preg_replace_callback(
            '/("[^"]+" )('.$types.')(?=[ ,)])/i',
            fn (array $m): string => $m[1].self::SQLITE_TYPES[strtolower($m[2])],
            $sql,
        );
        $extra = '';
        foreach ($checks as $name => $expression) {
            $extra .= ", constraint \"{$name}\" check ({$expression})";
        }
        $end = strrpos($sql, ')');

        return substr($sql, 0, (int) $end).$extra.') strict';
    }
}
