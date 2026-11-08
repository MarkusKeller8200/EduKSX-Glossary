# Konzept: EduKSX-Glossary

**Abgenommen** am 07.11.2026 (#3). Grundlage: Projektkonzept V1.2 (mit ChatGPT erarbeitet, 07.11.2026)
und das Glossar-Modul des Intranets (`docs/KONZEPT-GLOSSAR.md` im sek-intranet). Konzept V1.2 und seine
Beilagen (Entscheidungsregister, Sync-Vertrag, Confluence-Publisher, MCP-Readiness) sind in diese Konzepte
zusammengeführt; der Originaltext steht im Git-Verlauf (Commit `8d4367b`). Bei Widersprüchen gilt
[ENTSCHEIDE.md](ENTSCHEIDE.md).

| Konzept                                              | Inhalt                                                                               |
| ---------------------------------------------------- | ------------------------------------------------------------------------------------ |
| [KONZEPT-BENUTZER.md](KONZEPT-BENUTZER.md)           | Konten, Identitäten, Rollen, MFA und Servicekonten                                   |
| [DATENSCHUTZ-ENTWURF.md](DATENSCHUTZ-ENTWURF.md)     | Datenschutzerklärung – vor Veröffentlichung noch rechtlich und betrieblich zu prüfen |
| [KONZEPT-DATENMODELL.md](KONZEPT-DATENMODELL.md)     | Felder, Tabellen, Statusachsen, Import                                               |
| [KONZEPT-API.md](KONZEPT-API.md)                     | REST-API, Synchronisation, Intranet als Abnehmer                                     |
| [KONZEPT-STACK.md](KONZEPT-STACK.md)                 | Technik, SQLite, Anmeldung                                                           |
| [KONZEPT-DESIGN.md](KONZEPT-DESIGN.md)               | Auftritt nach CI, GUI-Bausteine aus dem Intranet                                     |
| [KONZEPT-HOSTING.md](KONZEPT-HOSTING.md)             | Hostinger, Cloudflare, GitHub Pages                                                  |
| [KONZEPT-VERSIONIERUNG.md](KONZEPT-VERSIONIERUNG.md) | Versionen, Versionshistorie                                                          |
| [KONZEPT-CONFLUENCE.md](KONZEPT-CONFLUENCE.md)       | Confluence-Publisher (später)                                                        |
| [KONZEPT-MCP.md](KONZEPT-MCP.md)                     | MCP-Server für Rovo (später)                                                         |

Kennzeichnung: **Entscheid** = vom Projektverantwortlichen festgelegt · **Vorschlag** = mit der Abnahme vom
07.11.2026 als Planungsgrundlage übernommen, Einzelheiten werden bei der Umsetzung im jeweiligen Issue festgelegt ·
**offen** = wird geklärt, wenn benötigt.

## Ziel

Ein öffentliches, durchsuchbares Glossar für Schweizer Volksschulen, Schulgemeinden und Gemeinden: Abkürzungen und
Begriffe mit Erklärung, Quelle und Gültigkeit. Teil der Produktfamilie **EduKSX** (Dachmarke MarKusSXCH), erreichbar
unter `glossary.eduksx.ch`.

- **Entscheid:** Eigenständige Webanwendung, lesen ohne Anmeldung (DEC-001).
- **Entscheid:** Das Glossar ist das **führende System**. Intranet, Confluence und weitere Systeme erhalten nur
  veröffentlichte Daten und schreiben nicht direkt zurück (DEC-004).
- **Entscheid:** Eine versionierte REST-API gehört zum Kern und ist ab dem ersten nutzbaren Release da. Erster
  Pflicht-Abnehmer ist das Intranet, das regelmässig synchronisiert (DEC-005).
- Zielgruppen: Schulverwaltungen, Schul-ICT, Schulleitungen, Lehrpersonen, Behörden, Gemeinden, Fachstellen,
  interessierte Öffentlichkeit, Entwickler:innen.

**Nicht Ziel:** amtlich verbindliche Erklärungen, Ersatz für Rechtsquellen, Veröffentlichung durch KI ohne Prüfung,
Personendossiers, Mandantenfähigkeit im ersten Schritt.

## Inhalt: Felder

**Entscheid:** Die Felder des Intranet-Glossars sind die Ausgangsbasis (DEC-002).

| Feld                               | Regel                                                               |
| ---------------------------------- | ------------------------------------------------------------------- |
| Kategorien                         | eine oder mehrere aus der erweiterbaren Kategorienliste             |
| Abkürzung offiziell + Aliase       | geschützt: ändern nur mit Recht «offizielle Begriffe pflegen»       |
| Alternative Schreibweisen          | gemeinsam gültig, redaktionell geprüft, mehrere (DEC-020)           |
| Bezeichnung offiziell              | geschützt                                                           |
| Kurzbeschreibung                   | ein Satz, max. 160 Zeichen (Tooltip im Intranet)                    |
| Beschreibung                       | ausführliche Erklärung, eigene Worte, mit Quellenbezug              |
| Quelle (URL) und Herkunft          | mehrere Herkünfte aus der Herkunftsliste (VSA, Kanton, Bund, DSB …) |
| Notiz                              | redaktionell, Markdown                                              |
| Status                             | aktuell / historisch / zu prüfen                                    |
| Suchbegriffe, gültig ab/bis, aktiv | Suche, zeitliche Geltung, ein-/ausblenden                           |

Regeln wie im Intranet (**Entscheid**):

- **Mehrdeutige Abkürzungen** sind eigene Einträge (SHP, SL, SV …).
- **Doppelte** nur zusammenführen, wenn Abkürzung **und** Bedeutung gleich sind – nie automatisch. Kategorien,
  Aliase und Herkunft werden vereinigt.
- Offizielle Felder sind geschützt, jede Änderung steht im Verlauf.

**Vorschlag:**

- Statusachsen getrennt halten: Status des Begriffs (aktuell/historisch/zu prüfen), Stand eines Vorschlags
  (Moderation) und **veröffentlichte Revision** (was Website und API zeigen).
- Jede Veröffentlichung ist eine unveränderliche **Revision**; Website und API zeigen immer die zuletzt
  freigegebene. Ein Änderungsvorschlag lässt die veröffentlichte Revision unberührt, bis die neue freigegeben ist.
- Eigene Bezeichnungen je Schule (Mandant) später als eigene Ebene, nicht als globaler Wert.

**Entscheid DEC-020 (07.11.2026):** Eigene Abkürzung, eigene Bezeichnung, eigener Alias und «Genutzt in» sind keine
gemeinsamen Felder mehr. Persönliche Formen setzt jede angemeldete Person für sich («Meine Darstellung»); das
Intranet verwaltet seine Modulverwendungen und lokalen Benennungen selbst. Details in
[KONZEPT-DATENMODELL.md](KONZEPT-DATENMODELL.md#gemeinsame-und-persönliche-daten-änderungsjournal-dec-020).

## Funktionen

### Öffentlich (Muss)

- Suche, Index A–Z, Kategorien und Filter; mehrdeutige Einträge nebeneinander.
- Detailseite mit Abkürzung, Bezeichnung, Kurzbeschreibung, Beschreibung, Quelle, Status und Gültigkeit.
- Direktlink je Eintrag (sprechende Adresse, z. B. `/begriffe/klassenlehrperson`), teilen.
- Mobil nutzbar, barrierearm (WCAG 2.1 AA), Tastatur; hell/dunkel.
- Seite **«Versionen»** mit dem Verlauf aus dem CHANGELOG (siehe [KONZEPT-VERSIONIERUNG.md](KONZEPT-VERSIONIERUNG.md)).

### Redaktion (Muss)

- Begriffe, Kategorien und Herkunftsliste pflegen; Tabelle wie im Intranet (Suche, Filter, Gruppierung,
  Bearbeiten in der Zelle), Detail- und Formularansicht.
- **Excel (XLSX) Import und Export** wie im Intranet: Vorschau (neu / geändert / Fehler), Update über die ID, nie
  löschen; leere Vorlage. **Vorschlag:** zusätzlich CSV; Formeln beim Export als Text schützen.
- Knopf «Doppelte Abkürzungen oder Begriffe» mit manuellem Zusammenführen.
- Verlauf (Audit) aller Änderungen und Freigaben.
- Startinhalt: die rund 140 Einträge aus dem Intranet (Recherche VSA vom 03.11.2026), mit Herkunft und Status
  «zu prüfen», wo die Recherche das verlangt.

### Community (Muss für den ersten Betrieb)

- Neuen Begriff vorschlagen, Änderung an einem Eintrag beantragen.
- Veröffentlicht wird **nur nach Freigabe** durch Projektverantwortliche:n bzw. Admin-Gruppe (DEC-003).
- **Vorschlag – Ablauf:** eingereicht → in Prüfung → angenommen → veröffentlicht; daneben Rückfrage, abgelehnt
  (mit Begründung), zurückgezogen. Vorschlags-Nummer zum Nachverfolgen.
- Vorschläge innerhalb der Anwendung setzen ein bestätigtes persönliches Benutzerkonto voraus (DEC-017).
- Schutz vor Missbrauch: Rate-Limit, Spam-Schutz (z. B. Cloudflare Turnstile), keine öffentliche Anzeige von
  E-Mail oder IP.
- Bis zur Umsetzung bleiben die **GitHub-Issue-Formulare** der Weg für Vorschläge.
- **GitHub Pages** bleibt dauerhaft als reduzierte, statische Lesefassung (Export des veröffentlichten Datenstands,
  [KONZEPT-HOSTING.md](KONZEPT-HOSTING.md)).

## Recherche, Quellen und Qualität

Ausgangspunkt: Volksschulamt des Kantons Zürich, Datenschutzbeauftragte des Kantons Zürich, Gemeindeamt, weitere
kantonale Stellen, interne Schulterminologie; vorhandene Begriffssammlungen werden konsolidiert. Ergebnisse einer
Recherche gelten **nicht automatisch** als offizielle Definition.

**Vorschlag – Regeln:** Herkunft und URL für jeden nicht trivialen Eintrag, Prüfdatum der Quelle; Kennzeichen
«offiziell» nur bei belegter amtlicher Form; Kurzbeschreibung in eigenen Worten, nicht aus fremden Texten kopiert;
Liste der Doppelten vor der Veröffentlichung; Mehrdeutigkeiten bewusst erhalten; jährliche Wiedervorlage für
veränderliche Begriffe; Änderungsjournal.

## Benutzer, Rollen und Zugangsregeln (DEC-017)

Die verbindlichen Regeln zu persönlichen Konten, geschützter Developer-Rolle, Berechtigungen, verpflichtender MFA, getrennten Servicekonten und lokalen beziehungsweise föderierten Anmeldungen stehen in [KONZEPT-BENUTZER.md](KONZEPT-BENUTZER.md). Öffentliche Lesezugriffe benötigen kein Konto. Vorschläge innerhalb der Anwendung benötigen ein bestätigtes persönliches Konto. GitHub-Issue-Formulare bleiben bis zur Community-Umsetzung als Übergangskanal bestehen.

## Schnittstellen

```text
Recherche / Excel / Community ─► Prüfung ─► Freigabe ─► veröffentlichte Revision ─► Website
                                                                               ├─► /api/v1 ─► Intranet (Pflicht)
                                                                               ├─► Confluence-Publisher (später)
                                                                               └─► MCP für Rovo (später)
```

- **REST-API `/api/v1`** (Entscheid, DEC-005): nur veröffentlichte Daten, JSON, OpenAPI 3.1, stabile UUID und
  Slugs, Pagination, ETag/304, Fehler als `application/problem+json`, Rate-Limit. Lesen ohne Schlüssel;
  Schreiben (Vorschläge) geschützt. Endpunkte und Sync-Vertrag: [KONZEPT-API.md](KONZEPT-API.md).
- **Intranet:** holt die Daten regelmässig ab (aktuelles API-Konzept empfiehlt 5–15 Minuten) über Vollstand und Änderungen seit
  `since` (inkl. zurückgezogener Einträge), behält bei Fehlern den letzten gültigen Stand. Tooltip und Erkennung im
  Freitext bleiben im Intranet; `/glossar/begriffe` wird dort zum Adapter. Änderungen aus dem Intranet kommen als
  Vorschläge.
- **Confluence** (Entscheid DEC-006/007): nur Empfänger, nachrangig; [KONZEPT-CONFLUENCE.md](KONZEPT-CONFLUENCE.md).
- **MCP für Rovo** (Entscheid DEC-008): in der Architektur vorgesehen, nicht im ersten Release; Rovo kann nur
  vorschlagen, nicht veröffentlichen; [KONZEPT-MCP.md](KONZEPT-MCP.md).
- **Vorschlag:** Website, API, Import und später MCP rufen dieselbe Service-Schicht auf (eine Stelle für Regeln,
  Rechte und Verlauf). Kein Zugriff anderer Systeme auf die Datenbank.

## Betrieb

- Hosting: [KONZEPT-HOSTING.md](KONZEPT-HOSTING.md) (Entscheid DEC-013): Hostinger-VPS, `glossary.eduksx.ch`,
  GitHub Pages als reduzierte Fassung.
- Stack: [KONZEPT-STACK.md](KONZEPT-STACK.md).
- Auftritt und GUI-Bausteine: [KONZEPT-DESIGN.md](KONZEPT-DESIGN.md) – Auftritt nach CI MarKusSXCH,
  Bausteine aus dem Intranet übernommen, nicht neu entwickelt.
- Issues, Versionen, Releases: Blueprint-Ablauf ([GITHUB-ABLAUF.md](GITHUB-ABLAUF.md)),
  [KONZEPT-VERSIONIERUNG.md](KONZEPT-VERSIONIERUNG.md).

## Datenschutz und Sicherheit

- Öffentliche Fachdaten und personenbezogene Konten, private Vorschläge, Moderationsinformationen und Sicherheits-Audits werden strikt getrennt (DEC-018).
- Für persönliche Konten gelten die für Anmeldung und Kommunikation erforderlichen Pflichtattribute gemäss [KONZEPT-BENUTZER.md](KONZEPT-BENUTZER.md). Freiwillige Community-Angaben bleiben optional.
- Die öffentliche API, Exporte, GitHub Pages und spätere Confluence-Ausgaben verwenden ausschliesslich geprüfte Veröffentlichungsfelder; keine privaten Identitäts- oder Moderationsdaten.
- MFA für privilegierte Personenrechte, serverseitige Berechtigungsprüfung, Eingaben validieren, Markdown sicher darstellen, Excel-Uploads prüfen, Secrets nicht im Repository, Dependabot/CodeQL, Sicherungen mit Wiederherstellungstest.
- [DATENSCHUTZ-ENTWURF.md](DATENSCHUTZ-ENTWURF.md) bleibt bis zur rechtlichen und betrieblichen Prüfung Entwurf. Datenstandorte, Auftragsbearbeitung, Auslandübermittlungen und Aufbewahrung sind vor dem öffentlichen Betrieb zu klären (DEC-019).

## Lizenzen

- **Software:** MIT (DEC-021, beschlossen 07.11.2026, Datei `LICENSE`). Code aus dem Intranet nur übernehmen, wenn
  die Rechte und Fremdlizenzen geprüft sind.
- **Daten:** CC BY 4.0 (DEC-016, vorläufig); Zitierumfang und Nachnutzung fremder Begriffslisten prüfen.

## Etappen

| Etappe | Inhalt                                                                                        | Abnahme                                        |
| ------ | --------------------------------------------------------------------------------------------- | ---------------------------------------------- |
| 0.1    | Konzept, Hosting, Stack, Versionierung; Programmiergerüst und Deployment                      | Gerüst läuft auf `glossary.eduksx.ch`          |
| 0.2    | Datenmodell, Übernahme der Intranet-Daten, Import/Export                                      | Startinhalt importiert, nichts still gelöscht  |
| 0.3    | Öffentliche Website und API `/api/v1` mit OpenAPI und Sync                                    | Sync mit dem Intranet automatisch getestet     |
| 0.4    | Benutzerverwaltung, Anmeldung (Registrierung, Microsoft), Datenschutz                         | erledigt mit Release 0.4.0 (07.11.2026)        |
| offen  | Community: Vorschläge, Moderation, Verlauf (Version folgt nach Prüfung der Datenbankstruktur) | kein Vorschlag ohne Freigabe öffentlich        |
| danach | Intranet als Abnehmer (Adapter, Cache, Fallback)                                              | Sync inkl. Rückzug und Wiederaufnahme getestet |
| 1.0    | erster öffentlicher Betrieb (Datenschutz, Sicherung, Überwachung)                             | Freigabe für den Betrieb                       |
| später | Confluence-Publisher, MCP für Rovo, Mandanten                                                 | eigene Konzepte                                |

Termine erst mit Ressourcenplanung.

## Offene Entscheide

1. Stack und Datenbank ([KONZEPT-STACK.md](KONZEPT-STACK.md)).
2. Community-Anmeldung entschieden (DEC-017) und mit 0.4.0 umgesetzt (Registrierung mit Double-Opt-in, Microsoft). Offen: Microsoft-App-Tenant für den Betrieb und Edulog-Aufnahme (#46).
3. Rechte- und Lizenzprüfung bei Übernahme aus dem Intranet. Softwarelizenz MIT (DEC-021), Datenlizenz CC BY 4.0 (DEC-016).
4. ~~Freigabe-, Versions- und Rollback-Regeln für Revisionen.~~ DEC-022 (#74), siehe [KONZEPT-DATENMODELL.md](KONZEPT-DATENMODELL.md#freigabe-und-rücknahme-von-revisionen-dec-022).
5. ~~API: Nutzungsbedingungen, Gesamt-Export, Rate-Limits, Stabilitätszusage.~~ Entschieden mit DEC-016 (#30).
6. Mandanten-Bezeichnungen und -Notizen.
7. Umgang mit bestehenden GitHub-Issues beim Start der Vorschlagsfunktion.
8. Confluence-Format und Mehrfachziele; Rovo-MCP (Transport, Identität, Rechte).
