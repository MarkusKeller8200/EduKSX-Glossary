<?php

namespace App\Console\Commands;

use App\Support\DumpArchive;
use App\Support\DumpRestore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Dump auf dev oder test einspielen (#313, DB-Standard B12): nur **anonymisierte** Dumps (`glossary:dump --anonymisiert`),
 * nie in production (dort: `glossary:wiederherstellen`). Ersetzt alle Daten der Anwendung; Schema und Migrationen müssen
 * zum Dump passen. SQLite und PostgreSQL.
 */
class LoadDump extends Command
{
    protected $signature = 'glossary:dump-einspielen
        {datei : ZIP-Datei aus glossary:dump --anonymisiert}
        {--passwort= : Passwort des Dumps (sonst GLOSSARY_DUMP_PASSWORD)}
        {--ja : ohne Rückfrage}';

    protected $description = 'Anonymisierten Dump auf dev oder test einspielen (ersetzt alle Daten)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Einspielen ist in production nicht erlaubt (vollständige Sicherung: glossary:wiederherstellen).');

            return self::FAILURE;
        }
        try {
            $dump = DumpArchive::open((string) $this->argument('datei'), (string) ($this->option('passwort') ?? config('glossary.dump_password', '')));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        try {
            if (! $dump->anonymized()) {
                $this->error('Der Dump ist nicht anonymisiert – auf dev und test nur Dumps aus «glossary:dump --anonymisiert» (DB-Standard B12).');

                return self::FAILURE;
            }
            if (! $this->option('ja') && ! $this->confirm('Alle Daten dieser Datenbank werden ersetzt. Weiter?')) {
                return self::FAILURE;
            }
            $counts = DumpRestore::run($dump, DB::connection());
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $dump->close();
        }
        $this->info('Eingespielt: '.count($counts).' Tabellen, '.array_sum($counts).' Zeilen (anonymisiert vom '.($dump->manifest['created_at'] ?? '?').').');

        return self::SUCCESS;
    }
}
