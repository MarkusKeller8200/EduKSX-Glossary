<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Prüfung der Konfiguration einer Installation (#196, docs/KONZEPT-DEPLOYMENT.md D3): fehlende, unsichere oder
 * widersprüchliche Einstellungen. Für `php artisan glossary:pruefen` und die Systemseite. Gibt nie Werte von
 * Geheimnissen aus, nur ob sie gesetzt sind.
 */
final class ConfigCheck
{
    public const OK = 'ok';

    public const WARNUNG = 'warnung';

    public const FEHLER = 'fehler';

    /**
     * @return list<array{bereich: string, pruefung: string, status: string, hinweis: string}>
     */
    public static function run(): array
    {
        $production = config('app.env') === 'production';
        $checks = [];
        $add = function (string $bereich, string $pruefung, string $status, string $hinweis = '') use (&$checks): void {
            $checks[] = ['bereich' => $bereich, 'pruefung' => $pruefung, 'status' => $status, 'hinweis' => $hinweis];
        };

        // Anwendung
        $add('Anwendung', 'Schlüssel (APP_KEY)', (string) config('app.key') !== '' ? self::OK : self::FEHLER,
            (string) config('app.key') !== '' ? '' : 'APP_KEY bzw. APP_KEY_FILE setzen; im Container wird er sonst einmalig erzeugt.');
        $add('Anwendung', 'Fehleranzeige (APP_DEBUG)', $production && (bool) config('app.debug') ? self::FEHLER : self::OK,
            $production && (bool) config('app.debug') ? 'Im Betrieb APP_DEBUG=false setzen – sonst sieht man Details zu Fehlern.' : '');

        $url = (string) config('app.url');
        $host = (string) (parse_url($url, PHP_URL_HOST) ?? '');
        $expected = (string) (getenv('GLOSSARY_HOST') ?: '');
        if ($production && ! str_starts_with($url, 'https://')) {
            $add('Anwendung', 'Adresse (APP_URL)', self::FEHLER, 'Im Betrieb mit https:// – sonst stimmen Links und sichere Cookies nicht.');
        } elseif ($expected !== '' && strcasecmp($expected, $host) !== 0) {
            $add('Anwendung', 'Adresse (APP_URL)', self::FEHLER, "APP_URL zeigt auf «{$host}», GLOSSARY_HOST ist «{$expected}».");
        } elseif ($host === '' || in_array($host, ['localhost', '127.0.0.1'], true)) {
            $add('Anwendung', 'Adresse (APP_URL)', $production ? self::FEHLER : self::OK, $production ? 'APP_URL auf die öffentliche Adresse setzen.' : 'lokal');
        } else {
            $add('Anwendung', 'Adresse (APP_URL)', self::OK, $host);
        }

        $secure = (bool) config('session.secure');
        $add('Anwendung', 'Sichere Cookies (SESSION_SECURE_COOKIE)', $production && ! $secure ? self::FEHLER : self::OK,
            $production && ! $secure ? 'Im Betrieb SESSION_SECURE_COOKIE=true setzen.' : '');

        // Datenbank und Speicher
        try {
            DB::select('select 1');
            $add('Daten', 'Datenbank erreichbar', self::OK, (string) config('database.default'));
        } catch (Throwable $e) {
            $add('Daten', 'Datenbank erreichbar', self::FEHLER, class_basename($e).' – Verbindung bzw. Datei prüfen.');
        }
        $add('Daten', 'Speicher beschreibbar (storage/)', is_writable(storage_path()) ? self::OK : self::FEHLER,
            is_writable(storage_path()) ? '' : 'Rechte des Volumens prüfen (Besitzer www-data).');

        if (config('database.default') === 'sqlite') {
            $backups = glob(storage_path('app/backups/*.sqlite')) ?: [];
            $latest = $backups === [] ? null : max(array_map(fn (string $f): int => (int) filemtime($f), $backups));
            $add('Daten', 'Sicherung vor dem Start', $latest === null ? ($production ? self::WARNUNG : self::OK) : self::OK,
                $latest === null ? 'Noch keine Sicherung in storage/app/backups (entsteht beim nächsten Start des Containers).' : 'zuletzt '.date('d.m.Y H:i', $latest));
        }

        // Sicherung ausser Haus (#197): Stand des Dienstes «backup»
        $offsite = storage_path('app/backups/sicherung.json');
        if (is_file($offsite)) {
            $o = json_decode((string) file_get_contents($offsite), true);
            $am = is_array($o) ? strtotime((string) ($o['am'] ?? '')) : false;
            $failed = ! is_array($o) || ($o['ergebnis'] ?? '') !== 'ok';
            $stale = $am === false || time() - $am > 2 * 86400;
            $add('Daten', 'Sicherung ausser Haus', $failed ? self::FEHLER : ($stale ? self::WARNUNG : self::OK),
                $failed ? (string) ($o['meldung'] ?? 'unbekannter Fehler') : ($stale ? 'Letzte Sicherung älter als zwei Tage.' : 'zuletzt '.date('d.m.Y H:i', (int) $am)));
        } else {
            $add('Daten', 'Sicherung ausser Haus', self::OK, 'nicht über den Dienst «backup» eingerichtet (z. B. Sicherung durch den Betrieb)');
        }

        // Mail und Warteschlange
        $mailHint = MailSettings::unverified()
            ? 'In der Administration eingestellt, aber noch ohne erfolgreiche Testmail (Systemseite).'
            : 'Ohne Versand bleibt die Registrierung aus (Systemseite oder MAIL_MAILER, MAIL_FROM_ADDRESS).';
        $add('Mail', 'Versand eingerichtet', MailSetup::configured() ? self::OK : self::WARNUNG,
            MailSetup::configured() ? (string) config('mail.default').' ('.MailSettings::source().')' : $mailHint);
        $sync = config('queue.default') === 'sync';
        $add('Mail', 'Warteschlange (QUEUE_CONNECTION)', $production && $sync && MailSetup::configured() ? self::WARNUNG : self::OK,
            $production && $sync && MailSetup::configured() ? 'Mit «sync» gibt es keine Wiederholung bei Fehlern; «database» mit Dienst «worker» verwenden.' : (string) config('queue.default'));

        // Paare: beide oder keiner
        foreach ([
            'Spam-Schutz (Turnstile)' => ['glossary.turnstile.site_key', 'glossary.turnstile.secret'],
            'Anmeldung mit Microsoft' => ['services.microsoft.client_id', 'services.microsoft.client_secret'],
        ] as $label => [$a, $b]) {
            $hasA = (string) config($a) !== '';
            $hasB = (string) config($b) !== '';
            $add('Dienste', $label, $hasA === $hasB ? self::OK : self::WARNUNG,
                $hasA === $hasB ? ($hasA ? 'eingeschaltet' : 'aus') : 'Nur einer der beiden Werte ist gesetzt – der Dienst bleibt aus.');
        }

        return $checks;
    }

    /** @param  list<array{status: string}>  $checks */
    public static function hasErrors(array $checks): bool
    {
        return array_filter($checks, fn (array $c): bool => $c['status'] === self::FEHLER) !== [];
    }
}
