<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Vollständiger Dump der Datenbank (#268) als ZIP:
 *
 * - `datenbank/glossary.sqlite` – exakte Kopie (VACUUM INTO) zur Wiederherstellung, nur bei SQLite
 * - `tabellen/<tabelle>.jsonl` – jede Tabelle Zeile für Zeile als JSON, unabhängig von der Datenbank (Umzug auf
 *   PostgreSQL); ausgelassen sind nur flüchtige Tabellen (Sitzungen, Cache)
 * - `manifest.json` – Version, Zeitpunkt, Datenbank, Zeilen je Tabelle, SHA-256 je Datei
 *
 * Mit Passwort sind alle Einträge mit AES-256 verschlüsselt (öffnet z. B. 7-Zip). Ohne Passwort nur, solange
 * GLOSSARY_DUMP_REQUIRE_PASSWORD nicht gesetzt ist.
 */
final class DatabaseDump
{
    /** Flüchtige Tabellen: werden neu aufgebaut, enthalten Sitzungsdaten; nur in der SQLite-Kopie. */
    public const VOLATILE = ['sessions', 'cache', 'cache_locks'];

    public static function passwordRequired(): bool
    {
        return (bool) config('glossary.dump_require_password', false);
    }

    /**
     * Dump in die ZIP-Datei $target schreiben.
     *
     * @return array{format: int, app_version: string, build: string|null, created_at: string, driver: string, encrypted: bool, sqlite_file: bool, tables: array<string, int>, skipped: list<string>, files: array<string, string>}
     */
    public static function create(string $target, ?string $password = null, ?string $connection = null): array
    {
        $password = $password === '' ? null : $password;
        if ($password === null && self::passwordRequired()) {
            throw new RuntimeException('Für den Dump ist ein Passwort vorgeschrieben.');
        }
        if ($password !== null && ! defined(ZipArchive::class.'::EM_AES_256')) {
            throw new RuntimeException('Verschlüsselte ZIP-Dateien werden auf diesem Server nicht unterstützt (libzip ohne AES).');
        }

        /** @var Connection $db */
        $db = DB::connection($connection);
        $work = sys_get_temp_dir().'/glossary-dump-'.bin2hex(random_bytes(6));
        mkdir($work.'/tabellen', 0700, true);

        try {
            $files = [];
            $tables = [];
            $skipped = [];

            $sqlite = $db->getDriverName() === 'sqlite' && self::sqliteCopy($db, $work.'/glossary.sqlite');
            if ($sqlite) {
                $files['datenbank/glossary.sqlite'] = $work.'/glossary.sqlite';
            }

            foreach (self::tableNames($db) as $table) {
                if (in_array($table, self::VOLATILE, true)) {
                    $skipped[] = $table;

                    continue;
                }
                $path = $work.'/tabellen/'.$table.'.jsonl';
                $tables[$table] = self::exportTable($db, $table, $path);
                $files['tabellen/'.$table.'.jsonl'] = $path;
            }

            $manifest = [
                'format' => 1,
                'app_version' => (string) config('glossary.version'),
                'build' => is_string(config('glossary.build')) ? (string) config('glossary.build') : null,
                'created_at' => now()->toIso8601String(),
                'driver' => $db->getDriverName(),
                'encrypted' => $password !== null,
                'sqlite_file' => $sqlite,
                'tables' => $tables,
                'skipped' => $skipped,
                'files' => array_map(fn (string $p): string => (string) hash_file('sha256', $p), $files),
            ];
            file_put_contents($work.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $files['manifest.json'] = $work.'/manifest.json';

            self::zip($target, $files, $password);

            return $manifest;
        } finally {
            self::cleanup($work);
        }
    }

    /** Konsistente Kopie über VACUUM INTO; innerhalb einer Transaktion über eine eigene Verbindung zur Datei. */
    private static function sqliteCopy(Connection $db, string $file): bool
    {
        $quoted = "'".str_replace("'", "''", $file)."'";
        try {
            if ($db->transactionLevel() === 0) {
                $db->getPdo()->exec('VACUUM INTO '.$quoted);

                return is_file($file);
            }
            $source = (string) $db->getConfig('database');
            if ($source === '' || $source === ':memory:' || ! is_file($source)) {
                return false; // nur im Test (Speicher-Datenbank in einer Transaktion)
            }
            $pdo = new \PDO('sqlite:'.$source);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->exec('VACUUM INTO '.$quoted);

            return is_file($file);
        } catch (Throwable $e) {
            throw new RuntimeException('Kopie der SQLite-Datenbank fehlgeschlagen: '.$e->getMessage(), 0, $e);
        }
    }

    /** @return list<string> */
    private static function tableNames(Connection $db): array
    {
        $names = array_map(
            fn (array $t): string => (string) $t['name'],
            $db->getSchemaBuilder()->getTables(),
        );
        $names = array_values(array_filter($names, fn (string $n): bool => ! str_starts_with($n, 'sqlite_')));
        sort($names);

        return $names;
    }

    /** Tabelle Zeile für Zeile als JSON Lines schreiben; liefert die Anzahl Zeilen. */
    private static function exportTable(Connection $db, string $table, string $path): int
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException("Datei für {$table} kann nicht geschrieben werden.");
        }
        $count = 0;
        try {
            foreach ($db->table($table)->cursor() as $row) {
                $values = array_map(
                    fn (mixed $v): mixed => is_string($v) && ! mb_check_encoding($v, 'UTF-8') ? ['base64' => base64_encode($v)] : $v,
                    (array) $row,
                );
                fwrite($handle, json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
                $count++;
            }
        } finally {
            fclose($handle);
        }

        return $count;
    }

    /** @param  array<string, string>  $files  Pfad im ZIP → Datei */
    private static function zip(string $target, array $files, ?string $password): void
    {
        $zip = new ZipArchive;
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ZIP-Datei kann nicht angelegt werden.');
        }
        if ($password !== null) {
            $zip->setPassword($password);
        }
        foreach ($files as $name => $path) {
            $zip->addFile($path, $name);
            if ($password !== null) {
                $zip->setEncryptionName($name, ZipArchive::EM_AES_256);
            }
        }
        if (! $zip->close()) {
            throw new RuntimeException('ZIP-Datei konnte nicht geschrieben werden.');
        }
    }

    private static function cleanup(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
