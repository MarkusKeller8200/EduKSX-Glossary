# Konzept: REST-API und Synchronisation

**Abgenommen** am 07.11.2026, aus Konzept V1.2 (Abschnitt 8) und dem Sync-Vertrag. Entscheide DEC-004 und DEC-005.

## Grundsatz (beschlossen)

- Die API gehört zum Kern und ist spätestens mit dem ersten nutzbaren Release da.
- Pflicht-Anwendungsfall: das **sek-intranet synchronisiert regelmässig**.
- Das Glossary ist das führende System. Andere Systeme greifen nie direkt auf die Datenbank zu und erhalten nur
  veröffentlichte Daten.

## Konventionen (Vorschlag)

- HTTPS, JSON UTF-8, REST, **OpenAPI 3.1** (`openapi/glossary-v1.yaml`, ausgeliefert unter `/api/openapi.json`,
  Doku mit Scalar).
- Version im Pfad: `/api/v1`. Felder nur hinzufügen; was bricht, kommt in `/api/v2` (KONZEPT-VERSIONIERUNG).
- Stabile UUID und Slug je Begriff; Fehler als `application/problem+json`; Filter, Pagination, ETag/If-None-Match,
  Cache-Control, Rate-Limits, CORS über eine Liste erlaubter Herkünfte.
- Lesen ohne Schlüssel; Schreiben (Vorschläge) und Moderation nur authentisiert und mit Recht.

## Endpunkte (Vorschlag)

| Methode | Endpunkt                         | Zweck                                                                    |
| ------- | -------------------------------- | ------------------------------------------------------------------------ |
| GET     | `/api/v1/terms`                  | veröffentlichte, aktive Einträge mit Suche, Filter, Pagination           |
| GET     | `/api/v1/terms/{id-or-slug}`     | ein freigegebener Eintrag                                                |
| GET     | `/api/v1/categories`             | öffentliche Kategorien                                                   |
| GET     | `/api/v1/sources`                | veröffentlichbare Herkunft/Quellen                                       |
| GET     | `/api/v1/releases/latest`        | aktueller Datenstand: Nummer, Zeitpunkt, Prüfsumme, ETag                 |
| GET     | `/api/v1/changes?since={cursor}` | Änderungen seit Cursor, inkl. Deaktivierungen und Rückzügen (Tombstones) |
| GET     | `/api/v1/exports?format=json`    | Vollstand für importierende Systeme (auch Quelle für GitHub Pages)       |
| POST    | `/api/v1/proposals`              | Vorschlag einreichen; Schutz nach Entscheid zur Anmeldung                |
| GET     | `/api/openapi.json`              | maschinenlesbare Beschreibung                                            |

Beispiel einer Antwort:

```json
{
    "id": "e84fa119-8521-4f55-a8e2-9423c59accee",
    "slug": "klassenlehrperson",
    "abbreviation": "KLP",
    "name": "Klassenlehrperson",
    "short_description": "Lehrperson mit Verantwortung für eine Klasse.",
    "categories": ["Schulorganisation"],
    "status": "aktuell",
    "revision": 3,
    "published_at": "2026-11-07T00:00:00Z",
    "sources": [{ "name": "Schule", "url": "https://example.org/quelle" }]
}
```

Beispiel-URL und -Datum sind Platzhalter.

## Synchronisationsvertrag (Vorschlag)

- **Vollstand** (`exports`) und **Änderungen** (`changes?since=`) sind die beiden Wege.
- Jeder Eintrag hat eine unveränderliche ID, `revision`, `published_at` und `updated_at`. Zurückgezogene oder nicht
  mehr freigegebene Einträge kommen als Tombstone bzw. Unpublish-Ereignis.
- Cursor bzw. Datenstand sind atomar, die Reihenfolge reproduzierbar, wiederholte Abrufe idempotent.
- `ETag` und `304 Not Modified` für günstige periodische Prüfungen.
- Schlägt ein Abruf fehl, bleibt der letzte gültige lokale Stand erhalten; danach wird lückenlos nachgezogen.
- Keine unveröffentlichten Entwürfe, Vorschläge oder redaktionellen Personendaten.
- Intervall, Anmeldung für Abnehmer und Aufbewahrung der Änderungen: werden geklärt, wenn benötigt (Vorschlag für den
  Start: Abruf alle 15–60 Minuten).

## Intranet als Abnehmer

- Ein Dienst im Intranet holt die freigegebenen Daten per API und hält einen lokalen Stand (Cache) als Rückfall.
- Zuordnung über stabile ID/Slug und Revision.
- Tooltip (`term.tsx`) und Erkennung im Freitext bleiben im Intranet; der interne Endpunkt `/glossar/begriffe` wird
  zum Adapter auf den lokalen Stand.
- Änderungen aus dem Intranet gehen als Vorschläge ins Glossary, nie direkt in dessen Daten.
- Ist-Zustand (geprüft 07.11.2026): Das Intranet hat eine `/api/v1` mit Token-Schutz, aber noch keinen
  Glossar-Endpunkt.

## Abnahmetests

- Website und API zeigen dasselbe; unveröffentlichte Vorschläge fehlen vollständig.
- Stabile IDs; Filter und Pagination getestet; ETag/304 korrekt.
- Regel für Breaking Changes festgelegt; Import ohne Datenverlust.
- Sync mit dem Intranet inkl. Rückzug und Wiederaufnahme nach Ausfall automatisch getestet.

## Umsetzung v1 (#25, #26, 07.11.2026)

- Endpunkte wie oben ausser `POST /api/v1/proposals` (kommt mit der Community-Funktion). Beschreibung:
  `openapi/glossary-v1.json` (OpenAPI 3.1), ausgeliefert unter `/api/openapi.json` und `/api/openapi.yaml`
  (JSON ist gültiges YAML). Interaktive Doku: **`/api/docs`** (Scalar). Ein Test prüft, dass jede Route unter
  `/api/v1` in der Beschreibung steht.
- **Change-Feed:** Tabelle `term_publication_events`; jede Veröffentlichung und jeder Rückzug ist ein Ereignis, die
  ID ist der Cursor. «published» enthält den Begriff in genau der veröffentlichten Revision, «unpublished» ist der
  Tombstone. Veröffentlichen mehrerer Begriffe in einem Schritt ergibt mehrere Ereignisse, aber einen Datenstand.
- **Zusammengeführt (#16):** Ereignis «merged» mit `merged_into` (verbleibender Begriff), danach folgt dieser als
  «published». `GET /api/v1/terms/{alte-id-oder-slug}` antwortet mit `301` und `Location` auf den verbleibenden
  Begriff; die Website leitet `/begriffe/{alter-slug}` ebenso dauerhaft weiter.
- **Sichtbar** ist ein Begriff, wenn er veröffentlicht ist und im veröffentlichten Abbild `is_active` gilt – der
  Arbeitsstand ändert nichts an Website oder API, bis er veröffentlicht wird.
- **ETag** = Prüfsumme des Antwortinhalts (auch Listen-Änderungen werden erkannt), `Cache-Control: public,
  max-age=60`, Kopfzeile `X-Glossary-Release`.
- **Fehler** als `application/problem+json` (RFC 9457) für alle Pfade unter `/api/`.
- **CORS:** `GET` von jeder Herkunft, ohne Cookies (`config/cors.php`).
- Rate-Limit 120 Abrufe pro Minute und Adresse.
