<?php

namespace App\Database;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Schema je Modul auf PostgreSQL (DB-Standard E2, #333): Alle Tabellen des Glossary – auch migrations, jobs, cache,
 * sessions – liegen im Schema `glossary` (search_path in config/database.php). Beim Aufbau einer Verbindung wird
 * geprüft, ob es besteht, und es wird nur angelegt, wenn es fehlt; ein Datenbankzugang mit Rechten nur auf dieses
 * Schema (B5) braucht es dann schon angelegt (Betrieb).
 */
final class PostgresSchema
{
    /** @var array<string, true> geprüfte Verbindungen */
    private static array $checked = [];

    public static function ensureFor(Connection $db): void
    {
        $name = (string) $db->getName();
        if ($db->getDriverName() !== 'pgsql' || isset(self::$checked[$name])) {
            return;
        }
        self::$checked[$name] = true;
        self::ensure($name);
    }

    public static function ensure(?string $connection = null): void
    {
        $db = DB::connection($connection);
        if ($db->getDriverName() !== 'pgsql') {
            return;
        }
        $schema = self::name($connection);
        $exists = $db->selectOne('select 1 as ok from information_schema.schemata where schema_name = ?', [$schema]) !== null;
        if ($exists) {
            return;
        }
        try {
            $db->statement('create schema "'.str_replace('"', '', $schema).'"');
        } catch (Throwable $e) {
            Log::error('glossary.db.schema_missing', ['schema' => $schema, 'exception' => $e::class]);

            throw $e;
        }
    }

    public static function name(?string $connection = null): string
    {
        $connection ??= (string) config('database.default');
        $path = config("database.connections.{$connection}.search_path", 'glossary');
        $first = is_array($path) ? (string) ($path[0] ?? 'glossary') : explode(',', (string) $path)[0];

        return trim($first, ' "');
    }
}
