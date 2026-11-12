<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Tägliche, konsistente Kopie der Datenbank (#197) nach storage/app/backups/taeglich.sqlite. Der Dienst «backup»
 * (docker/compose.backup.yaml) sichert sie verschlüsselt ausser Haus; hier wird nur die lokale Kopie erzeugt.
 */
class DailyBackup extends Command
{
    protected $signature = 'glossary:sicherung';

    protected $description = 'Konsistente Kopie der Datenbank für die Sicherung ausser Haus';

    public function handle(): int
    {
        if (config('database.default') !== 'sqlite') {
            $this->line('Nur für SQLite; andere Datenbanken sichert der Betrieb mit einem Dump.');

            return self::SUCCESS;
        }
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $target = $dir.'/taeglich.sqlite';
        $tmp = $dir.'/taeglich.sqlite.tmp';
        @unlink($tmp);
        try {
            // VACUUM INTO: vollständige, in sich stimmige Kopie, auch während die App schreibt
            DB::statement("VACUUM INTO '".str_replace("'", "''", $tmp)."'");
            rename($tmp, $target);
        } catch (Throwable $e) {
            @unlink($tmp);
            $this->error('Sicherung fehlgeschlagen: '.class_basename($e));

            return self::FAILURE;
        }
        $this->info('Kopie erstellt: storage/app/backups/taeglich.sqlite');

        return self::SUCCESS;
    }
}
