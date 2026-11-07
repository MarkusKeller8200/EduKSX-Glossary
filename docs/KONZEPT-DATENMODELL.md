# Konzept: Datenmodell

**Abgenommen** am 07.11.2026, aus Konzept V1.2 (Abschnitte 3 und 6) und dem Glossar-Modul des Intranets
(`docs/KONZEPT-GLOSSAR.md`, `app/Support/Glossary.php`). Entscheide DEC-002 und DEC-010.

Speicher: **SQLite** (eine Datei im Docker-Volumen), Migrationen von Laravel. Kein PostgreSQL. JSON dient als
Austausch- und Startformat (Übernahme aus dem Intranet, Export, GitHub Pages).

## Felder aus dem Intranet (beschlossen: Ausgangsbasis)

| Feld im Intranet                    | Zweck                                  | Besonderheit                          |
| ----------------------------------- | -------------------------------------- | ------------------------------------- |
| `kategorien`                        | Mehrfachzuordnung                      | erweiterbare Liste                    |
| `abk_offiziell`, `aliase_offiziell` | offizielle Abkürzung und Schreibweisen | ändern nur mit Sonderrecht            |
| `abk_eigen`, `alias_eigen`          | lokal bzw. anders verwendete Form      | getrennt von der offiziellen          |
| `bez_offiziell`, `bez_eigen`        | offizielle bzw. eigene Bezeichnung     | eigene kann im Intranet Vorrang haben |
| `kurzbeschreibung`                  | Tooltip                                | max. 160 Zeichen                      |
| `beschreibung`                      | ausführliche Erklärung                 | mit Quellenbezug                      |
| `url`, `herkunft`                   | Beleg, Quelleninstitution              | mehrere Herkünfte möglich             |
| `notiz`                             | redaktionelle Ergänzung                | Markdown                              |
| `genutzt_in`                        | Bezüge zu Anwendungen                  | neu unabhängig vom Intranet           |
| `status`                            | aktuell, historisch, zu prüfen         | nicht dasselbe wie die Freigabe       |
| `suchbegriffe`                      | Suche                                  |                                       |
| `gueltig_ab`, `gueltig_bis`         | zeitliche Geltung                      | optional                              |
| `aktiv`                             | anzeigen/ausblenden                    | getrennt von der Veröffentlichung     |

Regeln (beschlossen, wie im Intranet): mehrdeutige Abkürzungen erlaubt; Doppelte nur bei gleicher Abkürzung **und**
Bedeutung zusammenführen, nie automatisch, Kategorien/Aliase/Herkunft bleiben erhalten; offizielle Felder geschützt;
jede Änderung nachvollziehbar.

## Tabellen (Vorschlag)

- `terms`: `id` (UUID v7), `slug`, `official_abbreviation`, `own_abbreviation`, `official_name`, `own_name`,
  `short_description`, `description`, `lifecycle_status`, `is_active`, `valid_from`, `valid_until`, Zeitstempel.
- `term_aliases`: `term_id`, `alias`, `alias_type` (`official`, `own`, `search`), Sprache; **nicht eindeutig**,
  weil Mehrdeutigkeit erlaubt ist.
- `categories`, `term_categories`: mehrere Kategorien je Begriff.
- `sources`, `term_sources`: Institution/Herkunft, URL, Abrufdatum, Hinweis, Belegstatus.
- `term_revisions`: unveränderliche Abbilder je Revision: Änderungsart, Nummer, Autor/System, Zeit, freigegeben
  durch, veröffentlicht am.
- `proposals`: neuer Begriff oder Änderung, Ziel-Begriff und Basisrevision, Änderung als JSON, Status, Begründung,
  Bezug zur einreichenden Person, Zeitstempel.
- `proposal_events`: Statuswechsel, Prüfvermerke, Ablehnungsgründe, Kommentare; nicht öffentlich.
- `users`, `roles`, `permissions`, `user_roles`: Redaktion und Administration.
- `import_batches`, `import_rows`: Datei-Angaben, Vorschau, Entscheide, Fehler; befristet aufbewahrt.
- `publication_releases`: Datenstand mit Nummer, Zeitpunkt, Prüfsumme; `api_clients` nur, wenn Abnehmer sich
  anmelden müssen.
- später `term_relations`: verwandt, Synonym, ersetzt durch.

Abbildung: Die deutschen Feldnamen des Intranets werden beim Import auf diese Spalten abgebildet (Tabelle oben);
`bez_eigen`/`abk_eigen` bleiben als alternative Benennung der gemeinsamen Daten. Eigene Bezeichnungen je Schule
(Mandant) später als eigene Ebene.

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
- `genutzt_in` als JSON-Liste (`used_in`), Suchbegriffe zusätzlich als Aliase vom Typ `search`.
- Noch nicht umgesetzt: `proposals`, `import_batches` (mit #15), `term_relations` (später).
