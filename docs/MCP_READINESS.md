# MCP-Readiness für spätere Rovo-Anbindung

**Beschlossen:** MCP-Server bereits im Architekturdesign vorsehen, aber nicht für erstes Release implementieren.

**Vorschlag:** Adapter vor versionierte Glossary Application Services setzen. Lesetools (`search_terms`, `get_term`) und Antragswerkzeuge (`propose_term`, `propose_term_change`) ergänzen; Freigabewerkzeuge nur nach gesonderter Berechtigungsentscheidung. Alle Änderungen auditieren, keine Direktpublikation durch KI.

**Zu prüfen:** Aktuelle Rovo-MCP-Unterstützung, Authentisierung, Transport, Connector-Konfiguration, Berechtigungen, Scope-Isolation und Integrationstests.
