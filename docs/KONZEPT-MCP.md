# Konzept: MCP-Server für Rovo (spätere Etappe)

**Abgenommen** am 07.11.2026, aus Konzept V1.2 (Abschnitt 8b) und MCP_READINESS. Entscheide DEC-008 und DEC-011.

## Beschlossen

- Ein MCP-Server wird in Architektur und Datenmodell **von Anfang an berücksichtigt**, aber nicht für das erste
  Release umgesetzt.
- Rovo soll später über diesen Server Begriffe zur Aktualisierung vorschlagen bzw. aktualisieren können.

## Vorschlag

- Werkzeuge: `search_terms`, `get_term`, `list_categories`, `propose_term`, `propose_term_change`,
  `get_proposal_status`. `approve_proposal` und `publish_revision` höchstens später, mit eigenem Entscheid und eng
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
