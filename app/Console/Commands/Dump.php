<?php

namespace App\Console\Commands;

use App\Support\DatabaseDump;
use Illuminate\Console\Command;
use Throwable;

/**
 * Vollständiger Dump der Datenbank (#268) als ZIP mit SQLite-Kopie, allen Tabellen als JSON und Manifest. Passwort über
 * --passwort (Eingabe) oder GLOSSARY_DUMP_PASSWORD; ohne Passwort nur, solange es nicht vorgeschrieben ist.
 */
class Dump extends Command
{
    protected $signature = 'glossary:dump
        {ziel? : ZIP-Datei (Vorgabe: storage/app/backups/dump-<Zeitpunkt>.zip)}
        {--passwort : Passwort abfragen und den Dump verschlüsseln (AES-256)}';

    protected $description = 'Vollständigen Dump der Datenbank als ZIP schreiben (mit oder ohne Passwort)';

    public function handle(): int
    {
        $target = (string) ($this->argument('ziel') ?: storage_path('app/backups/dump-'.now()->utc()->format('Ymd\THis\Z').'.zip'));
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }

        $password = (string) config('glossary.dump_password', '');
        if ($password === '' && $this->option('passwort')) {
            $password = (string) $this->secret('Passwort für den Dump');
            if ($password !== (string) $this->secret('Passwort wiederholen')) {
                $this->error('Die Passwörter stimmen nicht überein.');

                return self::FAILURE;
            }
        }

        try {
            $manifest = DatabaseDump::create($target, $password);
        } catch (Throwable $e) {
            $this->error('Dump fehlgeschlagen: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Dump geschrieben: '.$target.($manifest['encrypted'] ? ' (verschlüsselt)' : ' (ohne Passwort)'));
        $this->table(['Tabelle', 'Zeilen'], array_map(
            fn (string $t, int $n): array => [$t, $n],
            array_keys($manifest['tables']),
            $manifest['tables'],
        ));

        return self::SUCCESS;
    }
}
