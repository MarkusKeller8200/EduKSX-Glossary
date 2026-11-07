# Sync für Abnehmer (Intranet)

Anleitung für Systeme, die das Glossary übernehmen und aktuell halten. Die erste Anwendung ist das
Intranet (sek-intranet, Etappe 0.5). Eine Vorlage ist der Referenzclient `App\Glossary\SyncClient`.
Er ist ohne Laravel-Abhängigkeiten geschrieben und wird in der CI geprüft (`tests/Feature/SyncTest.php`, #29).

## Ablauf

1. **Vollstand beim ersten Mal:** `GET /api/v1/exports?format=json`. Die Antwort enthält alle öffentlichen
   Begriffe, die Listen und den `cursor`. Den Cursor lokal speichern.
2. **Danach nur noch Änderungen:** `GET /api/v1/changes?since=<cursor>&limit=500`. Weiterfragen mit
   `meta.next_cursor`, solange `meta.has_more` wahr ist. Den neuen Cursor erst speichern, wenn die Seite
   verarbeitet ist.
3. **Ereignisse in fester Reihenfolge anwenden:**
    - `published`: `term` übernehmen (Begriff in genau dieser Revision). Ist `term.is_active` falsch, den
      Begriff lokal entfernen oder ausblenden.
    - `unpublished`: Begriff lokal entfernen oder ausblenden (Tombstone).
    - `merged`: Begriff lokal entfernen und Verweise von `term_id` auf `merged_into` umleiten. Der
      verbleibende Begriff kommt als eigenes `published`-Ereignis.
    - Unbekannte Ereignisarten überspringen. Neue Arten sind möglich, ohne dass die Version wechselt.
4. **Listen separat:** Kategorien und Herkunft über `GET /api/v1/categories` und `/sources` holen. Im Begriff
   zählt der Schlüssel (`key`). Bezeichnungen können sich ändern, ohne dass der Begriff ein Ereignis erhält.

## Eigenschaften

- **Wiederholbar:** Dieselben Ereignisse noch einmal anzuwenden ändert nichts. Der Vollstand liest den Cursor vor
  den Begriffen; ein Ereignis, das dazwischen dazukommt, wird beim nächsten Abruf einfach nochmals angewendet.
- **Günstig prüfen:** `GET /api/v1/releases/latest` liefert Datenstand und Cursor. Ist der Cursor unverändert, ist
  nichts zu tun. Alle Antworten haben ein ETag; mit `If-None-Match` antwortet der Server mit `304`.
- **Alte IDs:** `GET /api/v1/terms/{alte-id}` antwortet nach einer Zusammenführung mit `301` auf den verbleibenden
  Begriff.
- **Intervall:** Ein Abruf alle 5–15 Minuten reicht. Das Limit liegt bei 120 Abrufen pro Minute und Adresse.

## Prüfen

```bash
php artisan glossary:sync-check http://127.0.0.1:8000          # lokal, Stand in storage/app/sync-check.json
php artisan glossary:sync-check https://glossary.eduksx.ch --neu   # mit Vollstand neu beginnen
```

Der Befehl synchronisiert wie ein Abnehmer und vergleicht danach mit dem Vollstand. Ergebnis: «Stand identisch»
oder eine Liste der Abweichungen.

## Gemeinsame Felder (DEC-020)

- Das Glossary liefert nur gemeinsame, veröffentlichte Felder. «Intern genutzt in», Tooltips und lokale Benennungen
  verwaltet das Intranet selbst; eigene Bezeichnungen werden nicht mehr synchronisiert.
- `is_ambiguous` und `meaning_count` stehen nur im Vollstand und in `/terms`, nicht im Change-Feed. Abnehmer
  berechnen sie aus ihrem lokalen Stand (Regel in `App\Glossary\GlossaryDisplay`); der Referenzclient lässt sie beim
  Vergleich weg.
