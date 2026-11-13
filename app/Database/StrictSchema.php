<?php

namespace App\Database;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Tabellen nach dem DB-Standard (A4, #266): SQLite als STRICT-Tabelle mit CHECK-Regeln in der ersten Migration,
 * PostgreSQL mit denselben CHECK-Regeln als benannte Constraints. Die Spalten beschreibt wie gewohnt Schema::create;
 * für SQLite wird das CREATE TABLE auf die STRICT-Typen (INTEGER, REAL, TEXT, BLOB) abgebildet.
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
     * Eben angelegte (leere) Tabelle härten: SQLite als STRICT neu aufbauen (mit ihren Indizes), CHECK-Regeln setzen;
     * PostgreSQL erhält die CHECK-Regeln als Constraints. Die Tabelle selbst legt die Migration mit Schema::create an
     * (so lesen auch Larastan und Werkzeuge das Schema).
     *
     * @param  array<string, string>  $checks  Name der Regel → SQL-Ausdruck
     */
    public static function harden(string $table, array $checks = []): void
    {
        /** @var Connection $db */
        $db = Schema::getConnection();
        if ($db->getDriverName() !== 'sqlite') {
            foreach ($checks as $name => $expression) {
                $db->statement("alter table \"{$table}\" add constraint \"{$name}\" check ({$expression})");
            }

            return;
        }
        if ($db->table($table)->exists()) {
            throw new RuntimeException("Tabelle {$table} ist nicht leer – STRICT nur direkt nach dem Anlegen.");
        }
        $create = (string) $db->scalar("select sql from sqlite_master where type = 'table' and name = ?", [$table]);
        /** @var list<object{sql: string}> $indexes */
        $indexes = $db->select("select sql from sqlite_master where type = 'index' and tbl_name = ? and sql is not null", [$table]);
        $db->statement("drop table \"{$table}\"");
        $db->statement(self::strictSql($create, $checks));
        foreach ($indexes as $index) {
            $db->statement($index->sql);
        }
    }

    /**
     * Spalten zu einer bestehenden (STRICT-)Tabelle hinzufügen. SQLite: die von Schema::table erzeugten ALTER-
     * Anweisungen auf STRICT-Typen abbilden, CHECK-Regeln je Spalte anhängen (SQLite kennt bei ADD COLUMN nur
     * Spalten-CHECKs); PostgreSQL: Schema::table und benannte Constraints.
     *
     * @param  Closure(Blueprint): void  $columns
     * @param  array<string, array{0: string, 1: string}>  $checks  Spalte → [Name der Regel, SQL-Ausdruck]
     */
    public static function addColumns(string $table, Closure $columns, array $checks = []): void
    {
        /** @var Connection $db */
        $db = Schema::getConnection();
        if ($db->getDriverName() !== 'sqlite') {
            Schema::table($table, $columns);
            foreach ($checks as [$name, $expression]) {
                $db->statement("alter table \"{$table}\" add constraint \"{$name}\" check ({$expression})");
            }

            return;
        }
        $statements = $db->pretend(fn () => Schema::table($table, $columns));
        $types = implode('|', array_map(fn (string $t): string => preg_quote($t, '/'), array_keys(self::SQLITE_TYPES)));
        foreach ($statements as $statement) {
            $sql = (string) $statement['query'];
            if (preg_match('/^alter table "[^"]+" add column "([^"]+)"/i', $sql, $m) === 1) {
                $sql = trim((string) preg_replace_callback(
                    '/("[^"]+" )('.$types.')(?=[ ,)])/i',
                    fn (array $t): string => $t[1].self::SQLITE_TYPES[strtolower($t[2])],
                    $sql.' ',
                ));
                if (isset($checks[$m[1]])) {
                    $sql .= " constraint \"{$checks[$m[1]][0]}\" check ({$checks[$m[1]][1]})";
                }
            }
            $db->statement($sql, $statement['bindings']);
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
