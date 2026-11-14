# Konzept: MCP-Server

**Abgenommen** am 07.11.2026, aus Konzept V1.2 (Abschnitt 8b) und MCP_READINESS. Entscheide DEC-008 und DEC-011.
**Ergänzt** am 12.11.2026 um die Umsetzung (DEC-029, abgenommen am 13.11.2026 – Abschnitt «Umsetzung» unten, #261). Die offenen Entscheide O1–O6 sind **OFFEN** und werden vor der Umsetzung geklärt.

## Beschlossen

- Ein MCP-Server wird in Architektur und Datenmodell **von Anfang an berücksichtigt**, aber nicht für das erste
  Release umgesetzt.
- Rovo soll später über diesen Server Begriffe zur Aktualisierung vorschlagen bzw. aktualisieren können.

## Vorschlag

- Werkzeuge (Namen nach API-Standard G2/D5 `<modul>_<operation>`, #319): `glossary_search_terms`, `glossary_get_term`, `glossary_list_categories`, `glossary_propose_term`, `glossary_propose_term_change`,
  `glossary_get_proposal_status`. `glossary_approve_proposal` und `glossary_publish_revision` höchstens später, mit eigenem Entscheid und eng
  begrenztem Recht.
- **Keine Veröffentlichung durch KI ohne Prüfung:** Vorschläge von Rovo laufen durch dieselbe Prüfung, Moderation und
  denselben Verlauf wie Community-Vorschläge.
- Ablauf: `Rovo → Connector → MCP-Server (Adapter) → Glossary-Services → Vorschlag → Freigabe → Revision → API`.
- Der MCP-Server bekommt **keinen direkten Zugriff auf die Datenbank** und hängt nicht an Controllern der Website.
- Schon jetzt vorbereiten: dokumentierte Service-Verträge, strukturierte Ergebnisse und Fehlertypen, stabile IDs und
  Revisionen, Rechte- und Verlaufsschicht, eigene Identitäten für Maschinen, Scopes, OpenAPI.

## Offen (wird geklärt, wenn benötigt)

Aktuelle Unterstützung von MCP in Rovo/Atlassian (Transport, Connector-Registrierung, Anmeldung, Weitergabe der
Identität, Rechte), vor der Umsetzung mit aktueller Herstellerdoku prüfen; Kompatibilität erst nach Integrationstest
zusichern.

## Geschützte Serviceidentitäten (DEC-017/018)

Der spätere MCP-Adapter erhält eine eigenständige technische Identität mit minimalen Scopes. Ein weitergereichter Benutzerkontext darf nur nach gesicherter, nachvollziehbarer Prüfung übernommen werden. Schreibwerkzeuge erzeugen Vorschläge über die regulären Glossary-Services und durchlaufen die Moderation; Veröffentlichung durch KI erfordert einen erneuten ausdrücklichen Projektentscheid. Private Benutzer- und Moderationsdaten werden nicht als allgemeine Suchergebnisse an Rovo ausgegeben.

## Umsetzung (DEC-029, abgenommen 13.11.2026)

Baut auf DEC-008, DEC-011, DEC-017 und DEC-018 auf. Ziel erweitert: nicht nur Rovo, sondern jeder MCP-fähige
Assistent (Claude, ChatGPT, Copilot, Rovo) kann geprüfte Glossar-Einträge abfragen, z. B. «Was heisst KLP?» oder
«Erkläre die Abkürzungen in diesem Elternbrief».

| Nr. | Vorschlag                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| --- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| M1  | **Bibliothek:** offizielles Paket `laravel/mcp` (Laravel 13), Server in `routes/ai.php`, Werkzeuge, Ressourcen und Prompts als eigene Klassen unter `app/Mcp/`. Kein eigener Protokoll-Code.                                                                                                                                                                                                                                                                                             |
| M2  | **Transport:** Streamable HTTP unter `/mcp` auf derselben Domain; kein stdio. Protokollstand 2026-07-28 (zustandslos, ohne Sitzung) – passt zu mehreren Instanzen und Komodo. Unterstützt `laravel/mcp` ihn noch nicht: Stand 2025-11-25 bis zum Update des Pakets (siehe O4).                                                                                                                                                                                                           |
| M3  | **Lesewerkzeuge (Phase 1):** `glossary_search_terms` (Suche wie Website, Filter Kategorie/Herkunft), `glossary_get_term` (Code `/g/{code}` oder Slug, alle veröffentlichten Felder), `glossary_resolve_abbreviation` (inkl. Aliase und Schreibweisen), `glossary_explain_text` (Abkürzungen in einem Text finden, gleiche Logik wie «glossary-highlighter», DEC-026), `glossary_list_categories` (Kategorien und Herkunft mit Beschreibung). Alle mit `IsReadOnly`.                      |
| M4  | **Ressourcen und Prompts:** je Begriff eine Ressource unter dem Permalink; Prompt «Text mit Glossar erklären».                                                                                                                                                                                                                                                                                                                                                                           |
| M5  | **Gleiche Daten wie die API:** Antworten über `ApiPresenter` (Felder wie API v1, Lizenzhinweis CC BY 4.0, Permalink). Nur veröffentlichte Felder (DEC-018); keine eigene Abfragelogik, nur Glossary-Services (DEC-011).                                                                                                                                                                                                                                                                  |
| M6  | **Anmeldung in Phasen:** Phase 1 ohne Anmeldung wie die öffentliche Lese-API (Begrenzer). Phase 2 Bearer-Token der Servicekonten (DEC-017) mit Scopes: persönliche bzw. Organisations-Darstellung (`glossary:terms.read`), Schreibwerkzeuge `glossary_propose_term`, `glossary_propose_term_change`, `glossary_get_proposal_status` – Vorschläge laufen durch die Moderation (DEC-024). Phase 3 OAuth 2.1 nach MCP-Autorisierung, geliefert von EduKSX-Ident, nicht vom Glossary selbst. |
| M7  | **Keine Veröffentlichung durch KI** (unverändert): `glossary_approve_proposal`/`glossary_publish_revision` nur mit erneutem Projektentscheid.                                                                                                                                                                                                                                                                                                                                            |
| M8  | **Betrieb:** Caddy leitet `/mcp` weiter; Begrenzer je IP-Adresse (Phase 1) bzw. Token; Protokoll der Servicekonten wie bei der API (ServiceAccountAudit). In der Administration abschaltbar (Einstellungen), auch für selbst gehostete Instanzen (siehe O2).                                                                                                                                                                                                                             |
| M9  | **Datenschutz:** Was ein Assistent sendet (z. B. ein ganzer Text bei `glossary_explain_text`), wird **nicht** protokolliert und nicht gespeichert; im Log höchstens Werkzeugname, Zeit, Ergebnis-Code. Nachführen in Datenschutzerklärung und Löschkonzept.                                                                                                                                                                                                                              |
| M10 | **Testen und Dokumentation:** Feature-Tests mit den Test-Hilfen von `laravel/mcp`, Prüfung mit dem MCP Inspector; Abschnitt «MCP» auf der Entwicklerseite mit fertiger Konfiguration (Claude Desktop, Claude Code, Rovo).                                                                                                                                                                                                                                                                |

### Phasen

1. **Phase 1 – lesen, öffentlich:** M1–M5, M8–M10. Aufwand überschaubar, die Abfragen bestehen bereits; der Hauptteil
   sind Tests, Begrenzer und Dokumentation.
2. **Phase 2 – Token und Vorschläge:** Servicekonten mit Scopes, Schreibwerkzeuge über den Vorschlagsablauf.
3. **Phase 3 – OAuth über EduKSX-Ident:** Anmeldung für Konnektoren (Claude.ai, ChatGPT, Rovo) mit Personenkonto;
   abhängig vom Plattform-Konzept (Repo EduKSX-Architektur).

### Offene Entscheide

| Nr. | Frage                                                                                                                                | Vorschlag                                                           |
| --- | ------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------- |
| O1  | Phase 1 ohne Anmeldung (wie die Lese-API) oder nur mit Token?                                                                        | ohne Anmeldung, mit Begrenzer                                       |
| O2  | MCP auch in selbst gehosteten Instanzen?                                                                                             | ja, Einstellung, Vorgabe aus                                        |
| O3  | Schreibwerkzeuge (Vorschläge) über MCP – ab Phase 2 oder gar nicht?                                                                  | ab Phase 2, nur Servicekonten mit Scope `glossary:proposals.create` |
| O4  | Protokollstand: auf 2026-07-28 in `laravel/mcp` warten oder mit 2025-11-25 beginnen?                                                 | vor der Umsetzung Paketstand prüfen                                 |
| O5  | Begrenzer ohne Anmeldung (Anfragen pro Minute)?                                                                                      | 60 pro Minute und IP-Adresse                                        |
| O6  | Rovo zuerst (ursprüngliches Ziel) oder allgemeine Assistenten zuerst? Rovo braucht laut «Offen» oben einen eigenen Integrationstest. | allgemein zuerst, Rovo als Integrationstest                         |
