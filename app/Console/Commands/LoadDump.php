<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

/**
 * Dump auf dev oder test einspielen (#313, DB-Standard B12): nur **anonymisierte** Dumps (`glossary:dump --anonymisiert`),
 * nie in production. Ersetzt alle Daten der Datenbank; Schema und Migrationen müssen zum Dump passen.
 * Nur SQLite (dev und test); für PostgreSQL danach `glossary:umzug-postgres`.
 */
class LoadDump extends Command
{
    protected $signature = 'glossary:dump-einspielen
        {datei : ZIP-Datei aus glossary:dump --anonymisiert}
        {--passwort= : Passwort des Dumps (sonst GLOSSARY_DUMP_PASSWORD)}
        {--ja : ohne Rückfrage}';

    protected $description = 'Anonymisierten Dump auf dev oder test einspielen (ersetzt alle Daten)';

    private const CHUNK = 500;

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Einspielen ist in production nicht erlaubt (Wiederherstellung: Sicherung, docs/LOESCHKONZEPT.md).');

            return self::FAILURE;
        }
        $db = DB::connection();
        if ($db->getDriverName() !== 'sqlite') {
            $this->error('Nur auf SQLite (dev, test); für PostgreSQL danach glossary:umzug-postgres.');

            return self::FAILURE;
        }
        $zip = new ZipArchive;
        if ($zip->open((string) $this->argument('datei')) !== true) {
            $this->error('ZIP-Datei lässt sich nicht öffnen.');

            return self::FAILURE;
        }
        $password = (string) ($this->option('passwort') ?? config('glossary.dump_password', ''));
        if ($password !== '') {
            $zip->setPassword($password);
        }
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        if (! is_array($manifest)) {
            $this->error('Manifest fehlt oder ist nicht lesbar (Passwort?).');

            return self::FAILURE;
        }
        if (($manifest['anonymized'] ?? false) !== true) {
            $this->error('Der Dump ist nicht anonymisiert – auf dev und test nur Dumps aus «glossary:dump --anonymisiert» (DB-Standard B12).');

            return self::FAILURE;
        }
        $local = DB::table('migrations')->orderBy('migration')->pluck('migration')->all();
        $dumped = array_map(fn (array $r): string => (string) $r['migration'], $this->rows($zip, 'migrations'));
        sort($dumped);
        if ($local !== $dumped) {
            $this->error('Migrationen passen nicht zum Dump – zuerst die Version des Dumps installieren bzw. migrieren.');

            return self::FAILURE;
        }
        if (! $this->option('ja') && ! $this->confirm('Alle Daten dieser Datenbank werden ersetzt. Weiter?')) {
            return self::FAILURE;
        }

        $tables = array_keys((array) $manifest['tables']);
        $counts = [];
        DB::transaction(function () use ($zip, $tables, &$counts): void {
            DB::statement('PRAGMA defer_foreign_keys = ON');   // Verweise erst beim Abschluss prüfen
            $tables = array_values(array_diff($tables, ['migrations']));
            // zuerst alles leeren (Kaskaden), dann einspielen
            foreach ($tables as $table) {
                DB::table($table)->delete();
            }
            foreach ($tables as $table) {
                $batch = [];
                $counts[$table] = 0;
                foreach ($this->rows($zip, $table) as $row) {
                    $batch[] = array_map(fn ($v) => is_array($v) && isset($v['base64']) ? base64_decode((string) $v['base64']) : $v, $row);
                    $counts[$table]++;
                    if (count($batch) >= self::CHUNK) {
                        DB::table($table)->insert($batch);
                        $batch = [];
                    }
                }
                if ($batch !== []) {
                    DB::table($table)->insert($batch);
                }
            }
        });
        $zip->close();

        foreach ($counts as $table => $count) {
            if ($count !== (int) $manifest['tables'][$table]) {
                throw new RuntimeException("Zählung weicht ab: {$table}");
            }
        }
        $this->info('Eingespielt: '.count($counts).' Tabellen, '.array_sum($counts).' Zeilen (anonymisiert vom '.($manifest['created_at'] ?? '?').').');

        return self::SUCCESS;
    }

    /** @return list<array<string, mixed>> */
    private function rows(ZipArchive $zip, string $table): array
    {
        $content = $zip->getFromName('tabellen/'.$table.'.jsonl');
        if ($content === false) {
            return [];
        }
        $rows = [];
        foreach (explode("\n", $content) as $line) {
            if (trim($line) !== '') {
                $rows[] = (array) json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            }
        }

        return $rows;
    }
}
