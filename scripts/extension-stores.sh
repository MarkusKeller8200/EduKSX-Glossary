#!/usr/bin/env bash
# Browsererweiterung in die Stores laden (#386). Aufruf aus .github/workflows/extension.yml (Job «stores»):
#
#   scripts/extension-stores.sh <erweiterung.zip> <quellcode.zip>
#
# Jeder Store läuft nur, wenn seine Zugangsdaten gesetzt sind (Umgebungsvariablen, siehe extension/store/README.md);
# sonst wird er mit einem Hinweis übersprungen. Ein Fehler in einem Store bricht die anderen nicht ab, der Lauf endet
# aber mit Fehler. Zugangsdaten werden nie ausgegeben.
set -uo pipefail

ZIP="${1:?ZIP der Erweiterung fehlt}"
SOURCE="${2:?Quellcode-Archiv fehlt}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
fehler=0

hinweis() { echo "::notice title=$1::$2"; }
fehlschlag() { echo "::error title=$1::$2"; fehler=1; }

# HTTP-Aufruf: gibt den Körper aus, endet mit Fehler bei Status >= 400 (ohne Kopfzeilen, ohne Zugangsdaten)
http() {
    local out status
    out=$(mktemp)
    status=$(curl -sS -o "$out" -w '%{http_code}' "$@") || { rm -f "$out"; return 1; }
    cat "$out"
    rm -f "$out"
    [ "$status" -lt 400 ] || { echo " (HTTP $status)" >&2; return 1; }
}

chrome() {
    if [ -z "${CHROME_CLIENT_ID:-}" ] || [ -z "${CHROME_CLIENT_SECRET:-}" ] || [ -z "${CHROME_REFRESH_TOKEN:-}" ] \
        || [ -z "${CHROME_PUBLISHER_ID:-}" ] || [ -z "${CHROME_EXTENSION_ID:-}" ]; then
        hinweis "Chrome Web Store" "nicht eingerichtet – übersprungen"
        return 0
    fi
    local token item base
    token=$(http https://oauth2.googleapis.com/token \
        --data-urlencode "client_id=$CHROME_CLIENT_ID" --data-urlencode "client_secret=$CHROME_CLIENT_SECRET" \
        --data-urlencode "refresh_token=$CHROME_REFRESH_TOKEN" --data-urlencode "grant_type=refresh_token" \
        | jq -r '.access_token // empty') || true
    [ -n "$token" ] || { fehlschlag "Chrome Web Store" "Anmeldung fehlgeschlagen (Refresh-Token prüfen)"; return; }
    echo "::add-mask::$token"
    item="publishers/$CHROME_PUBLISHER_ID/items/$CHROME_EXTENSION_ID"
    base=https://chromewebstore.googleapis.com

    local state
    state=$(http -X POST -H "Authorization: Bearer $token" -T "$ZIP" "$base/upload/v2/$item:upload" | jq -r '.uploadState // empty') \
        || { fehlschlag "Chrome Web Store" "Upload abgelehnt"; return; }
    for _ in $(seq 1 20); do
        [ "$state" = "IN_PROGRESS" ] || break
        sleep 15
        state=$(http -H "Authorization: Bearer $token" "$base/v2/$item:fetchStatus" | jq -r '.lastAsyncUploadState // empty')
    done
    [ "$state" = "SUCCEEDED" ] || { fehlschlag "Chrome Web Store" "Upload nicht erfolgreich: ${state:-unbekannt}"; return; }
    http -X POST -H "Authorization: Bearer $token" -H 'Content-Type: application/json' -d '{}' "$base/v2/$item:publish" >/dev/null \
        || { fehlschlag "Chrome Web Store" "Einreichen abgelehnt"; return; }
    hinweis "Chrome Web Store" "hochgeladen und zur Prüfung eingereicht"
}

edge() {
    if [ -z "${EDGE_CLIENT_ID:-}" ] || [ -z "${EDGE_API_KEY:-}" ] || [ -z "${EDGE_PRODUCT_ID:-}" ]; then
        hinweis "Edge Add-ons" "nicht eingerichtet – übersprungen"
        return 0
    fi
    local base auth=(-H "Authorization: ApiKey $EDGE_API_KEY" -H "X-ClientID: $EDGE_CLIENT_ID")
    base="https://api.addons.microsoftedge.microsoft.com/v1/products/$EDGE_PRODUCT_ID/submissions"

    # Antwort 202 mit «Location: <operationID>»
    local op status
    op=$(curl -sS -D - -o /dev/null "${auth[@]}" -H 'Content-Type: application/zip' -X POST -T "$ZIP" "$base/draft/package" \
        | tr -d '\r' | awk 'tolower($1)=="location:"{print $2}' | sed 's#.*/##')
    [ -n "$op" ] || { fehlschlag "Edge Add-ons" "Upload abgelehnt"; return; }
    status=InProgress
    for _ in $(seq 1 40); do
        sleep 15
        status=$(http "${auth[@]}" "$base/draft/package/operations/$op" | jq -r '.status // empty') || status=Failed
        [ "$status" = "InProgress" ] || break
    done
    [ "$status" = "Succeeded" ] || { fehlschlag "Edge Add-ons" "Paket nicht angenommen: ${status:-unbekannt}"; return; }

    op=$(curl -sS -D - -o /dev/null "${auth[@]}" -H 'Content-Type: application/json' -X POST \
        -d '{"notes":"Neue Version aus dem GitHub-Release, siehe CHANGELOG.md."}' "$base" \
        | tr -d '\r' | awk 'tolower($1)=="location:"{print $2}' | sed 's#.*/##')
    [ -n "$op" ] || { fehlschlag "Edge Add-ons" "Einreichen abgelehnt"; return; }
    hinweis "Edge Add-ons" "hochgeladen und zur Prüfung eingereicht"
}

firefox() {
    if [ -z "${AMO_JWT_ISSUER:-}" ] || [ -z "${AMO_JWT_SECRET:-}" ]; then
        hinweis "Firefox Add-ons" "nicht eingerichtet – übersprungen"
        return 0
    fi
    local dir meta
    dir=$(mktemp -d)
    meta=$(mktemp --suffix=.json)
    (cd "$dir" && python3 -m zipfile -e "$ZIP" .)
    # Lizenz der Version (Pflicht bei AMO für gelistete Versionen): MIT wie der Kern glossary-highlighter (DEC-040)
    jq --arg l "${AMO_LICENSE:-MIT}" '.version.license = $l' "$ROOT/extension/store/amo-metadata.json" > "$meta"
    if npx --yes web-ext@10.8.0 sign --source-dir "$dir" --artifacts-dir "$dir/../amo" --channel listed \
        --amo-metadata "$meta" --upload-source-code "$SOURCE" \
        --api-key "$AMO_JWT_ISSUER" --api-secret "$AMO_JWT_SECRET" --approval-timeout 0; then
        hinweis "Firefox Add-ons" "hochgeladen und zur Prüfung eingereicht"
    else
        fehlschlag "Firefox Add-ons" "web-ext sign fehlgeschlagen (Ausgabe oben)"
    fi
    rm -rf "$dir" "$meta"
}

chrome
edge
firefox
exit "$fehler"
