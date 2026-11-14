<?php

namespace App\Support;

/**
 * Werte aus der Umgebung (`.env` bzw. compose) zum Ansehen in der Administration (#366, Recht «system»). Bewusst nicht
 * änderbar: Der Container kann und soll die `.env` des Servers nicht schreiben. Geheimnisse nur «gesetzt / nicht
 * gesetzt». Wirksam erst nach `docker compose up -d`.
 */
final class EnvironmentOverview
{
    /** @var list<array{0: string, 1: string, 2: bool, 3: string}> Name, Gruppe, geheim, Bedeutung */
    private const ITEMS = [
        ['GLOSSARY_HOST', 'Anwendung', false, 'Adresse der Instanz (APP_URL wird daraus gebildet)'],
        ['APP_ENV', 'Anwendung', false, 'Umgebung (production, local)'],
        ['APP_DEBUG', 'Anwendung', false, 'Fehlerdetails anzeigen – im Betrieb immer false'],
        ['APP_KEY', 'Anwendung', true, 'Schlüssel für Verschlüsselung und Sitzungen; sonst storage/app/.app_key'],
        ['GLOSSARY_IMAGE', 'Anwendung', false, 'Image und Version des Containers'],
        ['GLOSSARY_REDIRECT_HOST', 'Anwendung', false, 'frühere Adresse, die weiterleitet'],
        ['DB_CONNECTION', 'Datenbank', false, 'sqlite oder pgsql'],
        ['DB_HOST', 'Datenbank', false, 'Server von PostgreSQL'],
        ['DB_DATABASE', 'Datenbank', false, 'Datei (SQLite) bzw. Name (PostgreSQL)'],
        ['DB_SCHEMA', 'Datenbank', false, 'Schema auf PostgreSQL'],
        ['DB_USERNAME', 'Datenbank', false, 'Zugang zu PostgreSQL'],
        ['DB_PASSWORD', 'Datenbank', true, 'Passwort des Zugangs (bzw. DB_PASSWORD_FILE)'],
        ['MAIL_MAILER', 'Mail', false, 'gesetzt = Versand nur über die Umgebung, Formular gesperrt'],
        ['MAIL_HOST', 'Mail', false, 'SMTP-Server'],
        ['MAIL_PASSWORD', 'Mail', true, 'Passwort des SMTP-Zugangs'],
        ['QUEUE_CONNECTION', 'Betrieb', false, 'Warteschlange'],
        ['CACHE_STORE', 'Betrieb', false, 'Zwischenspeicher'],
        ['SESSION_DRIVER', 'Betrieb', false, 'Sitzungen'],
        ['LOG_CHANNEL', 'Betrieb', false, 'Ziel der Protokolle'],
        ['LOG_FORMAT', 'Betrieb', false, 'json = eine Zeile JSON je Eintrag'],
        ['LOG_LEVEL', 'Betrieb', false, 'Stufe der Protokolle'],
        ['GLOSSARY_DEV_BACKUPS', 'Betrieb', false, 'Sicherung (Restore) in der Administration – nur auf dev'],
        ['GLOSSARY_DUMP_PASSWORD', 'Betrieb', true, 'Passwort für glossary:dump ohne Eingabe'],
        ['GLOSSARY_DUMP_REQUIRE_PASSWORD', 'Betrieb', false, 'Dumps nur mit Passwort (sperrt die Einstellung)'],
        ['GLOSSARY_METRICS_TOKEN', 'Betrieb', true, 'Token für /metrics (sperrt die Einstellung)'],
        ['GLOSSARY_SOURCE_URL', 'Betrieb', false, 'Quellcode-Link (sperrt die Einstellung)'],
        ['GLOSSARY_UPDATE_KEY', 'Betrieb', true, 'Schlüssel des Update-Dienstes'],
        ['EDUKSX_EVENTS_KEYS', 'Plattform', true, 'Schlüssel für Ereignisse von EduKSX-Ident'],
        ['EDUKSX_EVENTS_SOURCES', 'Plattform', false, 'erlaubte Absender der Ereignisse'],
        ['MICROSOFT_CLIENT_ID', 'Anmeldung', false, 'Anmeldung mit Microsoft'],
        ['MICROSOFT_CLIENT_SECRET', 'Anmeldung', true, 'Geheimnis der Anmeldung mit Microsoft'],
    ];

    /**
     * @return list<array{name: string, gruppe: string, geheim: bool, bedeutung: string, gesetzt: bool, wert: string|null}>
     */
    public static function items(): array
    {
        $out = [];
        foreach (self::ITEMS as [$name, $group, $secret, $meaning]) {
            $value = getenv($name);
            if ($value === false && getenv($name.'_FILE') !== false) {
                $value = '(aus Datei '.getenv($name.'_FILE').')';
                $secret = false;   // nur der Pfad wird angezeigt, nicht der Inhalt
            }
            $set = $value !== false && $value !== '';
            $out[] = [
                'name' => $name,
                'gruppe' => $group,
                'geheim' => $secret,
                'bedeutung' => $meaning,
                'gesetzt' => $set,
                'wert' => $set && ! $secret ? (string) $value : null,
            ];
        }

        return $out;
    }

    /** Ordner der Installation für den Hinweis «so ändern» */
    public static function directory(): string
    {
        $dir = getenv('GLOSSARY_DIR');

        return is_string($dir) && $dir !== '' ? $dir : '/docker/glossary';
    }
}
