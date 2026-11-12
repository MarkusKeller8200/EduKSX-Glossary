#!/bin/sh
# Sicherung ausser Haus (#197, DEC-028 D6): täglich um ${SICHERUNG_UHRZEIT} die Datenbank-Kopie
# (storage/app/backups/taeglich.sqlite, erzeugt von glossary:sicherung) und den Schlüssel (storage/app/.app_key) mit
# restic verschlüsselt sichern. Ziel: S3-kompatibel (Infomaniak Object Storage, Swiss Backup S3, MinIO, NAS) oder ein
# Ordner (z. B. eingebundenes NAS). Aufbewahrung ${SICHERUNG_TAGE} Tage (Löschkonzept: 30). Am 1. des Monats
# Wiederherstellungstest: letzte Sicherung zurückholen und prüfen. Ergebnis in storage/app/backups/sicherung.json.
set -eu

DATA=/data
STAND="$DATA/app/backups/sicherung.json"
UHRZEIT="${SICHERUNG_UHRZEIT:-02:30}"
TAGE="${SICHERUNG_TAGE:-30}"

: "${RESTIC_REPOSITORY:?RESTIC_REPOSITORY fehlt (Ziel der Sicherung)}"
if [ -z "${RESTIC_PASSWORD:-}" ] && [ -z "${RESTIC_PASSWORD_FILE:-}" ]; then
    echo "[backup] RESTIC_PASSWORD bzw. RESTIC_PASSWORD_FILE fehlt" >&2
    exit 1
fi

melden() { # ergebnis meldung [test]
    printf '{"am":"%s","ergebnis":"%s","meldung":"%s","test":"%s","ziel":"%s"}\n' \
        "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$1" "$2" "${3:-}" "$(echo "$RESTIC_REPOSITORY" | sed 's#//[^@]*@#//***@#')" > "$STAND.tmp"
    mv "$STAND.tmp" "$STAND"
    chmod 644 "$STAND"
}

sichern() {
    restic snapshots > /dev/null 2>&1 || restic init
    test -f "$DATA/app/backups/taeglich.sqlite" || { melden fehler "Keine Datenbank-Kopie (glossary:sicherung läuft im Dienst scheduler)"; return 1; }
    restic backup --tag glossary --host glossary "$DATA/app/backups/taeglich.sqlite" "$DATA/app/.app_key"
    restic forget --tag glossary --keep-within "${TAGE}d" --prune
    test=""
    if [ "$(date +%d)" = "01" ] || [ "${SICHERUNG_TEST:-}" = "jetzt" ]; then
        rm -rf /tmp/wiederherstellung
        restic restore latest --tag glossary --target /tmp/wiederherstellung
        datei="$(find /tmp/wiederherstellung -name taeglich.sqlite | head -n 1)"
        if [ -n "$datei" ] && head -c 15 "$datei" | grep -q 'SQLite format 3'; then
            restic check --read-data-subset=5%
            test="ok"
        else
            test="fehler"
        fi
        rm -rf /tmp/wiederherstellung
    fi
    if [ "$test" = "fehler" ]; then
        melden fehler "Wiederherstellungstest fehlgeschlagen" "$test"
        return 1
    fi
    melden ok "Sicherung erstellt, Aufbewahrung ${TAGE} Tage" "$test"
}

echo "[backup] täglich um $UHRZEIT nach $(echo "$RESTIC_REPOSITORY" | sed 's#//[^@]*@#//***@#')"
if [ "${1:-}" = "jetzt" ]; then
    sichern
    exit $?
fi
while true; do
    if [ "$(date +%H:%M)" = "$UHRZEIT" ]; then
        sichern || echo "[backup] Sicherung fehlgeschlagen" >&2
        sleep 61
    fi
    sleep 30
done
