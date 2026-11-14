#!/bin/sh
# Startskript des Containers (aus sek-intranet übernommen). Läuft bei jedem Start, auch nach jedem Deployment.
set -e
cd /var/www/html

# storage/ ist ein Docker-Volume: Ordner sicherstellen (bleiben über Deployments erhalten).
mkdir -p storage/app/database storage/app/private storage/app/public storage/app/backups \
    storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs

# Geheimnisse aus Dateien (#196): NAME_FILE=/pfad setzt NAME auf den Inhalt der Datei (Docker/Kubernetes Secrets),
# z. B. APP_KEY_FILE, MAIL_PASSWORD_FILE. Der Wert erscheint nie in der Ausgabe.
# COMPOSE_FILE (Liste der compose-Dateien aus .env) ist kein Geheimnis und bleibt unberührt.
for name in $(env | sed -n 's/^\([A-Z0-9_]*\)_FILE=.*/\1/p' | grep -vx COMPOSE); do
    file="$(printenv "${name}_FILE")"
    if [ -r "$file" ]; then
        value="$(cat "$file")"
        export "$name=$value"
    else
        echo "[glossary] ${name}_FILE: Datei nicht lesbar – ${name} bleibt ungesetzt." >&2
    fi
done

# Eigene Zertifizierungsstelle (#199): Zertifikate in /etc/glossary/ca/*.crt zusätzlich vertrauen. Funktioniert auch
# für worker/scheduler (ohne root): eigenes Bündel und PHP-Einstellung in /tmp.
if ls /etc/glossary/ca/*.crt > /dev/null 2>&1; then
    mkdir -p /tmp/glossary-ini
    cat /etc/ssl/certs/ca-certificates.crt /etc/glossary/ca/*.crt > /tmp/glossary-ca.pem
    printf 'curl.cainfo=/tmp/glossary-ca.pem\nopenssl.cafile=/tmp/glossary-ca.pem\n' > /tmp/glossary-ini/zz-ca.ini
    export PHP_INI_SCAN_DIR="${PHP_INI_SCAN_DIR:-$PHP_INI_DIR/conf.d}:/tmp/glossary-ini"
    export SSL_CERT_FILE=/tmp/glossary-ca.pem
    echo "[glossary] Eigene Zertifizierungsstelle aktiv ($(ls /etc/glossary/ca/*.crt | wc -l) Datei(en))."
fi

# APP_KEY: aus der Umgebung oder einmalig erzeugen und im Volume speichern.
if [ -z "${APP_KEY:-}" ]; then
    if [ ! -s storage/app/.app_key ]; then
        php -r 'echo "base64:".base64_encode(random_bytes(32));' > storage/app/.app_key
        echo "[glossary] Neuer APP_KEY erzeugt (storage/app/.app_key)."
    fi
    APP_KEY="$(cat storage/app/.app_key)"
    export APP_KEY
fi

# Rolle des Containers: «web» (Standard) sichert und migriert; «worker»/«scheduler» nur Konfiguration (#40)
ROLE="${GLOSSARY_ROLE:-web}"
if [ "$ROLE" != "web" ]; then
    php artisan optimize --no-ansi
    exec "$@"
fi

if [ "${DB_CONNECTION:-sqlite}" = "pgsql" ]; then
    # PostgreSQL (#357): warten, bis die Datenbank Verbindungen annimmt (höchstens 2 Minuten)
    i=0
    until php -r '
        $dsn = sprintf("pgsql:host=%s;port=%s;dbname=%s", getenv("DB_HOST") ?: "127.0.0.1", getenv("DB_PORT") ?: "5432", getenv("DB_DATABASE") ?: "glossary");
        try { new PDO($dsn, getenv("DB_USERNAME") ?: "", getenv("DB_PASSWORD") ?: "", [PDO::ATTR_TIMEOUT => 3]); } catch (Throwable $e) { exit(1); }
    ' 2> /dev/null; do
        i=$((i + 1))
        [ "$i" -lt 60 ] || { echo "[glossary] PostgreSQL nicht erreichbar (${DB_HOST:-127.0.0.1}:${DB_PORT:-5432})." >&2; exit 1; }
        sleep 2
    done
    php artisan optimize --no-ansi
    # vor dem Umbau ein Dump (Format glossary:dump, einspielen mit glossary:wiederherstellen); nur die letzten 10
    if php artisan migrate:status --no-ansi > /dev/null 2>&1; then
        php artisan glossary:dump "storage/app/backups/vor-update-$(date -u +%Y%m%dT%H%M%SZ).zip" --no-ansi > /dev/null \
            || echo "[glossary] Sicherung fehlgeschlagen"
        ls -1t storage/app/backups/vor-update-*.zip 2>/dev/null | tail -n +11 | xargs -r rm -f
    fi
    php artisan migrate --force --no-ansi
    # bestehende SQLite-Datenbank einmalig übernehmen; schlägt das fehl, startet der Container nicht
    php artisan glossary:postgres-uebernahme --no-ansi
else
    # SQLite-Datei anlegen, falls neu; vor dem Umbau eine Sicherung (eine Datei, konsistent über sqlite3 .backup).
    DB="${DB_DATABASE:-storage/app/database/glossary.sqlite}"
    if [ -s "$DB" ]; then
        sqlite3 "$DB" ".backup 'storage/app/backups/vor-update-$(date -u +%Y%m%dT%H%M%SZ).sqlite'" || echo "[glossary] Sicherung fehlgeschlagen"
        # nur die letzten 10 Sicherungen behalten
        ls -1t storage/app/backups/vor-update-*.sqlite 2>/dev/null | tail -n +11 | xargs -r rm -f
    else
        touch "$DB"
    fi

    # Konfiguration, Routen und Views für Produktion vorbereiten; Datenbank auf den Stand bringen.
    php artisan optimize --no-ansi
    php artisan migrate --force --no-ansi
fi

chown -R www-data:www-data storage bootstrap/cache

# Hinweis: Admin-Konto einmalig anlegen mit
#   docker compose exec app php artisan glossary:admin
exec "$@"
