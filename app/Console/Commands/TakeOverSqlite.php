<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

/**
 * Bestehende SQLite-Datenbank beim Wechsel auf PostgreSQL automatisch übernehmen (#357). Läuft bei jedem Start des
 * Dienstes «app» (docker/entrypoint.sh), tut aber nur einmal etwas:
 *
 * - die App läuft auf PostgreSQL, die SQLite-Datei ist vorhanden und nicht leer,
 * - in PostgreSQL gibt es noch keine Konten (frisch migriert),
 * - die Übernahme ist noch nicht vermerkt (`storage/app/database/postgres-uebernahme.json`).
 *
 * Dann: Kopie der SQLite-Datei nach `storage/app/backups/vor-postgres-<Zeitpunkt>.sqlite`, SQLite auf denselben Stand
 * migrieren, `glossary:umzug-postgres --leeren` (Zählung je Tabelle, eine Transaktion). Schlägt etwas fehl, endet der
 * Befehl mit Fehler und der Container startet nicht; PostgreSQL bleibt leer, die SQLite-Datei unverändert.
 */
class TakeOverSqlite extends Command
{
    protected $signature = 'glossary:postgres-uebernahme
        {--quelle=sqlite : Verbindung der SQLite-Datenbank}';

    protected $description = 'SQLite-Datenbank beim ersten Start auf PostgreSQL übernehmen (einmalig, automatisch)';

    public static function markerPath(): string
    {
        return storage_path('app/database/postgres-uebernahme.json');
    }

    public function handle(): int
    {
        $target = DB::connection();
        if ($target->getDriverName() !== 'pgsql') {
            return self::SUCCESS;
        }
        if (is_file(self::markerPath())) {
            return self::SUCCESS;
        }
        $quelle = (string) $this->option('quelle');
        $file = (string) config("database.connections.{$quelle}.database");
        if ($file === '' || $file === ':memory:' || ! is_file($file) || filesize($file) === 0) {
            return self::SUCCESS;   // neue Installation: nichts zu übernehmen
        }
        $source = DB::connection($quelle);
        if (! $source->getSchemaBuilder()->hasTable('users') || $source->table('users')->count() === 0) {
            return self::SUCCESS;
        }
        if ($target->table('users')->exists()) {
            $this->warn('[glossary] PostgreSQL enthält schon Konten – keine automatische Übernahme der SQLite-Datei (von Hand: glossary:umzug-postgres).');

            return self::SUCCESS;
        }

        $this->info('[glossary] Übernahme der SQLite-Datenbank nach PostgreSQL …');
        $backup = storage_path('app/backups/vor-postgres-'.now()->utc()->format('Ymd\THis\Z').'.sqlite');
        try {
            if (! is_dir(dirname($backup))) {
                mkdir(dirname($backup), 0775, true);
            }
            $pdo = new PDO('sqlite:'.$file);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("VACUUM INTO '".str_replace("'", "''", $backup)."'");
            $pdo = null;
        } catch (Throwable $e) {
            $this->error('[glossary] Kopie der SQLite-Datei fehlgeschlagen: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->line('[glossary] Kopie: '.$backup);

        if ($this->call('migrate', ['--database' => $quelle, '--force' => true]) !== self::SUCCESS) {
            $this->error('[glossary] SQLite-Datei lässt sich nicht auf den aktuellen Stand bringen – keine Übernahme.');

            return self::FAILURE;
        }
        try {
            $result = $this->call('glossary:umzug-postgres', ['--quelle' => $quelle, '--ziel' => $target->getName(), '--leeren' => true]);
        } catch (Throwable $e) {
            $this->error('[glossary] Übernahme abgebrochen, PostgreSQL unverändert: '.$e->getMessage());

            return self::FAILURE;
        }
        if ($result !== self::SUCCESS) {
            $this->error('[glossary] Übernahme abgebrochen (Zählung), PostgreSQL unverändert.');

            return self::FAILURE;
        }

        if (! is_dir(dirname(self::markerPath()))) {
            mkdir(dirname(self::markerPath()), 0775, true);
        }
        file_put_contents(self::markerPath(), (string) json_encode([
            'uebernommen_am' => now()->toIso8601String(),
            'quelle' => basename($file),
            'kopie' => basename($backup),
            'konten' => $target->table('users')->count(),
            'begriffe' => $target->table('terms')->count(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info('[glossary] SQLite-Datenbank übernommen. Die SQLite-Datei bleibt als Rückfall liegen.');

        return self::SUCCESS;
    }
}
