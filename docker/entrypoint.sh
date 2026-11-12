#!/bin/sh
# Startskript des Containers (aus sek-intranet übernommen). Läuft bei jedem Start, auch nach jedem Deployment.
set -e
cd /var/www/html

# storage/ ist ein Docker-Volume: Ordner sicherstellen (bleiben über Deployments erhalten).
mkdir -p storage/app/database storage/app/private storage/app/public storage/app/backups \
    storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs

# Geheimnisse aus Dateien (#196): NAME_FILE=/pfad setzt NAME auf den Inhalt der Datei (Docker/Kubernetes Secrets),
# z. B. APP_KEY_FILE, MAIL_PASSWORD_FILE. Der Wert erscheint nie in der Ausgabe.
for name in $(env | sed -n 's/^\([A-Z0-9_]*\)_FILE=.*/\1/p'); do
    file="$(printenv "${name}_FILE")"
    if [ -r "$file" ]; then
        value="$(cat "$file")"
        export "$name=$value"
    else
        echo "[glossary] ${name}_FILE: Datei nicht lesbar – ${name} bleibt ungesetzt." >&2
    fi
done

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

chown -R www-data:www-data storage bootstrap/cache

# Hinweis: Admin-Konto einmalig anlegen mit
#   docker compose exec app php artisan glossary:admin
exec "$@"
