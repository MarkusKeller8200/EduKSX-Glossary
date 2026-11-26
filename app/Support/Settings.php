<?php

namespace App\Support;

use App\Access\Audit;
use App\Models\User;
use EduKSX\Core\Settings\Settings as CoreSettings;
use EduKSX\Core\Settings\SettingSource;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Allgemeine Einstellungen (#106, Entscheid 08.11.2026): Werte, die in der Administration einstellbar sind. Vorgabe
 * aus der Konfiguration bzw. .env; gespeichert werden nur Abweichungen (Tabelle settings). Ändern nur mit Recht
 * «settings» (systemkritisch). Geheimnisse, Build-Angaben und die Version der Datenschutzerklärung bleiben ausserhalb.
 *
 * Seit #523 nutzt das Glossary den Baustein von eduksx/core (`EduKSX\Core\Settings\Settings`, KONZEPT-STACK ST5):
 * Vorrang Umgebung → Administration → Vorgabe, Historie in `data_changes`. Diese Klasse bleibt die Schnittstelle im
 * Glossary und schreibt jede Änderung zusätzlich als «settings.changed» ins Audit (Ansicht in der Administration).
 */
final class Settings
{
    /**
     * Schlüssel → Definition (Gruppe, Bezeichnung, Hinweis, Typ, Grenzen) im Format von eduksx/core
     * (`SettingDefinition`); «Besitzer/Repository» ist ein Text mit Muster (#523).
     *
     * `env`: Variable der Umgebung mit Vorrang – ist sie gesetzt, ist die Einstellung in der Administration gesperrt (#366).
     * Typ `secret`: verschlüsselt gespeichert (APP_KEY), nie angezeigt; setzen nur über setSecret().
     *
     * @var array<string, array{group: string, label: string, hint: string, type: 'int'|'bool'|'url'|'choice'|'secret'|'text'|'email', min?: int, max?: int, unit?: string, options?: array<string, string>, env?: string, pattern?: string, pattern_hint?: string}>
     */
    public const DEFINITIONS = [
        'retention.inactive_months' => ['group' => 'Löschfristen', 'label' => 'Inaktive Community-Konten löschen nach', 'hint' => 'Ohne Anmeldung; vorher kommt ein Hinweis per Mail.', 'type' => 'int', 'min' => 6, 'max' => 60, 'unit' => 'Monaten'],
        'retention.notice_days' => ['group' => 'Löschfristen', 'label' => 'Hinweis vor der Löschung', 'hint' => 'Tage zwischen Hinweis und Löschung.', 'type' => 'int', 'min' => 7, 'max' => 90, 'unit' => 'Tage'],
        'retention.audit_years' => ['group' => 'Löschfristen', 'label' => 'Audit-Protokoll aufbewahren', 'hint' => 'Danach werden Einträge gelöscht.', 'type' => 'int', 'min' => 1, 'max' => 10, 'unit' => 'Jahre'],
        'retention.proposal_months' => ['group' => 'Löschfristen', 'label' => 'Vorschläge mit Personenbezug', 'hint' => 'Nach Abschluss; danach ohne Personenbezug («Community»).', 'type' => 'int', 'min' => 6, 'max' => 60, 'unit' => 'Monate'],
        'source_check.months' => ['group' => 'Quellenprüfung', 'label' => 'Wiedervorlage der Herkunft nach', 'hint' => 'Seite «Prüfung»: Herkunft nie oder vor dieser Zeit geprüft.', 'type' => 'int', 'min' => 1, 'max' => 60, 'unit' => 'Monaten'],
        'registration.open' => ['group' => 'Konten', 'label' => 'Registrierung offen', 'hint' => 'Neue Konten selbst anlegen (mit E-Mail oder Microsoft). Wirkt nur mit eingerichtetem Versand (Systemseite); «Passwort vergessen» und Einladungen gehen unabhängig davon.', 'type' => 'bool'],
        'publish.four_eyes' => ['group' => 'Freigabe', 'label' => 'Vier-Augen-Prinzip', 'hint' => 'Freigeben nicht durch die Person der letzten Änderung. Gilt für «Veröffentlichen»; Zusammenführen und Wiederherstellen sind ausgenommen.', 'type' => 'bool'],
        'privacy.draft_notice' => ['group' => 'Hinweise und Links', 'label' => 'Hinweis «Datenschutzerklärung im Entwurf»', 'hint' => 'Auf /datenschutz, bis die Erklärung juristisch geprüft ist (#66).', 'type' => 'bool'],
        'links.github_repo' => ['group' => 'Hinweise und Links', 'label' => 'GitHub-Repository für «Fehler melden»', 'hint' => 'Form «Besitzer/Repository»; leer blendet den Link aus.', 'type' => 'text', 'max' => 200, 'pattern' => self::REPO_PATTERN, 'pattern_hint' => 'Form «Besitzer/Repository».'],
        'updates.check' => ['group' => 'Updates', 'label' => 'Auf neue Versionen prüfen', 'hint' => 'Fragt höchstens täglich die öffentlichen Releases auf GitHub ab (nur die Versionsnummer, keine Daten dieser Installation) und zeigt einen Hinweis in der Administration.', 'type' => 'bool'],
        'updates.policy' => ['group' => 'Updates', 'label' => 'Automatische Updates', 'hint' => 'Nur mit eingerichtetem Update-Dienst. «Nur Korrekturen» spielt X.Y.Z mit gleicher Haupt- und Nebenversion ein; neue Funktionen nie automatisch über eine Hauptversion.', 'type' => 'choice', 'options' => ['aus' => 'aus', 'korrektur' => 'nur Korrekturen', 'funktion' => 'Korrekturen und neue Funktionen']],
        'updates.window' => ['group' => 'Updates', 'label' => 'Zeitfenster für automatische Updates', 'hint' => 'Stunde, ab der automatische Updates laufen dürfen (Ortszeit), z. B. 3 für 03:00–04:00.', 'type' => 'int', 'min' => 0, 'max' => 23, 'unit' => 'Uhr'],
        'links.source_url' => ['group' => 'Hinweise und Links', 'label' => 'Quellcode der laufenden Version', 'hint' => 'Link «Quellcode (AGPL-3.0)» in der Fusszeile; wer den Code ändert, verweist auf das eigene Repository. Mit https://.', 'type' => 'url', 'env' => 'GLOSSARY_SOURCE_URL'],
        'dump.require_password' => ['group' => 'Betrieb', 'label' => 'Vollständige Dumps nur mit Passwort', 'hint' => 'Administration › System und glossary:dump verlangen ein Passwort (AES-256).', 'type' => 'bool', 'env' => 'GLOSSARY_DUMP_REQUIRE_PASSWORD'],
        'metrics.token' => ['group' => 'Betrieb', 'label' => 'Token für die Metriken (/metrics)', 'hint' => 'Prometheus ruft /metrics mit «Authorization: Bearer <Token>» ab. Ohne Token sind die Metriken aus.', 'type' => 'secret', 'env' => 'GLOSSARY_METRICS_TOKEN'],
        'links.pages_url' => ['group' => 'Hinweise und Links', 'label' => 'Adresse der Lesefassung (GitHub Pages)', 'hint' => 'Mit https://; leer blendet den Link aus.', 'type' => 'url'],
        // Entwicklungs-Sync (#478, DEC-041 A12): Rolle dieser Instanz
        'sync.role' => ['group' => 'Betrieb', 'label' => 'Rolle im Entwicklungs-Sync', 'hint' => '«Master» = öffentliches Glossary (prod), «Test» = Testumgebung, «Entwicklung» = dev (nicht mit APP_ENV=production), «Instanz» = selbst gehostet ohne Entwicklungs-Sync.', 'type' => 'choice', 'options' => ['instance' => 'Instanz', 'master' => 'Master', 'test' => 'Test', 'development' => 'Entwicklung'], 'env' => 'GLOSSARY_SYNC_ROLE'],
        // MCP-Server (#261, DEC-029 O2): öffentlich lesen für KI-Assistenten unter /mcp
        'mcp.enabled' => ['group' => 'Schnittstellen', 'label' => 'MCP-Server für KI-Assistenten', 'hint' => 'Assistenten wie Claude, ChatGPT, Copilot oder Rovo lesen veröffentlichte Begriffe unter /mcp (ohne Anmeldung, 60 Anfragen pro Minute und IP-Adresse). Eingaben werden nicht gespeichert.', 'type' => 'bool', 'env' => 'GLOSSARY_MCP'],
        // Melden aus Erweiterung und Einbindung (#480, DEC-042 M4, M7)
        'candidates.anonymous' => ['group' => 'Melden', 'label' => 'Anonymes Melden aus Erweiterung und Einbindung', 'hint' => 'Killswitch: aus = nur angemeldete Personen melden fehlende Begriffe bzw. falsche Erklärungen. Alles landet zuerst bei der Redaktion (Kandidaten).', 'type' => 'bool'],
        'candidates.blocklist' => ['group' => 'Melden', 'label' => 'Gesperrte Wörter', 'hint' => 'Mit Komma getrennt; solche Meldungen werden ohne Fehlermeldung verworfen.', 'type' => 'text', 'max' => 2000],
        'candidates.origins' => ['group' => 'Melden', 'label' => 'Webseiten mit Einbindung, die melden dürfen', 'hint' => 'Ursprünge mit https://, mit Komma getrennt (z. B. https://www.schule.ch). Die Browser-Erweiterung darf immer.', 'type' => 'text', 'max' => 2000],
        // Impressum (#411): je Instanz; leer zeigt die Seite einen Hinweis für die Betreiber
        'imprint.operator' => ['group' => 'Impressum', 'label' => 'Betreiber', 'hint' => 'Name der Person oder Organisation, die diese Instanz betreibt, z. B. «Muster Schule, Schulverwaltung».', 'type' => 'text', 'max' => 150, 'env' => 'GLOSSARY_IMPRESSUM_BETREIBER'],
        'imprint.address' => ['group' => 'Impressum', 'label' => 'Postadresse', 'hint' => 'Strasse, PLZ und Ort.', 'type' => 'text', 'max' => 200, 'env' => 'GLOSSARY_IMPRESSUM_ADRESSE'],
        'imprint.email' => ['group' => 'Impressum', 'label' => 'Kontakt (E-Mail)', 'hint' => 'Allgemeine Anfragen.', 'type' => 'email', 'env' => 'GLOSSARY_IMPRESSUM_EMAIL'],
        'imprint.privacy_email' => ['group' => 'Impressum', 'label' => 'Kontakt Datenschutz (E-Mail)', 'hint' => 'Anfragen zu Personendaten; leer = Kontakt oben.', 'type' => 'email', 'env' => 'GLOSSARY_IMPRESSUM_DATENSCHUTZ'],
        'imprint.register' => ['group' => 'Impressum', 'label' => 'Handelsregister / UID', 'hint' => 'Leer, wenn keine vorhanden.', 'type' => 'text', 'max' => 100, 'env' => 'GLOSSARY_IMPRESSUM_UID'],
        'imprint.content' => ['group' => 'Impressum', 'label' => 'Verantwortlich für den Inhalt', 'hint' => 'Z. B. «Die Community des Glossary; verantwortlich für die Veröffentlichung: …».', 'type' => 'text', 'max' => 300, 'env' => 'GLOSSARY_IMPRESSUM_INHALT'],
    ];

    /** Muster für «Besitzer/Repository» (GitHub). */
    public const REPO_PATTERN = '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#';

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
            'links.source_url' => (string) config('glossary.source_url', ''),
            'dump.require_password' => (bool) config('glossary.dump_require_password', false),
            'metrics.token' => (string) config('glossary.metrics_token', ''),
            'sync.role' => (string) config('glossary.sync_role', 'instance'),
            'candidates.anonymous' => true,
            'mcp.enabled' => (bool) config('glossary.mcp_enabled', false),
            'candidates.blocklist' => '',
            'candidates.origins' => '',
            'imprint.operator' => (string) config('glossary.imprint.operator', ''),
            'imprint.address' => (string) config('glossary.imprint.address', ''),
            'imprint.email' => (string) config('glossary.imprint.email', ''),
            'imprint.privacy_email' => (string) config('glossary.imprint.privacy_email', ''),
            'imprint.register' => (string) config('glossary.imprint.register', ''),
            'imprint.content' => (string) config('glossary.imprint.content', ''),
            default => throw new InvalidArgumentException("Unbekannte Einstellung «{$key}»."),
        };
    }

    /** Definitionen beim Core-Baustein anmelden (AppServiceProvider); Vorgaben bleiben dynamisch (Konfiguration). */
    public static function register(CoreSettings $settings): void
    {
        foreach (self::DEFINITIONS as $key => $definition) {
            $settings->define($key, $definition + ['default' => fn (): mixed => self::default($key)]);
        }
    }

    public static function get(string $key): mixed
    {
        self::known($key);

        return self::core()->get($key);
    }

    /** Name der Umgebungsvariable, wenn sie gesetzt ist und die Einstellung sperrt; sonst null (#366). */
    public static function locked(string $key): ?string
    {
        self::known($key);

        return self::core()->locked($key);
    }

    /** Herkunft des geltenden Werts: Umgebung, Administration oder Vorgabe (#366). */
    public static function source(string $key): string
    {
        self::known($key);

        return match (self::core()->source($key)) {
            SettingSource::Environment => 'Umgebung',
            SettingSource::Administration => 'Administration',
            SettingSource::Default => 'Vorgabe',
        };
    }

    /**
     * Geheimnis setzen oder entfernen (null); verschlüsselt mit APP_KEY, im Protokoll nur «gesetzt» bzw. «entfernt».
     */
    public static function setSecret(User $actor, string $key, ?string $value): void
    {
        if ((self::DEFINITIONS[$key]['type'] ?? null) !== 'secret') {
            throw new InvalidArgumentException("Unbekanntes Geheimnis «{$key}».");
        }
        if (self::locked($key) !== null) {
            throw new InvalidArgumentException('Wird in der Umgebung gesetzt ('.self::locked($key).').');
        }
        $had = self::core()->source($key) === SettingSource::Administration;
        DB::transaction(function () use ($actor, $key, $value, $had): void {
            self::core()->setSecret($key, $value, $actor->id);
            Audit::log($actor, null, 'settings.changed', [
                'key' => $key, 'old' => $had ? 'gesetzt' : '–', 'new' => $value === null || $value === '' ? 'entfernt' : ($had ? 'erneuert' : 'gesetzt'),
            ]);
        });
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
     * Geheimnisse nur über setSecret(); gesperrte Werte kommen aus der Umgebung und werden übergangen.
     *
     * @param  array<string, mixed>  $values
     * @return list<string> geänderte Schlüssel
     */
    public static function set(User $actor, array $values): array
    {
        foreach (array_keys($values) as $key) {
            self::known($key);
        }
        // Rolle «Entwicklung» nie in production (#478, DEC-041 A12)
        if (($values['sync.role'] ?? null) === 'development' && self::locked('sync.role') === null && app()->environment('production')) {
            throw new InvalidArgumentException('«Entwicklung» ist mit APP_ENV=production nicht erlaubt.');
        }

        return DB::transaction(function () use ($actor, $values): array {
            $old = [];
            foreach (array_keys($values) as $key) {
                $old[$key] = self::DEFINITIONS[$key]['type'] === 'secret' ? null : self::get($key);
            }
            $changed = self::core()->set($values, $actor->id);
            foreach ($changed as $key) {
                Audit::log($actor, null, 'settings.changed', ['key' => $key, 'old' => $old[$key], 'new' => self::get($key)]);
            }

            return $changed;
        });
    }

    /** Zwischenspeicher leeren (Tests, nach Änderungen). */
    public static function flush(): void
    {
        self::core()->flush();
    }

    private static function core(): CoreSettings
    {
        return app(CoreSettings::class);
    }

    private static function known(string $key): void
    {
        if (! isset(self::DEFINITIONS[$key])) {
            throw new InvalidArgumentException("Unbekannte Einstellung «{$key}».");
        }
    }
}
