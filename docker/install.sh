#!/bin/sh
# Installationsskript EduKSX-Glossary (#198, DEC-028): richtet eine Instanz mit dem fertigen Image ein – auf einem
# beliebigen Linux-Server mit Docker (zugesichert: Ubuntu 24.04/26.04, Debian 12/13).
#
#   curl -fsSLO https://raw.githubusercontent.com/MarkusKeller8200/EduKSX-Glossary/main/docker/install.sh
#   less install.sh          # ansehen
#   sudo sh install.sh
#
# Fragt Ordner, Adresse, Proxy, Datenbank (SQLite oder PostgreSQL), Update-Dienst und Sicherung; schreibt .env, startet,
# prüft und legt den ersten Admin an. Ein zweiter Lauf ändert eine bestehende Installation nicht ungefragt; er bietet an,
# eine Installation mit SQLite auf PostgreSQL umzustellen (Daten werden beim Start übernommen, #357).
set -eu

REPO="MarkusKeller8200/EduKSX-Glossary"
REGISTRY="ghcr.io/markuskeller8200/eduksx-glossary"

frage() { # text vorgabe → Antwort
    printf '%s [%s]: ' "$1" "$2" > /dev/tty
    read -r antwort < /dev/tty || antwort=""
    echo "${antwort:-$2}"
}
ja() { # text vorgabe(j/n)
    a="$(frage "$1 (j/n)" "$2")"
    case "$a" in j|J|ja|Ja|y|Y) return 0 ;; *) return 1 ;; esac
}
geheimnis() { # datei – zufälliges Passwort anlegen, falls es fehlt (lesbar für die Container, Ordner nur root)
    mkdir -p secrets
    chmod 700 secrets
    if [ ! -s "secrets/$1" ]; then
        head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "secrets/$1"
        chmod 644 "secrets/$1"
    fi
}
postgres_einrichten() {
    geheimnis db_password
    geheimnis db_admin_password
}
holen() { # pfad-im-repo ziel
    curl -fsSL "https://raw.githubusercontent.com/$REPO/$REF/$1" -o "$2" \
        || { echo "Datei $1 fehlt in Version $REF (Selbstinstallation ab Version 0.10.0)."; exit 1; }
}

echo "== EduKSX-Glossary installieren"
[ "$(id -u)" -eq 0 ] || { echo "Bitte als root bzw. mit sudo ausführen."; exit 1; }
command -v docker > /dev/null || { echo "Docker fehlt. Anleitung: https://docs.docker.com/engine/install/"; exit 1; }
docker compose version > /dev/null 2>&1 || { echo "Docker Compose (Plugin) fehlt."; exit 1; }
command -v curl > /dev/null || { echo "curl fehlt."; exit 1; }
if [ -r /etc/os-release ]; then
    . /etc/os-release
    case "${ID:-}-${VERSION_ID:-}" in
        ubuntu-24.04|ubuntu-26.04|debian-12|debian-13) ;;
        *) echo "Hinweis: ${PRETTY_NAME:-unbekanntes System} ist nicht zugesichert (Ubuntu 24.04/26.04, Debian 12/13)." ;;
    esac
fi

# Neueste Version (Release-Tag) – Dateien und Image passen damit zusammen
VERSION="$(curl -fsSL "https://api.github.com/repos/$REPO/releases/latest" | sed -n 's/.*"tag_name": *"v\([0-9.]*\)".*/\1/p' | head -n 1)"
[ -n "$VERSION" ] || { echo "Neueste Version nicht ermittelbar (Internet bzw. Proxy prüfen)."; exit 1; }
REF="v$VERSION"
echo "Version $VERSION"

DIR="$(frage "Ordner der Installation" /opt/glossary)"
mkdir -p "$DIR"
cd "$DIR"

if [ -f .env ]; then
    echo "In $DIR gibt es bereits eine Installation (.env)."
    if ! ja "Nur Dateien aktualisieren und neu starten, Einstellungen behalten?" j; then
        echo "Abgebrochen – nichts geändert."
        exit 0
    fi
    NEU=0
else
    NEU=1
fi

echo "Dateien holen …"
for f in compose.yaml compose.proxy.yaml compose.traefik.yaml compose.schulnetz.yaml compose.backup.yaml compose.updater.yaml compose.postgres.yaml; do
    holen "docker/$f" "$f"
done
mkdir -p backup postgres
holen docker/postgres/init.sh postgres/init.sh
holen docker/updater/updater.sh updater.sh
holen docker/backup/sichern.sh backup/sichern.sh

if [ "$NEU" -eq 1 ]; then
    HOST="$(frage "Adresse (ohne https://), DNS muss auf diesen Server zeigen" glossary.example.ch)"
    echo "Proxy: 1 = Caddy mit eigenem Zertifikat (Standard), 2 = vorhandener Traefik, 3 = eigener Proxy (nginx/Apache, Port 8080)"
    P="$(frage "Proxy" 1)"
    case "$P" in
        2) FILES="compose.yaml:compose.traefik.yaml" ;;
        3) FILES="compose.yaml" ;;
        *) FILES="compose.yaml:compose.proxy.yaml"
           for port in 80 443; do
               if command -v ss > /dev/null && ss -ltn "sport = :$port" | grep -q LISTEN; then
                   echo "Port $port ist belegt – Caddy kann nicht starten. Anderen Proxy wählen oder Port freigeben."; exit 1
               fi
           done ;;
    esac
    echo "Datenbank: 1 = SQLite (Standard, eine Datei, für wenige Benutzer), 2 = PostgreSQL im Stack"
    if [ "$(frage "Datenbank" 1)" = "2" ]; then
        FILES="compose.yaml:compose.postgres.yaml${FILES#compose.yaml}"
        postgres_einrichten
    fi
    if ja "Schulnetz: ausgehender Proxy oder eigene Zertifizierungsstelle?" n; then
        FILES="$FILES:compose.schulnetz.yaml"
        mkdir -p ca
        PROXY_URL="$(frage "Ausgehender Proxy (leer = keiner)" "")"
        echo "Zertifikate der Schule (.crt) später in $DIR/ca ablegen und neu starten."
    fi
    if ja "Update-Dienst einrichten (Updates aus der Administration, automatische Korrekturen)?" j; then
        FILES="$FILES:compose.updater.yaml"
    fi
    BACKUP=0
    if ja "Sicherung ausser Haus einrichten (restic, S3 oder Ordner)?" n; then
        FILES="$FILES:compose.backup.yaml"
        BACKUP=1
        ZIEL="$(frage "Ziel (z. B. s3:https://s3.example.ch/bucket/glossary oder /sicherung)" /sicherung)"
    fi

    umask 077
    {
        echo "GLOSSARY_HOST=$HOST"
        echo "GLOSSARY_IMAGE=$REGISTRY:$VERSION"
        echo "GLOSSARY_DIR=$DIR"
        echo "COMPOSE_FILE=$FILES"
        [ -n "${PROXY_URL:-}" ] && echo "HTTPS_PROXY=$PROXY_URL" && echo "HTTP_PROXY=$PROXY_URL"
        if [ "$BACKUP" -eq 1 ]; then
            echo "RESTIC_REPOSITORY=$ZIEL"
            echo "RESTIC_PASSWORD=$(head -c 32 /dev/urandom | base64 | tr -d '/+=' | head -c 40)"
            echo "# bei S3: AWS_ACCESS_KEY_ID=… und AWS_SECRET_ACCESS_KEY=… ergänzen"
        fi
        echo "# Mail: in der Administration unter System → «Versand einrichten» (oder MAIL_* hier)"
    } > .env
    chmod 600 .env
    [ "$BACKUP" -eq 1 ] && echo "WICHTIG: RESTIC_PASSWORD in $DIR/.env sicher ausserhalb des Servers aufbewahren – ohne ihn keine Wiederherstellung."
fi

if [ "$NEU" -eq 0 ] && ! grep -q '^COMPOSE_FILE=.*compose.postgres.yaml' .env; then
    if ja "Auf PostgreSQL umstellen? Die Daten aus SQLite werden beim Start übernommen (SQLite-Datei bleibt als Rückfall)." n; then
        postgres_einrichten
        if grep -q '^COMPOSE_FILE=' .env; then
            sed -i 's#^COMPOSE_FILE=compose.yaml#COMPOSE_FILE=compose.yaml:compose.postgres.yaml#' .env
        else
            echo "COMPOSE_FILE=compose.yaml:compose.postgres.yaml" >> .env
        fi
        echo "PostgreSQL eingerichtet; Passwörter in $DIR/secrets (nur root)."
    fi
fi

echo "Starten …"
docker compose pull --quiet
docker compose up -d
i=0
until docker compose exec -T app php -r 'exit(@file_get_contents("http://127.0.0.1/up") === false ? 1 : 0);' 2> /dev/null; do
    i=$((i + 1)); [ "$i" -lt 36 ] || { echo "Start fehlgeschlagen:"; docker compose logs --tail=40 app; exit 1; }
    sleep 5
done
docker compose exec -T app php artisan glossary:pruefen || true

if [ "$NEU" -eq 1 ] && ja "Ersten Admin jetzt anlegen?" j; then
    docker compose exec app php artisan glossary:admin
fi
echo "Fertig: https://$(sed -n 's/^GLOSSARY_HOST=//p' .env) – Anmeldung unter /anmelden, danach zweiten Faktor einrichten."
