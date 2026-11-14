<?php

namespace App\Console\Commands;

use App\Access\Audit;
use App\Support\DatabaseDump;
use App\Support\DumpArchive;
use App\Support\DumpRestore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Vollständige Sicherung wiederherstellen (#353): einen Dump aus `glossary:dump` (nicht anonymisiert) einspielen – zum
 * Zurückspielen einer Sicherung oder für den Umzug der Daten auf eine neue Instanz (z. B. von dev nach prod, Ablauf in
 * docs/KONZEPT-DEPLOYMENT.md «Daten auf eine andere Instanz übernehmen»).
 *
 * Schutz: nur in production (echte Daten, DB-Standard B12), gleiche Migrationen wie der Dump, Prüfsummen, vorher eine
 * Sicherung des aktuellen Stands, Wartungsmodus, eine Transaktion (Abbruch = unverändert), Zählung je Tabelle, Eintrag
 * im Protokoll. Werte mit anderem APP_KEY werden mit dem Schlüssel der Quelle neu verschlüsselt.
 */
class RestoreDump extends Command
{
    protected $signature = 'glossary:wiederherstellen
        {datei : ZIP-Datei aus glossary:dump}
        {--passwort= : Passwort des Dumps (sonst GLOSSARY_DUMP_PASSWORD oder Eingabe)}
        {--schluessel-quelle= : APP_KEY der Quelle, falls er sich unterscheidet (sonst Eingabe)}
        {--trocken : Nur prüfen, nichts ändern}
        {--ja : ohne Rückfrage}';

    protected $description = 'Vollständige Sicherung (Dump) einspielen – ersetzt alle Daten';

    public function handle(): int
    {
        if (! app()->environment('production')) {
            $this->error('Vollständige Dumps enthalten echte Daten und werden nur in production eingespielt (dev und test: glossary:dump-einspielen mit anonymisiertem Dump).');

            return self::FAILURE;
        }
        $file = (string) $this->argument('datei');
        $counts = [];
        $backup = '';
        $sourceKey = '';
        $dump = $this->open($file);
        if ($dump === null) {
            return self::FAILURE;
        }

        try {
            if ($dump->anonymized()) {
                $this->error('Der Dump ist anonymisiert – in production nur vollständige Dumps einspielen.');

                return self::FAILURE;
            }
            $db = DB::connection();
            $this->summary($dump, $db->getDriverName());

            $sourceKey = (string) ($this->option('schluessel-quelle') ?? '');
            if ($sourceKey === '' && $this->input->isInteractive() && DumpRestore::keyDiffers($dump, $db)) {
                $this->warn('Der Dump stammt von einer Instanz mit anderem APP_KEY (zweiter Faktor, Passwort des Mailversands).');
                $sourceKey = (string) $this->secret('APP_KEY der Quelle (base64:…)');
            }

            $tables = DumpRestore::dryRun($dump, $db, $sourceKey);
            $this->info('Prüfung bestanden: Prüfsummen, Migrationen, '.count($tables).' Tabellen, Schlüssel.');
            if ($this->option('trocken')) {
                return self::SUCCESS;
            }
            if (! $this->option('ja') && $this->ask('Alle Daten dieser Instanz werden ersetzt. Zur Bestätigung «WIEDERHERSTELLEN» eingeben') !== 'WIEDERHERSTELLEN') {
                $this->line('Abgebrochen, nichts geändert.');

                return self::FAILURE;
            }

            // Sicherung des aktuellen Stands – Rückweg, falls die falsche Datei eingespielt wurde
            $backup = storage_path('app/backups/vor-wiederherstellung-'.now()->utc()->format('Ymd\THis\Z').'.zip');
            if (! is_dir(dirname($backup))) {
                mkdir(dirname($backup), 0775, true);
            }
            DatabaseDump::create($backup, (string) config('glossary.dump_password', ''));
            $this->line('Sicherung des bisherigen Stands: '.$backup);

            $wasDown = app()->isDownForMaintenance();
            if (! $wasDown) {
                $this->callSilently('down', ['--retry' => 60]);
            }
            try {
                $counts = DumpRestore::run($dump, $db, $sourceKey);
            } finally {
                if (! $wasDown) {
                    $this->callSilently('up');
                }
            }
            $this->callSilently('cache:clear');
        } catch (Throwable $e) {
            Log::error('glossary.restore.failed', ['file' => basename($file), 'exception' => $e::class, 'message' => $e->getMessage()]);
            $this->error('Wiederherstellung abgebrochen, Daten unverändert: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            $dump->close();
        }

        $details = [
            'datei' => basename($file),
            'erstellt' => (string) ($dump->manifest['created_at'] ?? ''),
            'version' => (string) ($dump->manifest['app_version'] ?? ''),
            'quelle' => (string) ($dump->manifest['driver'] ?? ''),
            'tabellen' => count($counts),
            'zeilen' => array_sum($counts),
            'schluessel_neu' => $sourceKey !== '',
            'sicherung_vorher' => basename($backup),
        ];
        Audit::log(null, null, 'system.restore', $details);
        Log::warning('glossary.restore.done', $details);

        $this->table(['Tabelle', 'Zeilen'], array_map(fn (string $t, int $n): array => [$t, $n], array_keys($counts), $counts));
        $this->info('Wiederhergestellt: '.count($counts).' Tabellen, '.array_sum($counts).' Zeilen, Zählung gleich. Alle Sitzungen sind beendet.');
        $this->line('Danach: Anmeldung prüfen, /health prüfen; Löschfristen mit «php artisan glossary:loeschfristen --trocken» ansehen (docs/LOESCHKONZEPT.md).');

        return self::SUCCESS;
    }

    private function open(string $file): ?DumpArchive
    {
        $password = (string) ($this->option('passwort') ?? config('glossary.dump_password', ''));
        try {
            return DumpArchive::open($file, $password);
        } catch (RuntimeException $e) {
            if ($password !== '' || ! $this->input->isInteractive() || ! str_contains($e->getMessage(), 'Passwort')) {
                $this->error($e->getMessage());

                return null;
            }
        }
        try {
            return DumpArchive::open($file, (string) $this->secret('Passwort des Dumps'));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return null;
        }
    }

    private function summary(DumpArchive $dump, string $driver): void
    {
        $m = $dump->manifest;
        $this->table(['', 'Dump', 'Diese Instanz'], [
            ['Version', (string) ($m['app_version'] ?? '?'), (string) config('glossary.version')],
            ['Datenbank', (string) ($m['driver'] ?? '?'), $driver],
            ['Erstellt', (string) ($m['created_at'] ?? '?'), ''],
            ['Zeilen', (string) array_sum($dump->tables()), ''],
        ]);
    }
}
