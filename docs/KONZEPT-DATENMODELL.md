# Konzept: Datenmodell

**Abgenommen** am 07.11.2026, aus Konzept V1.2 (Abschnitte 3 und 6) und dem Glossar-Modul des Intranets
(`docs/KONZEPT-GLOSSAR.md`, `app/Support/Glossary.php`). Entscheide DEC-002 und DEC-010.

Speicher: **SQLite** (eine Datei im Docker-Volumen), Migrationen von Laravel. Kein PostgreSQL. JSON dient als
Austausch- und Startformat (Übernahme aus dem Intranet, Export, GitHub Pages).

## Felder aus dem Intranet (beschlossen: Ausgangsbasis)

| Feld im Intranet                    | Zweck                                  | Besonderheit                           |
| ----------------------------------- | -------------------------------------- | -------------------------------------- |
| `kategorien`                        | Mehrfachzuordnung                      | erweiterbare Liste                     |
| `abk_offiziell`, `aliase_offiziell` | offizielle Abkürzung und Schreibweisen | ändern nur mit Sonderrecht             |
| `abk_eigen`, `alias_eigen`          | lokal bzw. anders verwendete Form      | DEC-020: kein gemeinsames Feld mehr    |
| `bez_offiziell`, `bez_eigen`        | offizielle bzw. eigene Bezeichnung     | DEC-020: nur noch die offizielle       |
| `kurzbeschreibung`                  | Tooltip                                | max. 160 Zeichen                       |
| `beschreibung`                      | ausführliche Erklärung                 | mit Quellenbezug                       |
| `url`, `herkunft`                   | Beleg, Quelleninstitution              | mehrere Herkünfte möglich              |
| `notiz`                             | redaktionelle Ergänzung                | Markdown                               |
| `genutzt_in`                        | Bezüge zu Anwendungen                  | DEC-020: entfällt, Sache des Intranets |
| `status`                            | aktuell, historisch, zu prüfen         | nicht dasselbe wie die Freigabe        |
| `suchbegriffe`                      | Suche                                  |                                        |
| `gueltig_ab`, `gueltig_bis`         | zeitliche Geltung                      | optional                               |
| `aktiv`                             | anzeigen/ausblenden                    | getrennt von der Veröffentlichung      |

Regeln (beschlossen, wie im Intranet): mehrdeutige Abkürzungen erlaubt; Doppelte nur bei gleicher Abkürzung **und**
Bedeutung zusammenführen, nie automatisch, Kategorien/Aliase/Herkunft bleiben erhalten; offizielle Felder geschützt;
jede Änderung nachvollziehbar.

## Tabellen (Vorschlag)

- `terms`: `id` (UUID v7), `slug`, `official_abbreviation`, `official_name`, `short_description`, `description`,
  `note`, `url`, `search_terms`, `lifecycle_status`, `is_active`, `valid_from`, `valid_until`, Zeitstempel (DEC-020:
  ohne `own_*` und `used_in`).
- `term_aliases`: `term_id`, `alias`, `alias_type` (`official`, `alternative`, `search`), Sprache; **nicht
  eindeutig**, weil Mehrdeutigkeit erlaubt ist.
- `term_user_overrides`: persönliche Darstellung je Begriff und Konto (DEC-020).
- `term_legacy_values`: gesicherte Altwerte aus dem Intranet-Format zur Zuordnung (DEC-020).
- `categories`, `term_categories`: mehrere Kategorien je Begriff.
- `sources`, `term_sources`: Institution/Herkunft, URL, Abrufdatum, Hinweis, Belegstatus.
- `term_revisions`: unveränderliche Abbilder je Revision: Änderungsart, Nummer, Autor/System, Zeit, freigegeben
  durch, veröffentlicht am; dazu das feldgenaue Journal (`change_set`, `actor_type`, `context`, DEC-020).
- `proposals`: neuer Begriff oder Änderung, Ziel-Begriff und Basisrevision, Änderung als JSON, Status, Begründung,
  Bezug zur einreichenden Person, Zeitstempel.
- `proposal_events`: Statuswechsel, Prüfvermerke, Ablehnungsgründe, Kommentare; nicht öffentlich.
- `users`, `roles`, `permissions`/`role_permissions`, `user_roles`, `user_identities`, `user_audit`: persönliche Benutzerkonten und Berechtigungen; Details in [KONZEPT-BENUTZER.md](KONZEPT-BENUTZER.md).
- `service_accounts`, `service_account_tokens`, `service_account_audit`: eigenständige Maschinenidentitäten; getrennt von persönlichen Benutzerkonten, statt einer `API`-Benutzerrolle.
- `user_consents`: gesonderte Einwilligungen, soweit erforderlich; Datenschutzkenntnisnahme mit Version und Zeitpunkt getrennt dokumentieren.
- `import_batches`, `import_rows`: Datei-Angaben, Vorschau, Entscheide, Fehler; befristet aufbewahrt.
- `publication_releases`: Datenstand mit Nummer, Zeitpunkt, Prüfsumme; `service_accounts` für authentisierte technische Schreibzugriffe; keine parallele Tabelle `api_clients`.
- später `term_relations`: verwandt, Synonym, ersetzt durch.

Abbildung: Die deutschen Feldnamen des Intranets werden beim Import auf diese Spalten abgebildet (Tabelle oben);
`bez_eigen`/`abk_eigen`, `alias_eigen` und `genutzt_in` werden seit DEC-020 nicht mehr übernommen, sondern als
Altwerte gesichert und gemeldet. Eigene Bezeichnungen je Schule (Mandant) später als eigene Ebene.

## Drei Statusachsen

| Achse                         | Werte                          | Wirkung                    |
| ----------------------------- | ------------------------------ | -------------------------- |
| `terms.lifecycle_status`      | aktuell, historisch, zu prüfen | Anzeige und Filter         |
| `proposals.status`            | eingereicht … veröffentlicht   | Moderation                 |
| `term_revisions.published_at` | gesetzt = öffentlich           | was Website und API zeigen |

Unveröffentlichte Entwürfe und Vorschläge sind über Website und API nicht abrufbar.

## Import

- Stabile IDs behalten; Format der Excel-Datei versionieren; Unbekanntes melden.
- Beim Zusammenführen die alte ID als Weiterleitung bzw. Alias behalten.
- Nie löschen durch fehlende Zeilen.
- Formeln beim Export als Text schützen (CSV-/Excel-Injection); Dateityp und Grösse prüfen, keine Makros.

## SQLite im Betrieb

- Datei im Volumen (`storage/app/database/glossary.sqlite`), WAL-Modus für gleichzeitiges Lesen.
- Sicherung: konsistente Kopie mit `sqlite3 .backup` bzw. `VACUUM INTO`, verschlüsselt ausser Haus; Ziel und
  Intervall werden geklärt, wenn benötigt.
- Für die erwartete Datenmenge (einige tausend Begriffe, wenige gleichzeitig Schreibende) reicht SQLite vollauf.

## Umsetzung (#12, 07.11.2026)

- Migration `2026_11_07_000100_create_glossary_tables.php`, Models `Term`, `TermAlias`, `TermRevision`,
  `Category`, `Source`, `PublicationRelease`; Regeln nur in `App\Glossary\GlossaryService`.
- **Listen** (Kategorien, Herkunft) mit Schlüssel (`key`, z. B. `beh`, `vsa`), Kürzel, Bezeichnung, Label, Farbe –
  wie im Intranet, damit der Excel-Export des Intranets (Labels mit «;») direkt passt (#13).
- **IDs:** Beim Anlegen kann eine bestehende UUID mitgegeben werden (ID aus dem Intranet bleibt gleich).
- **Revisionen:** jede inhaltliche Änderung eine neue Revision mit vollständigem Abbild; Inhalt unveränderlich, nur
  die Freigabe wird gesetzt. Ohne Änderung entsteht keine Revision.
- **Veröffentlichen** setzt `terms.published_revision_id`; Website und API lesen nur diese Revision. Jede
  Veröffentlichung und jeder Rückzug erzeugt einen Datenstand (`publication_releases`) mit Prüfsumme.
- **Offizielle Felder** (Abkürzung, Bezeichnung, Aliase offiziell): ohne Recht `edit-official` nur leere Felder
  setzen. Vorgabe des Rechts: Administration.
- ~~`genutzt_in` als JSON-Liste (`used_in`)~~ (entfällt mit DEC-020), Suchbegriffe zusätzlich als Aliase vom Typ
  `search`.
- **Doppelte (#16):** Seite «Doppelte» zeigt Gruppen gleicher Abkürzung bzw. Bezeichnung (Vergleich ohne Gross-/
  Kleinschreibung und Leerraum); nichts geschieht automatisch. «Mehrdeutig belassen» wird je Gruppe gemerkt
  (`duplicate_decisions`) und gilt, solange die Mitglieder gleich bleiben. «Zusammenführen»: ein Begriff bleibt
  (ID, Slug), Kategorien, Herkunft und Aliase werden vereinigt, Texte je Feld gewählt;
  Abkürzungen und Bezeichnungen der anderen werden Suchbegriffe. Die anderen erhalten `merged_into_id` und sind nur
  noch Weiterleitung (Website und API 301, Excel-Import meldet die neue ID). War einer veröffentlicht, wird das
  Ergebnis gleich veröffentlicht – ein Datenstand.
- Noch nicht umgesetzt: `proposals`/`proposal_events` (Meilenstein «Vorschläge und Moderation», Datenbankstruktur
  wird vorher nochmals geprüft), `term_relations` (später). Die Import-Vorschau liegt bisher nur befristet im
  Zwischenspeicher statt in `import_batches` (eigenes Issue).

## Identitätsmodell, Datenschutz und Veröffentlichungsgrenze (DEC-017 bis DEC-019)

- Externe Identitäten werden eindeutig nach Anbieter, vertrauenswürdigem Aussteller und stabilem Subject zugeordnet. Eine gleiche E-Mail-Adresse reicht nicht für automatische Kontoverknüpfungen.
- Bei der Migration wird `is_admin` ausschliesslich in die Rolle Admin überführt. Die Developer-Rolle wird nur über geschützte Verfahren vergeben. Für effektiv privilegierte Konten gilt MFA unabhängig von einer veränderbaren Rollenmatrix.
- Die Kenntnisnahme der Datenschutzerklärung wird mit `privacy_notice_version` und `privacy_notice_acknowledged_at` dokumentiert. Erforderliche Einwilligungen sind separat mit Zweck, Fassung, Erteilung und gegebenenfalls Widerruf zu speichern.
- `proposals` und `proposal_events` enthalten interne Personenzuordnungen und Moderationsinformationen; diese gelangen nicht automatisch in veröffentlichte Revisionen.
- Öffentliche Ausgaben erfolgen nur über explizit definierte DTOs/Resources für veröffentlichte Revisionen (z. B. `PublicTermResource`). Website, API, Voll-Export, Change-Feed und GitHub Pages verwenden dieselbe Freigabegrenze.
- Die Tabellen und Migrationen dieses Abschnitts sind **Planungssoll**, soweit sie nicht ausdrücklich als umgesetzt dokumentiert sind. Die bestehende SQLite-Architektur bleibt unverändert.

## Gemeinsame und persönliche Daten, Änderungsjournal (DEC-020)

**Beschlossen 07.11.2026** (Entwicklungsübergabe «Datenmodell, Personalisierung und Änderungsjournal», #79–#83).
Ersetzt für diese Punkte die unveränderte Übernahme der Intranet-Felder aus DEC-002.

### Grundsatz

- Ein Glossary-Eintrag enthält nur **gemeinsame Fachdaten**. Persönliche oder systemabhängige Anpassungen sind keine
  Eigenschaft des Eintrags.
- `used_in` («Intern genutzt in») entfällt vollständig. Jede Intranet-Instanz ermittelt ihre Modul- und
  Bereichsverwendungen selbst; es gibt kein zentrales `term_usages`.
- `own_abbreviation`, `own_name` und Aliase vom Typ `own` entfallen. Gemeinsam gültige Varianten sind
  **alternative Schreibweisen** (`term_aliases.alias_type = alternative`, mehrere je Begriff, redaktionell geprüft,
  öffentlich).
- Kategorien und Herkünfte bleiben relational. `term_relations` folgt später.

### Persönliche Darstellung (`term_user_overrides`)

| Spalte                       | Regel                                               |
| ---------------------------- | --------------------------------------------------- |
| `id`                         | UUID                                                |
| `term_id`, `user_id`         | FK, `ON DELETE CASCADE`; `UNIQUE(term_id, user_id)` |
| `abbreviation_override`      | String 60, NULL = gemeinsamer Wert                  |
| `name_override`              | String 255                                          |
| `short_description_override` | String 160                                          |
| `description_override`       | Text                                                |
| `aliases_override`           | JSON-Liste, mehrere persönliche Schreibweisen       |

- Gesetzt auf der Begriffsseite unter **«Meine Darstellung»** von jedem angemeldeten, aktiven Konto, nur für sich
  selbst (Zugriff ausschliesslich über `user_id = angemeldetes Konto`). Keine Moderation, keine Revision.
- **Anzeige:** Auf der öffentlichen Website gelten die eigenen Anpassungen nur, wenn man angemeldet ist (Liste,
  Suche und Detail; die gemeinsame Form bleibt sichtbar). Solche Antworten tragen `Cache-Control: private,
no-store`. Abgemeldet und für alle anderen gilt die gemeinsame Form.
- Nie in Revisionen, Datenständen, API, Vollstand, Change-Feed, GitHub Pages, Confluence oder Excel.
- Beim Zusammenführen gehen Anpassungen auf den verbleibenden Begriff über; hat dieselbe Person dort schon eine,
  werden nur leere Felder ergänzt, bei Widerspruch bleibt die Anpassung am alten Begriff (Meldung, nichts wird
  still überschrieben).
- Wird mit dem Konto gelöscht.

### Änderungsjournal (`term_revisions`)

Das bestehende Revisionssystem wird ausgebaut, kein zweites:

| Spalte         | Inhalt                                                                                  |
| -------------- | --------------------------------------------------------------------------------------- |
| `change_set`   | Feld → `{old, new}`; Listen sortiert, Leer/NULL/[] gleich; beim Anlegen alle gesetzten  |
| `actor_type`   | Rolle zum Zeitpunkt: community, moderator, admin, developer, service, system            |
| `author_label` | Community-Bezeichnung (sonst Name) als **Text**, kein Fremdschlüssel                    |
| `author_id`    | technischer Bezug, `ON DELETE SET NULL`                                                 |
| `context`      | z. B. `{"via": "excel"}`, `{"via": "altwerte"}`, Zusammenführung mit Quell-IDs und Wahl |

- Gleiches Ergebnis → keine Revision. Abbilder enthalten nur gemeinsame Felder (neu `alternative_aliases`).
- `term_publication_events` (Veröffentlichen, Zurückziehen, Zusammenführen) erhalten `actor_type`, `actor_label`,
  `actor_id` – nur intern, nie im Change-Feed. Revisionen und Ereignisse bleiben getrennte Achsen.
- Journal nur für Redaktion und Administration (Verlauf in der Begriffsansicht mit altem und neuem Wert). Kein
  öffentliches Feldprotokoll; öffentlich sichtbare Beitragsangaben bräuchten einen eigenen Datenschutzentscheid.
- **Kontolöschung:** persönliche Darstellungen werden gelöscht; im Journal wird die Bezeichnung, die die Person
  nennt, durch «Gelöschtes Konto» ersetzt und `author_id`/`approved_by`/`actor_id` geleert. Das ist die einzige
  dokumentierte Ausnahme von der Unveränderlichkeit der Revisionen. Inhalt, Zeit und Werte bleiben. Bezeichnungen
  ohne Personenbezug (z. B. «Excel-Import» aus älteren Revisionen) bleiben.

### Berechnete Anzeigewerte (`App\Glossary\GlossaryDisplay`)

Nicht gespeichert, eine Regel für Website und API:

- `display_label` = Abkürzung, sonst Bezeichnung; `is_full_term` = keine Abkürzung, aber Bezeichnung.
- `meaning_count` = Anzahl unterschiedlicher Bedeutungen der Abkürzung im jeweiligen Sichtstand (gleiche
  normalisierte Bezeichnung zählt einmal, Kategorien zählen nicht); `is_ambiguous` = mehr als eine.
- Öffentlich zählt nur der veröffentlichte, aktive Stand. Im Change-Feed fehlen `meaning_count` und
  `is_ambiguous`, weil ein altes Ereignis sonst Zahlen eines späteren Stands zeigen würde.

### Migration und Altbestand

- Migration `2026_11_07_001200_shared_and_personal_data.php`: legt `term_user_overrides`, `term_legacy_values` und die
  Journal-Spalten an, sichert **jeden** vorhandenen Wert aus `own_abbreviation`, `own_name`, Aliasen `own` und
  `used_in` (je Wert eine Zeile) nach `term_legacy_values` und entfernt danach die Spalten. Der Rückweg (`down`)
  stellt Spalten und Werte aus der Sicherung wieder her. Vor dem Update sichert der Container die Datenbank.
- **Keine automatische Zuordnung:** Altwerte werden weder einem Konto noch einem offiziellen Feld zugeschlagen. Die
  Redaktion ordnet sie auf der Seite **«Altwerte»** zu: als offizielle Angabe (nur wenn das Feld leer ist), als
  alternative Schreibweise, als Suchbegriff – oder verwirft sie. «Intern genutzt in» lässt sich nur verwerfen und
  vorher als CSV für das Intranet herunterladen. Übernahmen ergeben einen Entwurf (Revision, Journal) und werden wie
  jede Änderung veröffentlicht.
- Bis zur Zuordnung zeigen Website und API nur die gemeinsamen Werte. Begriffe, die bisher nur eine eigene Abkürzung
  hatten, erscheinen deshalb vorerst ohne Abkürzung.
- **Bestand:** `php artisan glossary:bestand-eigene [--csv=datei.csv] [--sicherung=kopie.sqlite]` zeigt Anzahl je
  Feld (offen/zugeordnet), Begriffe ohne gemeinsame Abkürzung und Revisionen mit Altfeldern; schreibt auf Wunsch
  eine CSV und vorher eine konsistente Kopie der Datenbank.
- Historische Revisionen bleiben unverändert und können Altfelder enthalten; öffentliche Ausgaben lesen nur über
  Positivlisten und geben sie nie aus.

### Excel-Format Version 2

- Spalten wie bisher, ohne «Abkürzung eigene», «Alias eigene», «Bezeichnung eigene», «Intern genutzt in»; neu
  «Alternative Schreibweisen» (Mehrfachwerte mit «;»).
- Dateien im alten Intranet-Format werden erkannt: die alten Spalten werden **nicht zentral übernommen**, in der
  Vorschau je Zeile gemeldet und beim Übernehmen als Altwerte gesichert (kein stiller Verlust, kein Raten). Eine
  neue Zeile nur mit eigener Abkürzung/Bezeichnung ist ein Fehler.
- Persönliche Darstellungen werden nicht über Excel importiert oder exportiert.
