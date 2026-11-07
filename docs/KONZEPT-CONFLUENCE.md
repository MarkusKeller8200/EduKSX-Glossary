# Konzept: Confluence-Publisher (spätere Etappe)

**Abgenommen** am 07.11.2026, aus Konzept V1.2 (Abschnitt 8a) und CONFLUENCE_PUBLISHER. Entscheide DEC-006 und DEC-007.

## Beschlossen

- Confluence ist **nur Empfänger**, nicht Datenbasis und kein Speicher für die massgebenden Glossardaten.
- Veröffentlichte Begriffe sollen als normale Confluence-Seite in **jedem Space, für den die Integration
  Schreibrecht hat**, angelegt und aktualisiert werden können.
- Nachrangig gegenüber API und Intranet-Sync.

## Vorschlag

- Ziel je Atlassian-Site und Space: Elternseite, Seitentitel, Filter (Fachbereich/Kategorie), Sortierung, Modus.
- Erster Modus: eine Seite mit der ganzen Übersicht; bei grossen Beständen Index mit Unterseiten je Kategorie.
- Seite mit Quelle, Stand, Ursprungs-URL und dem Hinweis, dass das Glossary massgebend ist.
- Verknüpfung `site + space + pageId` speichern; vor jeder Aktualisierung die aktuelle Seitenversion lesen und
  manuelle Änderungen kontrolliert behandeln, **nie blind überschreiben**.
- Nur veröffentlichte Einträge; nie Vorschläge, Verlauf, Moderationsdaten oder Schlüssel.
- Erstveröffentlichung, Auslösen von Hand, periodischer Abgleich, Vergleich, Wiederholung, Fehlerprotokoll.
- Mehrere Spaces und Sites als getrennte Ziele; Schreibrecht je Ziel prüfen.
- Wird eine Freigabe aufgehoben, Kopie aktualisieren oder entfernen; Strategie für Löschung und Confluence-Versionen
  vor Inbetriebnahme festlegen.
- Eigenständiger Worker mit Confluence-API-Adapter, ohne Abhängigkeit von MCP oder Rovo.

## Offen (wird geklärt, wenn benötigt)

Format, Aufteilung der Seiten, Rechte für das Auslösen, Konflikte, historische Seiten, Häufigkeit.

## Veröffentlichungsgrenze und technische Identität (DEC-018)

Der Confluence-Publisher erhält ausschliesslich kontrollierte Veröffentlichungsfelder aus derselben Freigabeschicht wie API und GitHub Pages. Private Benutzerkonten, Vorschläge, Moderationsdaten, Audits und Servicekonto-Geheimnisse dürfen nicht exportiert werden. Die technische Anbindung verwendet ein zweckgebundenes Servicekonto oder eine gesicherte Zielverbindung. Der Publisher darf selbst keine fachlichen Freigaben erteilen.
