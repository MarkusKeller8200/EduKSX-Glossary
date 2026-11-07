# EduKSX-Glossary – Projektdokumentation V1.2

> **Stand:** 7. November 2026  
> **Status:** Fachliche Eckpunkte bestätigt, technische Ausgestaltung in Prüfung  
> **Projekt:** EduKSX-Glossary · **Dachmarke:** MarKusSXCH · **Produktlinie:** EduKSX  
> **Quellprojekt:** `MarkusKeller8200/sek-intranet` (privat)  
> **Zielrepository:** `MarkusKeller8200/EduKSX-Glossary` (öffentlich)  
> **Hinweis:** Die Dokumentversion ist keine Softwareversion.

## 1. Status und Entscheidungsregeln

- **B – Beschlossen:** vom Projektinhaber ausdrücklich vorgegeben.
- **I – Ist-Zustand:** im bestehenden Repository geprüft, keine Festlegung für die neue Lösung.
- **V – Vorschlag:** noch zu prüfen/freizugeben.
- **O – Offen:** Entscheid oder Rechts-/Technikprüfung ausstehend.

**Vorrangregel:** Die in V1.1 bestätigte eigenständige öffentliche Webanwendung ersetzt die in V1.0 vorgeschlagene Confluence-zentrierte Architektur als Hauptarchitektur. Frühere Vorgaben zur rein Confluence-internen Speicherung gelten ausschliesslich für eine allenfalls separat realisierte Confluence-native Integration, nicht für EduKSX-Glossary selbst.

## 2. Produktvision, Zielgruppen und Abgrenzung

**B:** Eigenständige, öffentlich zugängliche Webanwendung zur Pflege und Publikation eines Glossars für den Kontext von Schweizer Volksschulen, Schulgemeinden und politischen Gemeinden. **B:** Die Daten werden über externe Recherche und Excel-Import/-Export gepflegt; Community-Mitglieder können neue Einträge und Änderungen beantragen. Nur der Projektinhaber oder eine berechtigte Admin-Gruppe kann sie zur Veröffentlichung freigeben. **B:** Eine versionierte, standardisierte und von Beginn weg produktiv nutzbare API ist ein essenzieller Bestandteil des Kernprodukts. Sie versorgt insbesondere das Intranet über regelmässige Synchronisierung.

Zielgruppen: Schulverwaltungen, Schul-ICT, Lehrpersonen, Schulleitungen, Behörden, Gemeinden, Fachstellen, interessierte Öffentlichkeit und externe Entwickler. Die Website bietet allen eine lesbare, durchsuchbare Begriffsquelle; die Redaktion gewährleistet Verlässlichkeit und Quellenbezug.

**Abgrenzung (V):** Keine amtliche Rechtsverbindlichkeit der Erklärungen, kein Ersatz für verbindliche Rechtsquellen, keine ungeprüfte KI-Autopublikation, keine personenbezogenen Schüler- oder Mitarbeitendendossiers, zunächst keine Vollmandantenfähigkeit. Confluence ist **ausschliesslich Datenempfänger**, nicht führender Datenspeicher: Glossareinträge sollen als Confluence-Seiten in beliebigen berechtigten Spaces publizierbar sein. Die Synchronisationsintegration mit Confluence ist ein nachrangiges Projektziel. Ein MCP-Server zur späteren Nutzung durch Rovo wird von Beginn weg architektonisch berücksichtigt, aber nicht im ersten Release vorausgesetzt.

## 3. Referenzimplementation und Übernahme

### 3.1 Gesicherter Ist-Zustand im privaten `sek-intranet` (I)

- Backend: PHP `^8.3`, Laravel `^13.17`, Inertia Laravel `^3.0`.
- Frontend: React `^19.2`, TypeScript, Tailwind CSS `^4`, Radix-Komponenten, Vite Plus.
- Glossar: `docs/KONZEPT-GLOSSAR.md`, `app/Support/Glossary.php`, `app/Data/Contracts/GlossaryRepository.php`, `app/Data/Json/JsonGlossaryRepository.php`, `app/Http/Controllers/GlossaryController.php`, `app/Support/GlossarySeed.php`, `resources/js/pages/glossary/*`, `resources/js/lib/glossary*.ts`, `tests/Feature/GlossaryTest.php`.
- Datenhaltung des Prototyps: JSON-Repositories; Seed-Daten für Kategorien, Herkunft und Glossar.
- Das Intranet verfügt bereits über eine allgemeine `/api/v1`-API mit Token-Schutz; im geprüften `routes/api.php` ist **noch kein dedizierter Glossar-Datenendpunkt** veröffentlicht.

### 3.2 Fachliche Felder aus dem bestehenden Modul (B: als Ausgangsbasis übernehmen; technische Übertragung V)

| Feld | Zweck | Besonderheit |
|---|---|---|
| `kategorien` | Mehrfachzuordnung | Erweiterbare Referenzliste |
| `abk_offiziell`, `aliase_offiziell` | Offizielle Abkürzung und Schreibweisen | Änderungen nur mit Sonderrecht |
| `abk_eigen`, `alias_eigen` | Lokal/alternativ verwendete Form | Separat von offizieller Form |
| `bez_offiziell`, `bez_eigen` | Offizielle/eigene Bezeichnung | Eigene kann in Intranet-Anzeige Vorrang haben |
| `kurzbeschreibung` | Tooltip (max. 160 Zeichen im bisherigen Konzept) | Kurz und verständlich |
| `beschreibung` | Ausführliche Definition | Quellenorientiert |
| `url`, `herkunft` | Beleg/Quelleninstitution | Mehrere Herkünfte möglich |
| `notiz` | Redaktionelle Ergänzung | Markdown im Intranet |
| `genutzt_in` | Kontextverweise | Im neuen System unabhängig vom Intranet modellieren |
| `status` | aktuell, historisch, zu prüfen | Nicht identisch mit Freigabestatus |
| `suchbegriffe` | Recherche | Suchindex |
| `gueltig_ab`, `gueltig_bis` | Zeitliche Geltung | Optional |
| `aktiv` | Anzeigen/ausblenden | Separat von Publikationsfreigabe |

**B (Referenzverhalten):** Mehrdeutige Abkürzungen dürfen mehrere Bedeutungen haben. Dubletten nur bei gleicher Abkürzung **und** gleicher Bedeutung zusammenführen; keine automatische Zusammenführung. Kategorien, Aliase und Herkünfte beim Zusammenführen erhalten. Offizielle Felder schützen und alle Änderungen nachvollziehbar halten.

**V:** Bei der Migration `bez_eigen` / `abk_eigen` als alternative Benennung der gemeinsamen Datenbasis erhalten; spätere mandantenspezifische Overrides getrennt modellieren statt einen globalen Wert für jede Schule anzunehmen.

## 4. Fachlicher Funktionsumfang

### 4.1 Öffentlicher Bereich

| ID | Anforderung | Status | Priorität |
|---|---|---|---|
| PUB-01 | Öffentlich lesbares Glossar ohne Anmeldung | B | Muss |
| PUB-02 | Suche, alphabetischer Index, Kategorien und Filter | V, im Intranet vorhanden | Muss |
| PUB-03 | Detailseite: Abkürzung, Langform, Kurztext, Definition, Quelle, Status | V | Muss |
| PUB-04 | Mehrdeutige Einträge getrennt und transparent anzeigen | B: Referenzregel | Muss |
| PUB-05 | Quellen- und Gültigkeitsinformationen transparent machen | V | Muss |
| PUB-06 | Mobil nutzbar, barrierearm, Tastaturbedienbarkeit | V | Muss |
| PUB-07 | Direktlink für jeden Eintrag und Teilen | V | Soll |

### 4.2 Redaktion und Import

| ID | Anforderung | Status | Priorität |
|---|---|---|---|
| RED-01 | Glossareinträge pflegen und Kategorien/Herkunft verwalten | B | Muss |
| RED-02 | Excel XLSX Import und Export | B | Muss |
| RED-03 | Importvorschau: neu/geändert/Fehler, ID-basiertes Update, kein Löschen durch fehlende Zeilen | B: Referenzverhalten | Muss |
| RED-04 | Externe Recherche mit Quellenbeleg, redaktionelle Prüfung vor Veröffentlichung | B + V für Prüfschritte | Muss |
| RED-05 | Dublettenprüfung, manuelle Zusammenführung, Mehrdeutigkeitskennzeichnung | B: Referenzverhalten | Muss |
| RED-06 | Audit-Protokoll und veröffentlichte Revisionen | V | Muss |
| RED-07 | Vorlagenexport und CSV-Unterstützung | XLSX-Vorlage: I; CSV: V | Soll |

### 4.3 Community

| ID | Anforderung | Status | Priorität |
|---|---|---|---|
| COM-01 | Neue Begriffe zur Aufnahme vorschlagen | B | Muss |
| COM-02 | Änderung eines bestehenden Eintrags beantragen | B | Muss |
| COM-03 | Prüfung durch Projektinhaber oder Admin-Gruppe | B | Muss |
| COM-04 | Veröffentlichung ausschliesslich nach positiver Freigabe | B | Muss |
| COM-05 | Statusverfolgung mit Ticket-/Vorschlags-ID | V | Soll |
| COM-06 | Rückfragen, Begründung bei Ablehnung, Moderationsprotokoll | V | Soll |
| COM-07 | Missbrauchsschutz, Spam- und Rate-Limits | V | Muss |

**V – Workflow:** `eingereicht → vorprüfung → in_prüfung → angenommen → veröffentlicht`, alternativ `rückfrage`, `abgelehnt`, `zurückgezogen`. Die Annahme eines Vorschlags ist **nicht** gleichbedeutend mit der unmittelbaren Veröffentlichung; diese erfolgt durch einen kontrollierten Publikationsschritt. Bei Änderungsvorschlägen bleibt die bereits publizierte Revision unverändert, bis die neue Revision freigegeben wurde.

## 5. Benutzer- und Berechtigungsmodell

| Rolle | Lesen | Vorschlagen | Eigene Vorschläge sehen | Prüfen | Freigeben | Quellen/Import verwalten |
|---|---|---|---|---|---|---|
| Öffentlichkeit | Ja | Ja (mit Schutzmassnahmen) | Über Vorgangskennung (V) | Nein | Nein | Nein |
| Registriertes Community-Mitglied (V) | Ja | Ja | Ja | Nein | Nein | Nein |
| Redaktion (V) | Ja | Ja | Ja | Ja | Nein | Nach Berechtigung |
| Admin-Gruppe (B) | Ja | Ja | Ja | Ja | Ja | Ja |
| Projektinhaber (B) | Ja | Ja | Ja | Ja | Ja | Ja |

**O:** Ob Einreichen ohne Login, mit E-Mail-Verifikation, GitHub-Login oder anderem Identity-Provider erlaubt wird, muss festgelegt werden. Datenschutzarme Einreichung ohne Veröffentlichung der Identität ist bevorzugt. Admin-Zugriff mit MFA und fein granularen Berechtigungen (V).

## 6. Vorgeschlagenes Datenmodell

**V:** Relationales Schema mit folgenden Tabellen/Entitäten (PostgreSQL als bevorzugte Option, nicht beschlossen):

- `terms`: `id` UUID, `slug`, `official_abbreviation`, `own_abbreviation`, `official_name`, `own_name`, `short_description`, `description`, `lifecycle_status`, `is_active`, `valid_from`, `valid_until`, `created_at`, `updated_at`.
- `term_aliases`: `id`, `term_id`, `alias`, `alias_type` (`official`, `own`, `search`), Sprache; keine globale Eindeutigkeitsbedingung auf Alias, da Mehrdeutigkeit zulässig.
- `categories`, `term_categories`: mehrere Kategorien pro Begriff.
- `sources`, `term_sources`: Institution/Herkunft, URL, Recherche-/Abrufdatum, Quellenhinweis, Belegstatus.
- `term_revisions`: unveränderliche Snapshots pro Revision, Änderungstyp, Version, Autor/System, Zeit, Freigebende, Publikationszeit.
- `proposals`: Einreichen/Ändern, Ziel-Term und Basisrevision (optional), vorgeschlagene Änderung als Payload, Status, Begründung, Einreicherbezug, Zeitstempel.
- `proposal_events`: Statusübergänge, Prüfvermerke, Ablehnungsgründe, Kommentare; nicht öffentlich, sofern personenbezogen.
- `users`, `roles`, `permissions`, `user_roles`: Redaktionsidentitäten und Zugriffssteuerung.
- `import_batches`, `import_rows`: Datei-Metadaten, Validierungs-/Preview-Ergebnis, Entscheidungen, Fehler; Aufbewahrung begrenzen.
- `api_clients` (nur bei benötigter Authentisierung), `publication_releases` (Version, Veröffentlichungsdatum, Prüfsumme).
- `term_relations` (V, später): verwandte Begriffe, Synonyme, ersetzt_durch.

**Trennung der Statusachsen:** `lifecycle_status` = aktuell/historisch/zu prüfen; `proposal.status` = Moderationsfortschritt; `term_revision.published_at` = öffentlich sichtbare freigegebene Revision. Unveröffentlichte Entwürfe und Anträge dürfen von der öffentlichen API nicht abrufbar sein.

**Importregeln:** Stabile IDs beibehalten, übergebenes Spreadsheet-Format versionieren, Unbekanntes melden; beim Merge Original-IDs als Redirect/Alias referenzieren (V); keine Löschungen über Import. Excel-Formeln beim Export als Text schützen (CSV-/Excel-Injection); Dateien, MIME und Grössen prüfen, Makros nicht ausführen.

## 7. Technische Zielarchitektur und Stack-Entscheidung

**B:** Technologiestack an `sek-intranet` ausrichten; finale Auswahl bleibt offen.

**V – bevorzugter Lösungsweg:** PHP 8.3+, Laravel 13 (Versionen bei Projektstart auf Support prüfen), React 19, TypeScript, Inertia 3, Tailwind 4; API über Laravel-Routen und OpenAPI 3.1. React-Komponenten und Datenmodell aus dem Intranet selektiv übernehmen, keine ungeprüfte Kopie des privaten Repositorys.

**V – Datenhaltung:** PostgreSQL für gleichzeitige Community-Bearbeitung, atomare Freigabe, referentielle Integrität, Abfragen und Audit-Historie. JSON-Dateispeicherung des Intranet-Prototyps ist als Migrationsquelle geeignet, aber für öffentliche Mehrbenutzernutzung nicht empfohlen. Migrations-/Seed-System, Hintergrundjobs, Tests und Backups etablieren.

**B – Systemverantwortung:** EduKSX-Glossary ist das führende System (Single Source of Truth). Intranet und Confluence sind abgeleitete Konsumenten. Das Intranet liest und synchronisiert über die öffentliche API; Confluence-Seiten sind publizierte Kopien. Änderungen erfolgen primär im Glossary-Redaktionsprozess und nicht direkt in den Kopien.

**V – Komponentenfluss:**

`Recherche / XLSX / Community → Eingangsprüfung → Entwurfs-/Vorschlagsdaten → Moderation & Freigabe → Publikationsrevisionen → öffentliches Web + /api/v1 → regelmässig synchronisiertes Intranet / später Confluence-Publisher / weitere Konsumenten`

**V – vorbereitete Integrationsgrenzen:** gemeinsame, versionierte Lese- und Schreib-Service-Schicht mit eindeutigen Term-IDs, Revisionsnummern, Events/Outbox und deklarierter Berechtigungspolitik; REST-Controller, Importprozesse und später ein MCP-Adapter rufen dieselben geprüften Anwendungsdienste auf. MCP darf weder an Datenbanktabellen noch an UI-Controller direkt gekoppelt werden.

**O – Hosting:** Schweizer VPS oder anderer geeigneter Standort, Docker-Deployment, Reverse Proxy/TLS, verwaltete DB oder eigene PostgreSQL-Instanz. Keine konkrete Hostingentscheidung.

**O – Code-/Lizenzrechte:** Die Wiederverwendung aus dem privaten Intranet setzt Prüfung der eigenen Rechte, Fremdkomponenten und Dritt-Lizenzen voraus. Die für Glossary gewählte Softwarelizenz ist separat zu entscheiden; Einschränkungen kommerzieller Nutzung sind mit OSI-anerkanntem Open Source grundsätzlich nicht vereinbar.

## 8. Öffentliche standardisierte REST-API

**B:** Die API ist eine verpflichtende Kernfunktion und muss spätestens mit dem ersten nutzbaren Produktrelease verfügbar sein. Der konkrete Pflichtanwendungsfall ist die regelmässige Synchronisierung des `sek-intranet`. Konsumenten dürfen nicht direkt auf die Glossary-Datenbank zugreifen.

**V – API-Konvention:** HTTPS, JSON UTF-8, REST, OpenAPI 3.1, semantisch versionierter URL-Pfad `/api/v1`, stabile UUIDs, stabile Slugs, definierte Fehlerantworten (`application/problem+json`), Filter, Pagination, ETag/If-None-Match, Cache-Control, Rate-Limits, CORS-Allowlist bzw. kontrollierte CORS-Strategie. Eine öffentliche Lese-API kann ohne API-Key angeboten werden; Schreib-/Moderations-APIs müssen authentisiert und autorisiert sein.

| Methode | Endpunkt (V) | Zweck |
|---|---|---|
| GET | `/api/v1/terms` | Veröffentlichte, aktive Einträge mit Suche, Filter, Pagination |
| GET | `/api/v1/terms/{id-or-slug}` | Ein einzelner freigegebener Eintrag |
| GET | `/api/v1/categories` | Öffentliche Kategorien |
| GET | `/api/v1/sources` | Veröffentlichbare Quellinformationen |
| GET | `/api/v1/releases/latest` | Aktueller Datenbestand, Version/ETag |
| GET | `/api/v1/changes?since={cursor}` | **V:** Inkrementelle Änderungen inklusive Deaktivierungen und Löschmarkierungen für Sync |
| GET | `/api/v1/exports?format=json` | Optionaler Full-Snapshot für Importsysteme |
| POST | `/api/v1/proposals` | Neue Vorschläge; Missbrauchsschutz/Authentisierung nach Entscheid |
| GET | `/api/openapi.json` | Maschinenlesbare API-Dokumentation |

Beispiel einer öffentlichen Antwort (V):

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
  "sources": [{"name": "Schule", "url": "https://example.org/quelle"}]
}
```

*Die Beispiel-URL und das Beispieldatum sind Platzhalter, kein bestätigter Quellbeleg oder veröffentlichter Datensatz.*

**V – Synchronisationsvertrag:** Ein Vollsnapshot und ein inkrementeller Change-Feed bilden den Synchronisationspfad. Jeder Eintrag erhält eine unveränderliche ID, `revision`, `published_at` und `updated_at`; entfernte bzw. nicht mehr freigegebene Publikationen werden als Tombstone/Unpublish-Event übertragen. Cursor oder Release-Revision sind atomar, wiederholte Abrufe idempotent. Eine fehlgeschlagene Synchronisation verändert den zuletzt gültigen lokalen Snapshot nicht; nach Ausfall wird lückenlos nachgezogen. `ETag` und `304` erlauben kostengünstige periodische Prüfungen. Eine **Vorschlagsempfehlung** für den Betriebsbeginn ist ein geplanter Pull im Intranet alle 15–60 Minuten; das genaue Intervall wird im Betrieb entschieden.

**V – Intranet-Integration:** Der Intranet-Dienst liest freigegebene Daten per API und hält einen lokalen Cache/Snapshot vor; Fallback bei API-Ausfall. Nicht in die fremde zentrale Datenbasis schreiben, sondern Änderungen als Vorschläge einreichen. Ein Mapping über stabile IDs/Slugs und Versionen bereitstellen. Die vorhandene Intranet-Tooltip-Komponente und die Erkennung im Freitext bleiben als Darstellungsschicht bestehen. Der bisherige interne `/glossar/begriffe`-Endpoint kann als lokaler Adapter dienen.

**Abnahmetests:** identisches Ergebnis bei Web und API, unveröffentlichte Vorschläge fehlen vollständig, stabile IDs, getestete Filter/Pagination, korrekte ETags/304, definierte Breaking-Change-Strategie, Import ohne Datenverlust.


## 8a. Confluence als Empfängersystem (B: Zielrolle, V: Umsetzung)

**B:** Confluence ist keine Datenquelle mehr und wird nicht als Speicher für die kanonischen Glossardaten verwendet. Veröffentlichte Glossardaten sollen als reguläre Confluence-Seite in einem **beliebigen Space, für den die Integration Schreibberechtigung besitzt**, abgelegt und aktualisiert werden können. Der Confluence-Publisher ist gegenüber API und Intranet-Synchronisierung nachrangig.

**V – Publikationsmodell:**
- Verbindung pro Atlassian-Site und Ziel-Space; optional Ziel-Elternseite, Seitentitel, Fachbereich-/Kategorie-Filter, Sortierung und Publikationsmodus definieren.
- Eine vollständige Glossarübersicht auf einer einzelnen Seite als erster Modus; für grosse Kataloge optional Indexseite mit Kategorie-Unterseiten.
- Seite im üblichen Confluence-Seitenformat erzeugen/aktualisieren, inklusive Quelle, Stand, Ursprungs-URL und Hinweis, dass Glossary führend ist.
- Stabile Verknüpfung `site + space + pageId` im Publisher halten. Vor jeder Aktualisierung aktuelle Seitenversion lesen und konfligierende manuelle Bearbeitungen kontrolliert behandeln; **nicht** blind überschreiben.
- Nur veröffentlichte Einträge exportieren; Vorschläge, interne Auditdaten, personenbezogene Moderationsdaten und API-Secrets niemals übertragen.
- Erstveröffentlichung, manuelle Auslösung, periodischer Abgleich, Änderungsvergleich, Retry und Fehlerprotokoll vorsehen.
- Mehrere Ziel-Spaces und Atlassian-Sites als separate Publikationsziele modellieren. Es gibt kein garantiertes Schreibrecht in jedem Space; Berechtigungen müssen je Ziel geprüft werden.
- Bei Aufhebung einer Freigabe muss die Kopie aktualisiert oder entfernt werden; Strategie für Löschung und historische Confluence-Versionen vor Inbetriebnahme beschliessen.

**V – Auslösung:** Der Publisher kann als eigenständiger Worker mit Confluence-API-Adapter implementiert werden, ohne eine Laufzeit-Abhängigkeit von MCP oder Rovo zu erzeugen.

## 8b. MCP-Readiness und spätere Rovo-Anbindung

**B:** Die spätere Integration eines MCP-Servers wird von Anfang an bei Architektur und Datenmodell berücksichtigt. **B:** Die MCP-Implementierung ist kein Muss für den ersten Release. **B:** Rovo soll perspektivisch mit diesem MCP-Server Glossareinträge zur Aktualisierung vorschlagen beziehungsweise aktualisieren können.

**V – vorgesehene Fähigkeiten:** `search_terms`, `get_term`, `list_categories`, `propose_term`, `propose_term_change`, `get_proposal_status`; redaktionelle `approve_proposal`- und `publish_revision`-Werkzeuge allenfalls später und nur mit expliziter, eng begrenzter Berechtigung.

**V – Sicherheitsprinzip:** Rovo/MCP darf **keine ungeprüften Änderungen direkt veröffentlichen**. Vorgeschlagene Änderungen laufen standardmässig durch denselben Validierungs-, Moderations- und Auditprozess wie Community-Vorschläge. Der spätere Schreibmodus mit Freigaberechten erfordert gesonderte Entscheidung und technische Durchsetzung je Identität und Scope.

**V – Architektur:**

`Rovo → autorisierter MCP-Client/Connector → MCP-Server (Adapter) → Glossary Application Services → Vorschlags-Workflow → Adminfreigabe → veröffentlichte Revision → REST-API`

**V – Vorbereitung bereits in P1:** klar dokumentierte Service-Verträge, strukturierte Resultate und Fehlertypen, stabile IDs und Revisionen, Rechte-/Auditabstraktion, gesonderte maschinenbezogene Identitäten, Integrations-Scopes und OpenAPI-Dokumentation. Der MCP-Server bekommt später **keine direkte Schreibberechtigung auf die Datenbank**.

**O:** Konkrete Rovo-/Atlassian-Unterstützung für die gewählte MCP-Transportart, Connector-Registrierung, Authentisierung und Berechtigungsdurchreichung ist vor Umsetzung mit aktueller Herstellerdokumentation zu prüfen; keine Kompatibilität ohne Integrationstest zusichern.

## 9. Datenschutz und Sicherheit

**B:** Öffentliche Begriffsplattform mit redaktionell kontrollierter Veröffentlichung; keine personenbezogene Fallverwaltung als fachlicher Zweck.

**V – Massnahmen:** Datenminimierung bei Community-Einreichungen; Datenschutzhinweis, Aufbewahrungs- und Löschfristen für Vorschläge/Logs, rechtmässige Grundlage und klare Verantwortlichkeit; keine Veröffentlichung von E-Mail-Adressen oder IPs; Moderationsinhalte getrennt von Publikationsdaten. Admin-MFA, Rollenprinzip, CSRF-Schutz für Sitzungen, Input-Validierung, XSS-sichere Markdown-Darstellung, Rate-Limits, Spam-Prüfung, Malware-/Dateiprüfung, Backup- und Restore-Test, protokollierte Freigaben, Secrets ausserhalb des Codes, Dependency- und SAST-Prüfung. Quellenrechte, Zitatumfang und Nachnutzung öffentlicher Begriffslisten prüfen.

**O:** Betreiberrolle, Datenschutzerklärung, Rechtsgrundlagen, Hostingregion, Community-Identitätsmodell, Datenaufbewahrung, Sicherheitskonzept und Lizenz der veröffentlichten Datensammlung separat entscheiden. Softwarelizenz und Datenlizenz dürfen unterschiedlich sein.

## 10. Recherche, Quellen und Qualität

Ausgangspunkt: Volksschulamt des Kantons Zürich, Datenschutzbeauftragte des Kantons Zürich, Gemeindeamt, weitere kantonale Stellen, interne Schulterminologie; bereits erarbeitete Begriffssammlungen sind als Ausgangsmaterial zu konsolidieren. Rechercheergebnisse gelten **nicht automatisch** als offizielle Definitionen.

**V – Qualitätsregeln:** Herkunft und URL je nichttrivialem Eintrag; Quellenprüfdatum; Kennzeichen `offiziell` nur bei belegter amtlicher Form; redaktionelle Kurzbeschreibung unabhängig von fremden Originaltexten; Dublettenliste vor Publikation; Mehrdeutigkeiten bewusst erhalten; jährliche Wiedervorlage für veränderliche Begriffe; Änderungsjournal.

## 11. Roadmap und Abnahmekriterien

| Phase | Ziel | Lieferobjekte | Gate |
|---|---|---|---|
| P0 – Bestand | Vorhandenes Glossary-Modul und Datenstruktur inventarisieren | Feldmapping, Funktionsmatrix, Lizenz-/Rechteprüfung | Bestandsaufnahme bestätigt |
| P1 – Architektur | Stack, Hosting, Datenbank, Rollen und API-Vertrag entscheiden | ADRs, ER-Modell, OpenAPI-Entwurf, Threat Model | Architektur freigegeben |
| P2 – Datenmigration | Intranet-Daten und Excel-Listen übernehmen | Migrationen, Importvorschau, Quellenprüfung, Dublettenprüfung | Prüfdaten importiert, keine stille Löschung |
| P3 – Öffentliche Plattform & Pflicht-API | Suche, Kategorien, Details, Release-Version und Synchronisations-API bereitstellen | Responsive UI, `/api/v1`, OpenAPI, Snapshot/Change-Feed | Intranet-Sync-Vertrag automatisiert getestet |
| P4 – Community | Anträge und Moderation implementieren | Formular, Workflow, Audit, Freigabe | Kein Antrag ohne Adminfreigabe öffentlich |
| P5 – Pflichtintegration Intranet | Intranet als API-Konsument mit periodischem Pull anbinden | Adapter, Caching, Fallback, Mapping, Cursor-Sync | Synchronisierung inklusive Deaktivierung und Recovery getestet |
| P7 – Confluence-Publisher | Beliebige berechtigte Confluence-Spaces als Ziele anbinden | Space-Konfiguration, Seiten-Rendering, Konfliktbehandlung, Sync-Protokoll | Test in mehreren Spaces/Sites erfolgreich |
| P8 – MCP für Rovo | MCP-Adapter und Rovo-Anbindung auf bestehenden Services aufsetzen | MCP-Tools, Auth, Moderationsworkflow, Integrationstests | Keine ungeprüfte Veröffentlichung, Audit vollständig |
| P6 – Betrieb | Sichere Bereitstellung und Pflege | CI/CD, Monitoring, Backups, Datenschutzinformation | Freigabe für öffentlichen Betrieb |

**MVP-Empfehlung (V):** P0–P3 einschliesslich funktionsfähiger öffentlicher **Synchronisations-API**; Community und Intranet-Anbindung als nachfolgende Entwicklungsarbeit, jedoch **beide für den ersten fachlichen Produktbetrieb verbindlich**. P7 (Confluence-Publisher) und P8 (MCP/Rovo) sind nachrangig. Keine Termine ohne Ressourcenplanung festlegen.

## 12. GitHub-Dokumentationsstruktur (V)

```text
EduKSX-Glossary/
├── README.md
├── CHANGELOG.md
├── CONTRIBUTING.md
├── SECURITY.md
├── AGENTS.md
├── VERSION
├── docs/
│   ├── PROJECT_CONCEPT_V1.2.md
│   ├── DECISIONS.md
│   ├── DATA_MODEL.md
│   ├── API.md
│   ├── SYNC_CONTRACT.md
│   ├── CONFLUENCE_PUBLISHER.md
│   ├── MCP_READINESS.md
│   ├── MIGRATION_FROM_SEK_INTRANET.md
│   ├── COMMUNITY_MODERATION.md
│   ├── SECURITY_PRIVACY.md
│   └── adr/
├── openapi/
│   └── glossary-v1.yaml
├── database/migrations/
├── app/
├── resources/js/
└── tests/
```

**I:** Das bestehende Glossary-Repository besitzt gegenwärtig eine statische, über GitHub Pages publizierte Website mit `site/begriffe.json` und `site/index.html`; GitHub-Issue-Formulare dienen bereits der Mitwirkung. Dies ist vom **geplanten neuen Backend mit Moderation und standardisierter API** zu unterscheiden. Bei der Migration GitHub Pages/Issues als Übergangslösung dokumentieren und nicht ohne Plan entfernen.

## 13. Offene Entscheide (O)

1. Exakte Backend-/Frontend-Versionen, PostgreSQL vs. alternative persistente Datenbank.
2. Hosting, Betriebs- und Verantwortlichkeitsmodell.
3. Community-Login oder Einreichen ohne Account; Moderationsrechte und Admin-Gruppe.
4. Quellen- und Lizenzprüfung bei Übernahme aus dem privaten Intranet.
5. Freigabe-, Versions- und Rollback-Regeln sowie Umgang mit historischen Datensätzen.
6. Öffentliche API: Authentisierung, Nutzungsbedingungen, Gesamt-Export, Rate-Limits und Stabilitätsgarantie.
7. Mandantenspezifische Bezeichnungen/Notizen versus zentrale gemeinsame Definition.
8. Daten- und Softwarelizenz; kommerzielle Nutzung und Open-Source-Begriff korrekt abgrenzen.
9. Konkrete Zuordnung bestehender GitHub-Issues zu neuen Vorschlagsdatensätzen.
10. Priorisierung und Zeitpunkte für Community und Intranet-Integration; die REST-API selbst ist nicht optional.
11. Confluence-Publikationsformat, Seitenstruktur, Mehrfachziele und Konflikt-/Löschstrategie.
12. Rovo-MCP-Verbindung, Identitätsdurchreichung, Schreib-Scopes und Freigabemodus.

## 14. Herkunft der Analyse

- **B:** Neue Eckpunkte des Projektinhabers, 07.11.2026, einschliesslich API-Pflicht, Confluence als Datenempfänger und späterem MCP/Rovo.
- **V:** Dokumentversion V1.2 ersetzt die früheren Confluence-Datenhaltungsannahmen explizit.
- **I:** `https://github.com/MarkusKeller8200/sek-intranet/blob/main/docs/KONZEPT-GLOSSAR.md`, `composer.json`, `package.json`, die oben bezeichneten PHP-/TSX-Dateien und `routes/api.php`, geprüft am 07.11.2026.
- **I:** `https://github.com/MarkusKeller8200/EduKSX-Glossary/blob/main/README.md` und aktuelle Repository-Struktur, geprüft am 07.11.2026.
- **B/D/V:** Vorheriger Konzeptentwurf V1.0 und frühere Fachgespräche.