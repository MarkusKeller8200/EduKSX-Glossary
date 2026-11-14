<?php

namespace App\Console\Commands;

use App\Support\DatabaseDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

/**
 * Tägliche, konsistente Kopie der Datenbank (#197) nach storage/app/backups/taeglich.sqlite, auf PostgreSQL als Dump
 * nach taeglich.zip (#357). Der Dienst «backup»
 * (docker/compose.backup.yaml) sichert sie verschlüsselt ausser Haus; hier wird nur die lokale Kopie erzeugt.
 */
class DailyBackup extends Command
{
    protected $signature = 'glossary:sicherung';

    protected $description = 'Konsistente Kopie der Datenbank für die Sicherung ausser Haus';

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (DB::connection()->getDriverName() === 'pgsql') {
            return $this->dump($dir);
        }
        if (config('database.default') !== 'sqlite') {
            $this->line('Nur für SQLite und PostgreSQL.');

            return self::SUCCESS;
        }
        $source = (string) config('database.connections.sqlite.database');
        if ($source === '' || $source === ':memory:' || ! is_file($source)) {
            $this->error('Datenbankdatei nicht gefunden.');

            return self::FAILURE;
        }
        $target = $dir.'/taeglich.sqlite';
        $tmp = $dir.'/taeglich.sqlite.tmp';
        @unlink($tmp);
        try {
            // VACUUM INTO über eine eigene Verbindung: vollständige, in sich stimmige Kopie, auch während die App schreibt
            $pdo = new PDO('sqlite:'.$source);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("VACUUM INTO '".str_replace("'", "''", $tmp)."'");
            rename($tmp, $target);
        } catch (Throwable $e) {
            @unlink($tmp);
            $this->error('Sicherung fehlgeschlagen: '.class_basename($e));

            return self::FAILURE;
        }
        $this->info('Kopie erstellt: storage/app/backups/taeglich.sqlite');

        return self::SUCCESS;
    }

    /**
     * PostgreSQL (#357): Dump im Format von glossary:dump nach taeglich.zip (mit GLOSSARY_DUMP_PASSWORD verschlüsselt);
     * einspielen mit glossary:wiederherstellen.
     */
    private function dump(string $dir): int
    {
        $target = $dir.'/taeglich.zip';
        $tmp = $dir.'/taeglich.zip.tmp';
        @unlink($tmp);
        try {
            $manifest = DatabaseDump::create($tmp, (string) config('glossary.dump_password', ''));
            rename($tmp, $target);
        } catch (Throwable $e) {
            @unlink($tmp);
            $this->error('Sicherung fehlgeschlagen: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info('Dump erstellt: storage/app/backups/taeglich.zip ('.array_sum($manifest['tables']).' Zeilen'.($manifest['encrypted'] ? ', verschlüsselt' : '').')');

        return self::SUCCESS;
    }
}
