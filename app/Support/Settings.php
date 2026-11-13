<?php

namespace App\Support;

use App\Access\Audit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Allgemeine Einstellungen (#106, Entscheid 08.11.2026): Werte, die in der Administration einstellbar sind. Vorgabe
 * aus der Konfiguration bzw. .env; gespeichert werden nur Abweichungen (Tabelle settings). Ändern nur mit Recht
 * «settings» (systemkritisch), jede Änderung im Protokoll. Geheimnisse, Build-Angaben und die Version der
 * Datenschutzerklärung bleiben ausserhalb.
 */
final class Settings
{
    /**
     * Schlüssel → Definition (Gruppe, Bezeichnung, Hinweis, Typ, Grenzen).
     *
     * @var array<string, array{gruppe: string, label: string, hinweis: string, typ: 'int'|'bool'|'repo'|'url'|'choice', min?: int, max?: int, einheit?: string, optionen?: array<string, string>}>
     */
    public const DEFINITIONS = [
        'retention.inactive_months' => ['gruppe' => 'Löschfristen', 'label' => 'Inaktive Community-Konten löschen nach', 'hinweis' => 'Ohne Anmeldung; vorher kommt ein Hinweis per Mail.', 'typ' => 'int', 'min' => 6, 'max' => 60, 'einheit' => 'Monaten'],
        'retention.notice_days' => ['gruppe' => 'Löschfristen', 'label' => 'Hinweis vor der Löschung', 'hinweis' => 'Tage zwischen Hinweis und Löschung.', 'typ' => 'int', 'min' => 7, 'max' => 90, 'einheit' => 'Tage'],
        'retention.audit_years' => ['gruppe' => 'Löschfristen', 'label' => 'Audit-Protokoll aufbewahren', 'hinweis' => 'Danach werden Einträge gelöscht.', 'typ' => 'int', 'min' => 1, 'max' => 10, 'einheit' => 'Jahre'],
        'retention.proposal_months' => ['gruppe' => 'Löschfristen', 'label' => 'Vorschläge mit Personenbezug', 'hinweis' => 'Nach Abschluss; danach ohne Personenbezug («Community»).', 'typ' => 'int', 'min' => 6, 'max' => 60, 'einheit' => 'Monate'],
        'source_check.months' => ['gruppe' => 'Quellenprüfung', 'label' => 'Wiedervorlage der Herkunft nach', 'hinweis' => 'Seite «Prüfung»: Herkunft nie oder vor dieser Zeit geprüft.', 'typ' => 'int', 'min' => 1, 'max' => 60, 'einheit' => 'Monaten'],
        'registration.open' => ['gruppe' => 'Konten', 'label' => 'Registrierung offen', 'hinweis' => 'Neue Konten selbst anlegen (mit E-Mail oder Microsoft). Wirkt nur mit eingerichtetem Versand (Systemseite); «Passwort vergessen» und Einladungen gehen unabhängig davon.', 'typ' => 'bool'],
        'publish.four_eyes' => ['gruppe' => 'Freigabe', 'label' => 'Vier-Augen-Prinzip', 'hinweis' => 'Freigeben nicht durch die Person der letzten Änderung. Gilt für «Veröffentlichen»; Zusammenführen und Wiederherstellen sind ausgenommen.', 'typ' => 'bool'],
        'privacy.draft_notice' => ['gruppe' => 'Hinweise und Links', 'label' => 'Hinweis «Datenschutzerklärung im Entwurf»', 'hinweis' => 'Auf /datenschutz, bis die Erklärung juristisch geprüft ist (#66).', 'typ' => 'bool'],
        'links.github_repo' => ['gruppe' => 'Hinweise und Links', 'label' => 'GitHub-Repository für «Fehler melden»', 'hinweis' => 'Form «Besitzer/Repository»; leer blendet den Link aus.', 'typ' => 'repo'],
        'updates.check' => ['gruppe' => 'Updates', 'label' => 'Auf neue Versionen prüfen', 'hinweis' => 'Fragt höchstens täglich die öffentlichen Releases auf GitHub ab (nur die Versionsnummer, keine Daten dieser Installation) und zeigt einen Hinweis in der Administration.', 'typ' => 'bool'],
        'updates.policy' => ['gruppe' => 'Updates', 'label' => 'Automatische Updates', 'hinweis' => 'Nur mit eingerichtetem Update-Dienst. «Nur Korrekturen» spielt X.Y.Z mit gleicher Haupt- und Nebenversion ein; neue Funktionen nie automatisch über eine Hauptversion.', 'typ' => 'choice', 'optionen' => ['aus' => 'aus', 'korrektur' => 'nur Korrekturen', 'funktion' => 'Korrekturen und neue Funktionen']],
        'updates.window' => ['gruppe' => 'Updates', 'label' => 'Zeitfenster für automatische Updates', 'hinweis' => 'Stunde, ab der automatische Updates laufen dürfen (Ortszeit), z. B. 3 für 03:00–04:00.', 'typ' => 'int', 'min' => 0, 'max' => 23, 'einheit' => 'Uhr'],
        'links.pages_url' => ['gruppe' => 'Hinweise und Links', 'label' => 'Adresse der Lesefassung (GitHub Pages)', 'hinweis' => 'Mit https://; leer blendet den Link aus.', 'typ' => 'url'],
    ];

    /** Zwischenspeicher je Anwendungsinstanz (pro Anfrage bzw. Test frisch). */
    private const BINDING = 'glossary.settings.stored';

    /** Vorgabe aus Konfiguration bzw. .env. */
    public static function default(string $key): mixed
    {
        return match ($key) {
            'retention.inactive_months' => (int) config('glossary.retention.inactive_months', 24),
            'retention.notice_days' => (int) config('glossary.retention.notice_days', 30),
            'retention.audit_years' => (int) config('glossary.retention.audit_years', 5),
            'retention.proposal_months' => 24,
            'source_check.months' => 12,
            'publish.four_eyes' => false,
            'registration.open' => (bool) config('glossary.registration_open', false),
            'privacy.draft_notice' => (bool) config('glossary.privacy_draft', true),
            'links.github_repo' => (string) config('glossary.github_repo', ''),
            'links.pages_url' => (string) config('glossary.pages_url', ''),
            'updates.check' => (bool) config('glossary.update_check', true),
            'updates.policy' => 'korrektur',
            'updates.window' => 3,
            default => throw new InvalidArgumentException("Unbekannte Einstellung «{$key}»."),
        };
    }

    public static function get(string $key): mixed
    {
        $stored = self::stored();

        return array_key_exists($key, $stored) ? $stored[$key] : self::default($key);
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    public static function bool(string $key): bool
    {
        return (bool) self::get($key);
    }

    public static function string(string $key): string
    {
        return (string) self::get($key);
    }

    /**
     * Mehrere Werte setzen (gleich der Vorgabe = Eintrag entfernen). Ungültige Werte → Ausnahme, nichts gespeichert.
     *
     * @param  array<string, mixed>  $values
     * @return list<string> geänderte Schlüssel
     */
    public static function set(User $actor, array $values): array
    {
        $clean = [];
        foreach ($values as $key => $value) {
            if (! isset(self::DEFINITIONS[$key])) {
                throw new InvalidArgumentException("Unbekannte Einstellung «{$key}».");
            }
            $clean[$key] = self::validate($key, $value);
        }
        $changed = [];
        DB::transaction(function () use ($actor, $clean, &$changed): void {
            foreach ($clean as $key => $value) {
                $old = self::get($key);
                if ($old === $value) {
                    continue;
                }
                if ($value === self::default($key)) {
                    DB::table('settings')->where('key', $key)->delete();
                } else {
                    DB::table('settings')->updateOrInsert(['key' => $key], [
                        'value' => json_encode($value), 'updated_by_id' => $actor->id, 'updated_at' => now(),
                    ]);
                }
                Audit::log($actor, null, 'settings.changed', ['key' => $key, 'old' => $old, 'new' => $value]);
                $changed[] = $key;
            }
        });
        self::flush();

        return $changed;
    }

    /** Zwischenspeicher leeren (Tests, nach Änderungen). */
    public static function flush(): void
    {
        app()->forgetInstance(self::BINDING);
    }

    private static function validate(string $key, mixed $value): mixed
    {
        $def = self::definition($key);
        $label = (string) $def['label'];
        $min = (int) ($def['min'] ?? 0);
        $max = (int) ($def['max'] ?? PHP_INT_MAX);

        return match ((string) $def['typ']) {
            'int' => (function () use ($label, $min, $max, $value): int {
                if (! is_numeric($value) || (int) $value != $value || (int) $value < $min || (int) $value > $max) {
                    throw new InvalidArgumentException("«{$label}»: ganze Zahl von {$min} bis {$max}.");
                }

                return (int) $value;
            })(),
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'repo' => (function () use ($label, $value): string {
                $v = trim((string) $value);
                if ($v !== '' && preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $v) !== 1) {
                    throw new InvalidArgumentException("«{$label}»: Form «Besitzer/Repository».");
                }

                return $v;
            })(),
            'choice' => (function () use ($label, $value, $def): string {
                $v = (string) $value;
                /** @var array<string, string> $options */
                $options = $def['optionen'] ?? [];
                if (! array_key_exists($v, $options)) {
                    throw new InvalidArgumentException("«{$label}»: ungültige Auswahl.");
                }

                return $v;
            })(),
            'url' => (function () use ($label, $value): string {
                $v = trim((string) $value);
                if ($v !== '' && preg_match('#^https://\S+$#i', $v) !== 1) {
                    throw new InvalidArgumentException("«{$label}»: Adresse mit https://.");
                }

                return $v;
            })(),
            default => throw new InvalidArgumentException("Unbekannter Typ der Einstellung «{$key}»."),
        };
    }

    /** @return array<string, mixed> */
    private static function definition(string $key): array
    {
        return self::DEFINITIONS[$key];
    }

    /** @return array<string, mixed> */
    private static function stored(): array
    {
        if (app()->bound(self::BINDING)) {
            /** @var array<string, mixed> */
            return app(self::BINDING);
        }
        // vor der Migration (z. B. erster Start) gelten die Vorgaben – ohne Schema-Abfrage bei jeder Anfrage (#137)
        try {
            $stored = DB::table('settings')->pluck('value', 'key')
                ->map(fn (mixed $v): mixed => json_decode((string) $v, true))->all();
        } catch (QueryException) {
            return [];
        }
        app()->instance(self::BINDING, $stored);

        return $stored;
    }
}
