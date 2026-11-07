#!/usr/bin/env bash
# Zeigt das Ende einer Ausgabe als Fehler-Hinweis (Annotation) im GitHub-Lauf an.
# So ist die Ursache in der Übersicht sichtbar, ohne das ganze Protokoll zu öffnen.
# Aufruf: report-failure.sh <datei> <titel> [zeilen]
set -u

file="$1"
title="$2"
lines="${3:-80}"

[ -s "$file" ] || { echo "::error title=$title::Keine Ausgabe vorhanden."; exit 0; }

# Diffs vollständig, in Teilen zu höchstens ~45 KB (Grenze je Hinweis 64 KB, höchstens 10 je Schritt),
# damit die Korrektur aus den Hinweisen mit «git apply» übernommen werden kann.
if head -n 1 "$file" | grep -q '^diff --git'; then
    python3 - "$file" "$title" <<'PY'
import re, sys
path, title = sys.argv[1], sys.argv[2]
text = re.sub(r'\x1B\[[0-9;?]*[A-Za-z]', '', open(path, encoding='utf-8', errors='replace').read()).replace('\r', '')
files = re.split(r'(?m)^(?=diff --git )', text)
chunks, current = [], ''
for part in files:
    if current and len((current + part).encode()) > 45000:
        chunks.append(current)
        current = ''
    current += part
if current:
    chunks.append(current)
for i, chunk in enumerate(chunks[:10], 1):
    body = chunk.replace('%', '%25').replace('\n', '%0A')
    print(f'::error title={title} ({i}/{len(chunks)})::{body}')
PY
    exit 0
else
    text=$(sed -E 's/\x1B\[[0-9;?]*[A-Za-z]//g' "$file" | grep -v '^[[:space:]]*$' | tail -n "$lines")
fi
text=${text//'%'/'%25'}
text=${text//$'\r'/}
text=${text//$'\n'/'%0A'}

echo "::error title=$title::$text"
