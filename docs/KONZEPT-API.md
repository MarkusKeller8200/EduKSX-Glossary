# Konzept: REST-API und Synchronisation

**Abgenommen** am 07.11.2026, aus Konzept V1.2 (Abschnitt 8) und dem Sync-Vertrag. Entscheide DEC-004 und DEC-005.

## Grundsatz (beschlossen)

- Die API gehört zum Kern und ist spätestens mit dem ersten nutzbaren Release da.
- Pflicht-Anwendungsfall: das **sek-intranet synchronisiert regelmässig**.
- Das Glossary ist das führende System. Andere Systeme greifen nie direkt auf die Datenbank zu und erhalten nur
  veröffentlichte Daten.

## Konventionen (Vorschlag)

- HTTPS, JSON UTF-8, REST, **OpenAPI 3.1** (`openapi/glossary-v1.json`, ausgeliefert unter `/api/openapi.json`,
  Doku mit Scalar).
- Version im Pfad: `/api/v1`. Felder nur hinzufügen; was bricht, kommt in `/api/v2` (KONZEPT-VERSIONIERUNG).
- Stabile UUID und Slug je Begriff; Fehler als `application/problem+json`; Filter, Pagination, ETag/If-None-Match,
  Cache-Control, Rate-Limits, CORS für Lesezugriffe von jeder Herkunft (`*`, nur GET); schreibende Endpunkte
  bekommen eine Liste erlaubter Herkünfte.
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
  Start: Abruf alle 5–15 Minuten, siehe «Zugang und Grenzen»).

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
- **Cursor im Vollstand (#29):** `/api/v1/exports` und `/api/v1/releases/latest` liefern `cursor` (letztes
  Ereignis). Abnehmer holen danach nur noch `/api/v1/changes?since=<cursor>`. Ablauf und Referenzclient:
  [SYNC-INTRANET.md](SYNC-INTRANET.md), Prüfung mit `php artisan glossary:sync-check`.
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

## Nutzungsbedingungen und Stabilität (#30, beschlossen 07.11.2026)

Entscheid zum offenen Punkt 5 aus [KONZEPT.md](KONZEPT.md) (DEC-016).

### Stabilitätszusage v1 – erst ab öffentlichem Betrieb

- **Bis zum öffentlichen Betrieb (Etappe 1.0)** ist die API eine **Vorschau**: Felder und Endpunkte können sich
  ändern. Änderungen stehen im CHANGELOG; Abnehmer (das Intranet) passen sich an. Auf `/api/docs` und in der
  OpenAPI-Beschreibung steht der Hinweis «Vorschau».
- **Ab dem öffentlichen Betrieb** gilt die folgende Zusage (Entscheid Projektverantwortliche:r, 07.11.2026):
    - **Was bleibt:** Pfade, Parameter und Felder von `/api/v1` werden weder entfernt noch umbenannt. Ihre Bedeutung
      ändert sich nicht. IDs (UUID) und Slugs bleiben stabil; nach einer Zusammenführung leitet die alte Adresse mit
      `301` weiter.
    - **Was dazukommen darf:** neue Felder, neue Endpunkte, neue Werte bei Aufzählungen (z. B. Ereignisarten im
      Change-Feed, Status). Abnehmer ignorieren Unbekanntes.
    - **Brechende Änderungen** gibt es nur in einer neuen Version `/api/v2`. v1 läuft danach mindestens **12 Monate**
      parallel weiter. Die Ankündigung erfolgt im CHANGELOG, auf `/api/docs` und mit der Kopfzeile
      `Deprecation`/`Sunset` (RFC 8594, RFC 9745).
    - **Version der Beschreibung:** `info.version` in `openapi/glossary-v1.json` folgt der App-Version. Erweiterungen
      erhöhen MINOR.

### Zugang und Grenzen (beschlossen 07.11.2026)

- **Ohne Anmeldung und ohne Schlüssel:** Die API liefert nur veröffentlichte Daten.
- **Rate-Limit:** 120 Abrufe pro Minute und Adresse. Darüber kommt `429` mit `Retry-After`. Für den Sync reicht
  ein Abruf alle 5–15 Minuten; Abnehmer nutzen ETag und `If-None-Match`, eine `304`-Antwort ist günstig.
- **Gesamt-Export:** `/api/v1/exports?format=json` ist frei abrufbar. Empfohlen ist höchstens ein Abruf pro Stunde;
  danach `/changes` verwenden.
- **Keine Zusage zur Verfügbarkeit (SLA):** Die API ist ein freiwilliges Angebot. Abnehmer halten einen lokalen
  Stand (Cache, Fallback) und dürfen bei einem Ausfall nicht stehen bleiben. Für das Intranet ist das Etappe 0.5.
- **Missbrauch** (massenhaftes Abfragen, Umgehen des Limits) kann je Adresse gesperrt werden; Cloudflare-Regeln
  folgen mit #19.

### Nutzung der Daten (Lizenz – beschlossen 07.11.2026, vorläufig)

- **Entscheid:** Begriffssammlung vorerst unter **CC BY 4.0** (Projektverantwortliche:r, 07.11.2026: «momentan ok»;
  wird mit der Lizenzprüfung, offener Entscheid 3, bestätigt). Nennung: «EduKSX-Glossary, glossary.eduksx.ch». Die API
  liefert die Lizenz im Vollstand (`license`) und auf `/api/v1` mit.
- **Ausnahme:** Offizielle Bezeichnungen und Abkürzungen sind Fakten und frei. Längere Texte aus amtlichen Quellen
  übernehmen wir nur als Verweis (`url`, Herkunft), nicht wörtlich. So bleibt CC BY für die eigenen Texte möglich.
- **Software-Lizenz:** getrennt, nicht Teil dieses Entscheids.

### Umsetzung nach Abnahme

- Abschnitt «Nutzung und Stabilität» auf `/api/docs` und in `info.description`/`info.license` der
  OpenAPI-Beschreibung.
- Feld `license` (Name, URL, Nennung) in `/api/v1` und `/api/v1/exports`.
- Kopfzeilen `Deprecation`/`Sunset` erst, wenn eine v2 kommt.

## Identitäten und Datenschutzgrenze für API und Integrationen (DEC-017/018)

- **Öffentliches Lesen bleibt anonym:** `GET /api/v1/...`, veröffentlichter Voll-Export und Change-Feed benötigen weiterhin keinen Schlüssel.
- **Schreibende Zugriffe** wie `POST /api/v1/proposals` und spätere MCP-Werkzeuge erfordern die passende Berechtigung einer authentifizierten Person oder eines eigenständigen Servicekontos mit eng begrenzten Scopes. Ein Token berechtigt nicht automatisch zur Veröffentlichung.
- Die öffentliche API serialisiert ausschliesslich kontrollierte Veröffentlichungsfelder. Benutzerprofile, externe Identitätskennungen, private Vorschläge, Moderationskommentare, Sicherheits-Audits und Token werden niemals in öffentlichen JSON-Ausgaben geliefert.
- **Abnahmetests:** Testdaten mit privaten Vorschlägen, Identitäten und Geheimnissen dürfen in keiner Antwort der Lese-API, im Export, im Change-Feed, in API-Beispielen oder im GitHub-Pages-Build erscheinen.
- Die vereinbarte Versionierungs- und Stabilitätszusage sowie das bestehende Rate-Limit bleiben unverändert.

## Gemeinsame Felder und berechnete Werte (DEC-020, #83)

Die API v1 ist bis zum öffentlichen Betrieb eine Vorschau (DEC-016). Deshalb werden die Felder direkt bereinigt,
ohne v2 und ohne Übergangsfrist; der CHANGELOG nennt die Änderung.

- **Entfallen:** `own_abbreviation`, `own_name`. Persönliche Darstellungen, Altwerte («Intern genutzt in»),
  `change_set`, Akteure und interne Autoren-IDs erscheinen nie.
- `abbreviation`, `name` = gemeinsame veröffentlichte Werte (gleich `official_abbreviation`, `official_name`), nie
  personalisiert.
- **Neu:** `official_aliases`, `alternative_aliases` (gemeinsam freigegeben), `display_label`, `is_full_term`;
  `aliases` bleibt Sammelliste aus beiden.
- **Nur in** `/terms`, `/terms/{idOrSlug}` und `/exports`: `is_ambiguous`, `meaning_count` (vom Gesamtstand
  abhängig). Im Change-Feed fehlen sie, damit ein altes Ereignis keine Zahlen eines späteren Stands zeigt.
- Positivliste `ApiPresenter::PUBLIC_TERM_FIELDS` (mit `CONTEXT_FIELDS`), `openapi/glossary-v1.json` nachgeführt.
- Das Intranet synchronisiert nur gemeinsame, veröffentlichte Felder und IDs und berechnet «Intern genutzt in»,
  Tooltips und lokale Benennungen selbst (eigener Auftrag im Repository `sek-intranet`).
- **Ergänzt (#87):** `search_terms` (Suchbegriffe, nur für die Suche). Kategorien und Herkunft liefern neben
  `key`, `label`, `name`, `url` auch `abbreviation`, `description` und `color` (Name aus der Palette wie im
  Intranet); die redaktionelle Notiz bleibt intern.
- **Beziehungen (`relations`, #283):** Art (`successor`, `see_also`, `broader`, `narrower`) mit `id`, `slug` und
  `display_label` des Ziels; nur öffentliche Ziele, zusammengeführte aufgelöst. Hängt vom Gesamtstand ab und fehlt
  deshalb im Change-Feed (wie `is_ambiguous`).
- **Sprache (`?lang=`, #282):** `de`, `fr`, `it`, `en` für Begriffe, Kategorien, Herkünfte, Change-Feed und Vollstand.
  Übersetzte Felder (`abbreviation`, `name`, `short_description`, `description`; bei Listen `name`, `label`,
  `description`) ersetzen die deutschen; fehlt eine Übersetzung, bleibt der deutsche Wert. `official_*` bleiben deutsch.
  Nur mit `?lang=` gibt es das Feld `language` (verlangte und je Feld gelieferte Sprache). `translations` enthält immer
  alle veröffentlichten Übersetzungen.
- **`updated_at` (#62):** Zeitpunkt der veröffentlichten Revision, wie im Synchronisationsvertrag verlangt.

## Organisations-API (DEC-025, #148, Entwurf)

`GET /api/v1/org/terms[?customized=1]` und `GET /api/v1/org/terms/{idOrSlug}` nur mit Token eines Servicekontos
einer Organisation (Scope `read-org`). Je Begriff `term` (gemeinsame Werte wie `/terms`) und `organization_display`
(Anpassungen der Organisation oder `null`). Keine Pagination, kein ETag, `Cache-Control: private, no-store`. Die
öffentlichen Endpunkte enthalten nie Anpassungen einer Organisation. Details:
[KONZEPT-ORGANISATION.md](KONZEPT-ORGANISATION.md).

## Permalink, Index, Swagger und Postman (DEC-026, #154, #157, #158, #159)

- Je Begriff `code` (6 Zeichen, unveränderlich) und `permalink` (`/g/{code}`); `/terms/{idOrSlug}` akzeptiert auch
  den Code.
- `GET /api/v1/index`: kompakter Index (Code, Abkürzung, Bezeichnung, Schreibweisen, Kurztext, mehrdeutig,
  Kategorien, Permalink) mit ETag für Renderer und Browser-Erweiterung.
- `/api/swagger`: Swagger UI mit «Try it out»; `/api/postman.json`: Postman-Sammlung (v2.1) aus der
  OpenAPI-Beschreibung (`scripts/postman.py`, Prüfung in der CI). Nach jeder Änderung an `openapi/glossary-v1.json`:
  `python3 scripts/postman.py`.
