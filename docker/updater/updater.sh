#!/bin/sh
# Update-Dienst des Glossary (#177, DEC-027 U3–U9). Läuft als eigener Container neben der App und hat als Einziger
# Zugriff auf Docker. Die App legt nur einen Auftrag ab (storage/app/updates/auftrag.json); dieser Dienst
#   1. meldet sich jede Minute (stand.json, «gemeldet»),
#   2. übernimmt einen Auftrag: Image der Zielversion holen, App neu starten (das Startskript sichert die Datenbank
#      vor den Migrationen), /up prüfen,
#   3. springt bei einem Fehler zurück: vorheriges Image und die Sicherung von unmittelbar davor,
#   4. schreibt das Ergebnis nach stand.json und protokoll.jsonl.
# Erlaubt sind nur Versionen X.Y.Z aus der eigenen Registry; der Dienst ändert nur die Dienste app, worker, scheduler.
set -eu

DIR="${GLOSSARY_DIR:?GLOSSARY_DIR fehlt}"
DATA="${GLOSSARY_DATA:-/data}"
JOBS="$DATA/app/updates"
REGISTRY="${GLOSSARY_REGISTRY:-ghcr.io/markuskeller8200/eduksx-glossary}"
DB="app/database/glossary.sqlite"

cd "$DIR"
mkdir -p "$JOBS"

jetzt() { date -u +%Y-%m-%dT%H:%M:%SZ; }

stand() { # status meldung [von nach]
    printf '{"gemeldet":"%s","status":"%s","meldung":"%s","von":"%s","nach":"%s"}\n' \
        "$(jetzt)" "$1" "$2" "${3:-}" "${4:-}" > "$JOBS/stand.json.tmp"
    mv "$JOBS/stand.json.tmp" "$JOBS/stand.json"
    chmod 644 "$JOBS/stand.json"
}

protokoll() { # ergebnis von nach start meldung angefordert
    printf '{"ergebnis":"%s","von":"%s","nach":"%s","start":"%s","ende":"%s","meldung":"%s","angefordert_von":"%s"}\n' \
        "$1" "$2" "$3" "$4" "$(jetzt)" "$5" "$6" >> "$JOBS/protokoll.jsonl"
    chmod 644 "$JOBS/protokoll.jsonl"
}

feld() { # Wert eines Felds aus auftrag.json (ohne jq)
    sed -n "s/.*\"$1\": *\"\([^\"]*\)\".*/\1/p" "$JOBS/auftrag.json" | head -n 1
}

image_setzen() {
    sed -i '/^GLOSSARY_IMAGE=/d' .env
    printf 'GLOSSARY_IMAGE=%s\n' "$1" >> .env
}

gesund() {
    i=0
    while [ "$i" -lt 36 ]; do
        if docker compose exec -T app php -r 'exit(@file_get_contents("http://127.0.0.1/up") === false ? 1 : 0);' 2>/dev/null; then
            return 0
        fi
        i=$((i + 1))
        sleep 5
    done
    return 1
}

ausfuehren() {
    ziel="$(feld ziel)"
    wer="$(feld angefordert_von)"
    start="$(jetzt)"
    rm -f "$JOBS/auftrag.json"
    case "$ziel" in
        [0-9]*.[0-9]*.[0-9]*) ;;
        *) protokoll fehler "" "$ziel" "$start" "Zielversion ungültig" "$wer"; return ;;
    esac
    alt="$(sed -n 's/^GLOSSARY_IMAGE=//p' .env | tail -n 1)"
    alt="${alt:-$REGISTRY:latest}"
    neu="$REGISTRY:$ziel"
    stand laeuft "Image $neu wird geladen" "$alt" "$neu"
    if ! GLOSSARY_IMAGE="$neu" docker compose pull --quiet app; then
        stand bereit "Image $neu nicht ladbar" "$alt" "$neu"
        protokoll fehler "$alt" "$neu" "$start" "Image nicht ladbar" "$wer"
        return
    fi
    seit="$(date +%s)"
    image_setzen "$neu"
    stand laeuft "Neustart mit $neu" "$alt" "$neu"
    docker compose up -d --no-deps app worker scheduler
    if gesund; then
        docker image prune -f > /dev/null 2>&1 || true
        stand bereit "Aktualisiert auf $neu" "$alt" "$neu"
        protokoll fertig "$alt" "$neu" "$start" "ok" "$wer"
        return
    fi
    # Rücksprung: vorheriges Image und die Sicherung, die das Startskript unmittelbar vor den Migrationen angelegt hat
    stand laeuft "Prüfung fehlgeschlagen – Rücksprung auf $alt" "$alt" "$neu"
    docker compose stop app worker scheduler
    sicherung=""
    for f in $(ls -1t "$DATA"/app/backups/vor-update-*.sqlite 2>/dev/null); do
        if [ "$(stat -c %Y "$f")" -ge "$seit" ]; then sicherung="$f"; break; fi
    done
    if [ -n "$sicherung" ]; then
        cp "$sicherung" "$DATA/$DB"
        rm -f "$DATA/$DB-wal" "$DATA/$DB-shm"
        chown 33:33 "$DATA/$DB" 2>/dev/null || true
    fi
    image_setzen "$alt"
    docker compose up -d --no-deps app worker scheduler
    if gesund; then
        stand bereit "Zurück auf $alt (Update auf $neu fehlgeschlagen)" "$alt" "$neu"
        protokoll zurueck "$alt" "$neu" "$start" "Prüfung fehlgeschlagen, vorheriger Stand wiederhergestellt" "$wer"
    else
        stand fehler "Auch der vorherige Stand startet nicht – bitte von Hand prüfen" "$alt" "$neu"
        protokoll fehler "$alt" "$neu" "$start" "Rücksprung fehlgeschlagen" "$wer"
    fi
}

docker compose version > /dev/null 2>&1 || { echo "[updater] docker compose fehlt im Image" >&2; exit 1; }
echo "[updater] bereit für $DIR"
while true; do
    if [ -f "$JOBS/auftrag.json" ]; then
        ausfuehren || stand fehler "Unerwarteter Fehler im Update-Dienst"
    else
        stand bereit "wartet auf Aufträge"
    fi
    sleep 60
done
