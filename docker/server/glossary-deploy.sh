#!/bin/sh
# Erzwungener Befehl für den Deploy-Schlüssel des Glossary (#195, docs/KONZEPT-DEPLOYMENT.md).
# Der Schlüssel in GitHub kann damit nur diese Befehle ausführen – keine Shell, kein Bauen, kein anderes Image.
#
# Einrichtung (einmalig als root, Anleitung in DEPLOY.md):
#   install -m 755 -o root -g root glossary-deploy.sh /usr/local/bin/glossary-deploy
#   in /home/deploy/.ssh/authorized_keys vor dem Glossary-Schlüssel:
#   command="/usr/local/bin/glossary-deploy",no-port-forwarding,no-agent-forwarding,no-X11-forwarding,no-pty ssh-ed25519 …
#
# Befehle (über SSH_ORIGINAL_COMMAND bzw. als Argumente):
#   dateien                      compose-Dateien als tar von stdin übernehmen (nur compose*.yaml)
#   ausrollen <image> <adresse>  Image holen, starten, /up prüfen; sonst zurück auf das vorherige Image
#   status | logs                Zustand bzw. die letzten Zeilen des App-Protokolls
set -eu
set -f

TARGET="${GLOSSARY_TARGET:-/docker/glossary}"
REGISTRY="ghcr.io/markuskeller8200/eduksx-glossary"
PROXY_DATEI="${GLOSSARY_PROXY_DATEI:-compose.traefik.yaml}"

# shellcheck disable=SC2086
set -- ${SSH_ORIGINAL_COMMAND:-$*}
if [ "${1:-}" = "glossary-deploy" ]; then
    shift
fi

cd "$TARGET"
touch .env
chmod 600 .env

setze() {
    sed -i "/^$1=/d" .env
    printf '%s=%s\n' "$1" "$2" >> .env
}

pruefen() {
    i=0
    while [ "$i" -lt 36 ]; do
        if curl -fsS -o /dev/null --max-time 10 --resolve "$1:443:127.0.0.1" "https://$1/up" 2>/dev/null; then
            return 0
        fi
        i=$((i + 1))
        sleep 5
    done
    return 1
}

case "${1:-}" in
    dateien)
        tmp="$(mktemp -d)"
        trap 'rm -rf "$tmp"' EXIT
        tar -x -C "$tmp" -f - --no-same-owner
        for f in compose.yaml compose.traefik.yaml compose.caddy.yaml; do
            if [ -f "$tmp/$f" ]; then
                install -m 644 "$tmp/$f" "$TARGET/$f"
            fi
        done
        echo "compose-Dateien übernommen"
        ;;
    ausrollen)
        image="${2:-}"
        host="${3:-}"
        case "$image" in
            "$REGISTRY":[a-z0-9]*) ;;
            *) echo "Image nicht erlaubt: $image" >&2; exit 2 ;;
        esac
        case "$host" in
            "" | *[!a-z0-9.-]*) echo "Adresse ungültig: $host" >&2; exit 2 ;;
        esac
        alt="$(sed -n 's/^GLOSSARY_IMAGE=//p' .env | tail -n 1)"
        setze GLOSSARY_HOST "$host"
        setze GLOSSARY_IMAGE "$image"
        grep -q '^COMPOSE_FILE=' .env || setze COMPOSE_FILE "compose.yaml:$PROXY_DATEI"
        # Stand kommt aus dem Image; alte Werte aus dem Bauen auf dem Server entfernen
        sed -i '/^GLOSSARY_BUILD/d' .env
        if ! docker compose pull --quiet; then
            echo "Image nicht ladbar: $image – ist das Paket öffentlich (DEPLOY.md, «Fertiges Image»)?" >&2
            setze GLOSSARY_IMAGE "${alt:-$image}"
            exit 1
        fi
        docker compose up -d --remove-orphans
        if pruefen "$host"; then
            docker image prune -f > /dev/null
            echo "Online: https://$host ($image)"
        else
            echo "Prüfung von https://$host/up fehlgeschlagen" >&2
            docker compose logs --tail=60 app >&2 || true
            if [ -n "$alt" ] && [ "$alt" != "$image" ]; then
                echo "Zurück auf $alt" >&2
                setze GLOSSARY_IMAGE "$alt"
                docker compose up -d --remove-orphans
                pruefen "$host" && echo "Vorheriger Stand läuft wieder: $alt" >&2
            fi
            exit 1
        fi
        ;;
    status)
        docker compose ps
        ;;
    logs)
        docker compose logs --tail=80 app
        ;;
    *)
        echo "Erlaubt: dateien | ausrollen <image> <adresse> | status | logs" >&2
        exit 2
        ;;
esac
