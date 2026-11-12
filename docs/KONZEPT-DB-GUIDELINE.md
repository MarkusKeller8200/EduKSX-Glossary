# DB-Entwickler-Guideline EduKSX

Stand: 12.11.2026 · Verantwortlich: Markus Keller · Status: **Entwurf zur Abnahme**

Gleichlautend abgelegt in EduKSX-Architektur (`docs/KONZEPT-DB-GUIDELINE.md`, massgebend für die Produktfamilie) und EduKSX-Glossary (`docs/KONZEPT-DB-GUIDELINE.md`). Bearbeitet wird zuerst in EduKSX-Architektur.

## Zweck und Geltungsbereich

Diese Guideline ist die verbindliche Arbeitsanleitung für alle, die an Datenbank, Migrationen und Modellen von EduKSX arbeiten – Menschen und KI-Agenten. Sie fasst die beschlossenen Konzepte aus dem Repo **EduKSX-Glossary** zu Regeln zusammen, die man beim Entwickeln direkt anwenden kann. Sie gilt zuerst für EduKSX-Glossary und ist als Vorlage für die ganze Produktfamilie gedacht (Übernahme ins Plattform-Konzept im Repo EduKSX-Architektur).

**Massgebende Quellen** (bei Widerspruch gilt das Konzept, nicht diese Guideline):

| Dokument | Inhalt | Entscheid |
| --- | --- | --- |
| [KONZEPT-DATENBANK.md](https://github.com/MarkusKeller8200/EduKSX-Glossary/blob/main/docs/KONZEPT-DATENBANK.md) | Namenskonzept N1–N12, Bereinigung, Erweiterungen X1–X4, Migration M0–M6 | DEC-030 |
| [KONZEPT-DATENMODELL.md](https://github.com/MarkusKeller8200/EduKSX-Glossary/blob/main/docs/KONZEPT-DATENMODELL.md) | Tabellen, Statusachsen, Revisionen, gemeinsame vs. persönliche Daten | DEC-002, DEC-010, DEC-020, DEC-022 |
| [KONZEPT-KONVENTIONEN.md](https://github.com/MarkusKeller8200/EduKSX-Glossary/blob/main/docs/KONZEPT-KONVENTIONEN.md) | Technik englisch, Anzeige deutsch; Werte von Status und Arten | K1–K7 |
| [LOESCHKONZEPT.md](https://github.com/MarkusKeller8200/EduKSX-Glossary/blob/main/docs/LOESCHKONZEPT.md) | Fristen und Umsetzung je Datenkategorie | DEC-019 |
| [ENTSCHEIDE.md](https://github.com/MarkusKeller8200/EduKSX-Glossary/blob/main/docs/ENTSCHEIDE.md) | Register aller Entscheide | – |
| [AGENTS.md](https://github.com/MarkusKeller8200/EduKSX-Glossary/blob/main/AGENTS.md) | Projektregeln, u. a. abwärtskompatible Migrationen | DEC-027 U18 |

**Grundsätze in einem Satz je Punkt:**

1. **Technik englisch, Anzeige deutsch** – übersetzt wird nur über Labels im Code, nie in der Datenbank.
2. **Kein Datenverlust** – jede Änderung überführt bestehende Daten, zählt vorher und nachher und bricht bei Abweichung ab.
3. **Öffentlich und privat strikt getrennt** – nur kontrollierte Veröffentlichungsfelder verlassen das System (DEC-018).
4. **Nachvollziehbar** – fachliche Änderungen laufen über unveränderliche Revisionen, nicht über stilles Überschreiben.
5. **Konzept vor Code** – Schemaänderungen, die vom Konzept abweichen, kommen zuerst als Konzept-PR zur Abnahme.

## Plattform: SQLite heute, PostgreSQL-tauglich schreiben

Produktiv läuft **SQLite** (eine Datei im Docker-Volumen, `storage/app/database/glossary.sqlite`); der Wechsel auf **PostgreSQL** ist angekündigt (DEC-028, KONZEPT-DATENBANK O4). Deshalb wird jede Migration und jede Abfrage so geschrieben, dass sie auf beiden läuft.

| Thema | Vorgabe | Wo festgelegt |
| --- | --- | --- |
| Betriebsmodus | WAL (`journal_mode = wal`), `busy_timeout` 5000 ms, Fremdschlüssel eingeschaltet | `config/database.php` |
| Transaktionen | `DEFERRED`; schreibende Abläufe (Revision + Ereignis + Journal) immer in **einer** `DB::transaction()` | KONZEPT-DATENMODELL |
| Sicherung | konsistente Kopie mit `sqlite3 .backup` bzw. `VACUUM INTO`, nie die Datei im laufenden Betrieb kopieren; die letzten 10 vor jedem Update, ausser Haus 30 Tage | Löschkonzept |
| Tests | SQLite im Speicher | KONZEPT-STACK |

**Portabilitätsregeln für den PostgreSQL-Wechsel:**

- Nur den **Schema-Builder** von Laravel verwenden; Roh-SQL in Migrationen nur mit Weiche für beide Treiber.
- Keine SQLite-Eigenheiten ausnutzen: kein `rowid`, keine Vergleiche, die auf SQLite-Sortierung beruhen.
- Gross-/Kleinschreibung in Suchen explizit lösen (normalisierte Spalte oder `lower()`), weil `LIKE` sich zwischen SQLite und PostgreSQL unterscheidet.
- Zeitpunkte als Zeitstempel **mit Zeitzone** (`timestampTz`, auf PostgreSQL `timestamptz`), gespeichert in UTC; Umrechnung in Europe/Zurich nur in der Anzeige.
- Zeichenketten auf PostgreSQL als `text`; Längengrenzen über Validierung und CHECK-Regel, nicht über `varchar(n)`.
- JSON-Spalten nur lesen und schreiben, nicht darin filtern (N12) – so bleiben die JSON-Funktionen beider Systeme irrelevant.

**Strenge Tabellen und CHECK-Regeln ab Beginn:**

- Neue SQLite-Tabellen werden als **`STRICT`** angelegt: SQLite erzwingt dann die deklarierten Typen, statt z. B. Text in einer Zahlenspalte still zu speichern. STRICT erlaubt nur die Typen `INTEGER`, `REAL`, `TEXT`, `BLOB`, `ANY`; dafür gibt es einen eigenen Migrations-Helfer, der die Laravel-Typen darauf abbildet (UUID, Datum, Zeitstempel und JSON als `TEXT`, Ja/Nein als `INTEGER`). Bestehende Tabellen werden mit dem bereinigten Anfangsstand (M6) als STRICT neu angelegt.
- **CHECK-Regeln gelten von Anfang an auf SQLite und PostgreSQL**, nicht erst nach dem Wechsel: erlaubte Enum-Werte, Ja/Nein nur 0/1, Längen (`length(name) <= 255`), Zeiträume (`valid_until IS NULL OR valid_until >= valid_from`). In SQLite lassen sie sich nur beim Anlegen der Tabelle setzen – darum gehören sie in die erste Migration der Tabelle.
- Nach jeder Migration prüfen `PRAGMA integrity_check` und `PRAGMA foreign_key_check` die Daten (im Probelauf M1 und im Test).

**Prototypen:** Für Prototypen ist Speicherung in **JSON-Dateien** erlaubt (wie im Intranet-Prototyp). Auch dann gelten die Namensregeln, die Mehrsprachigkeit und die Historie dieser Guideline, Schreibzugriffe sind gesperrt (Datei-Lock) und werden atomar geschrieben (in temporäre Datei schreiben, dann umbenennen). Der Zugriff läuft über ein Repository, damit der Wechsel auf SQLite nur diese Schicht betrifft. Sobald ein Prototyp produktiv geht, wird er auf die Datenbank umgestellt.

## Namenskonventionen

Alle Namen in der Datenbank sind englisch und `snake_case`; die Regeln N1–N12 aus DEC-030 gelten ohne Ausnahme für neue Tabellen und Spalten.

| Regel | Element | Muster | Richtig | Falsch |
| --- | --- | --- | --- | --- |
| N1 | Sprache | englisch, `snake_case` | `short_description` | `kurzbeschreibung`, `shortDescription` |
| N2 | Tabelle | Plural | `terms`, `proposals` | `term` |
| N2 | Verknüpfung | `<besitzer_singular>_<ziel_plural>` | `term_categories`, `user_roles` | `categories_terms` |
| N2 | Protokoll | `<objekt>_audit` (unveränderlich), `<objekt>_events` (Verlauf) | `user_audit`, `proposal_events` | `user_audits` |
| N3 | Primärschlüssel | immer `id` | `id` | `term_id` als PK |
| N4 | Fremdschlüssel | `<ziel>_id` bzw. `<rolle>_id` | `responsible_user_id` | `responsible` |
| N4 | handelnde Person | `<verb>_by_id` | `approved_by_id` | `approved_by` |
| N6 | Zeitpunkt | `<x>_at` (timestamp) | `published_at` | `published` |
| N6 | Datum | `<x>_on` (date) | `retrieved_on`, `last_login_on` | `retrieved_at` für ein Datum |
| N6 | Zeitraum | `valid_from` / `valid_until` | `valid_from` | `start_date` |
| N7 | Ja/Nein | `is_<x>` / `has_<x>`, nie verneint | `is_active`, `has_password` | `password_set`, `is_not_hidden` |
| N8 | Status / Art | `status` bzw. `<x>_type` | `alias_type`, `relation_type` | `kind`, `typ` |
| N9 | Übersetzung | `<objekt>_translations` mit `locale` | `term_translations` | `terms_fr` |
| N10 | Abweichung | `<feld>_override` | `name_override` | `own_name` |
| N11 | Index | Laravel-Standard `<tabelle>_<spalten>_index` / `_unique` / `_foreign` | `term_aliases_term_id_index` | `idx_alias` |
| N12 | JSON-Spalte | sprechender Name für Abbild/Liste | `snapshot`, `changes`, `details`, `scopes` | `data`, `json` |

**Darum herum (KONZEPT-KONVENTIONEN):**

- Klassen, Methoden und Variablen englisch (K6); deutsche Altnamen werden bei Gelegenheit angeglichen, nicht in einem eigenen Umbau.
- Werte von Status und Arten englisch `snake_case` – identisch in Datenbank, API und Maschinen-Exporten (K2).
- Routen der Website deutsch (`/begriffe`), der API englisch (K5). Excel- und PDF-Spalten deutsch (K4).
- Sprachcodes nach ISO 639-1: `de` (Grundsprache), `fr`, `it`, `en` (K7).

## Normalisierung

Standard ist die dritte Normalform: Jede Information steht an genau einer Stelle; Abweichungen sind nur als begründete, hier aufgeführte Ausnahme erlaubt.

**Regeln:**

1. **Eine Information, eine Stelle:** Jede Spalte hängt vollständig und nur vom Schlüssel ihrer Tabelle ab. Was zu einem anderen Objekt gehört, steht in dessen Tabelle und wird über einen Fremdschlüssel verknüpft.
2. **Keine Listen in einem Feld:** keine kommagetrennten Werte, keine wiederholten Spalten (`alias_1`, `alias_2`). Mehrfachwerte bekommen eine eigene Tabelle (`term_aliases`), n:m-Bezüge eine Verknüpfungstabelle (`term_categories`, `term_sources`).
3. **Abgeleitete Werte werden berechnet, nicht gespeichert** (z. B. `display_label`, `meaning_count`, `is_ambiguous` in `GlossaryDisplay`). Gilt auch für Summen, Zähler und Anzeigetexte.
4. **Auswahlwerte:** feste, vom Code bestimmte Werte als Enum mit CHECK-Regel; Werte, die die Redaktion pflegt (Kategorien, Herkünfte), als eigene Stammdatentabelle mit Übersetzungen.
5. **Gemeinsam und persönlich getrennt:** Abweichungen je Organisation oder Person gehören nie in die Haupttabelle, sondern in `*_overrides` (N10).
6. **Sprachen getrennt:** Übersetzungen nur in `*_translations` (N9), nie als Sprachspalten.
7. **Doppelte Speicherung nur als Ausnahme:** erlaubt für unveränderliche Abbilder, Historie, Namen als Text für die Pseudonymisierung – und für Performance nur, wenn sie mit einer Messung belegt ist. Jede Ausnahme steht in der Tabelle unten, mit Grund und mit der Angabe, wie die Kopie aktuell gehalten wird. Eine Ausnahme ohne Eintrag ist ein Fehler im Review.

**Zulässige Ausnahmen (Stand 0.12.0):**

| Wo | Was ist doppelt | Grund | Wie aktuell gehalten |
| --- | --- | --- | --- |
| `term_revisions.snapshot` | vollständiges Abbild des Begriffs als JSON | Revisionen müssen unveränderlich sein (DEC-022) | gar nicht – bewusst eingefroren |
| `terms.published_revision_id` | Zeiger auf die veröffentlichte Revision | schneller Zugriff auf den öffentlichen Stand | gesetzt in derselben Transaktion wie das Veröffentlichen |
| `author_label`, `actor_label` | Name der handelnden Person als Text neben der ID | lesbar nach Kontolöschung, Pseudonymisierung | ersetzt durch «Gelöschtes Konto» bei Kontolöschung |
| `data_changes.changes`, `term_revisions.change_set` | alte und neue Werte | Historie (wer, wann, was) | unveränderlich; Personendaten werden beim Löschen entfernt |
| `publication_releases` | Datenstand mit Prüfsumme | Abgleich der Konsumenten (API, Intranet) | je Veröffentlichung neu, nie geändert |

**Bereinigt mit 0.12.0:** `terms.search_terms` entfällt; Suchbegriffe stehen nur noch in `term_aliases` (Typ `search`, E1). `term_sources.url` entfällt; die Quelle ist `terms.url` (K1).

## Schlüssel, Fremdschlüssel und Indizes

Fachobjekte bekommen eine UUID, jede Fremdschlüssel-Spalte einen Index, und ein Constraint fehlt nur dort, wo der Verweis das Löschen einer Person überleben muss.

**Primärschlüssel (N3):**

| Art der Tabelle | Schlüssel | Beispiele |
| --- | --- | --- |
| Fachobjekte und Konten (sichtbar in API, Sync, Links) | UUID v7 (`$table->uuid('id')->primary()`, Modell mit `HasUuids`) | `terms`, `proposals`, `organizations`, `users`, `service_accounts` |
| Protokolle, Ereignisse, Nummern, reine Hilfstabellen | fortlaufende Zahl (`$table->id()`) | `user_audit`, `proposal_events`, `publication_releases` |

Der Permalink-Code eines Begriffs (`/g/{code}`, 6 Zeichen, unveränderlich, DEC-026) ist **zusätzlich** eindeutig, ersetzt aber nie die `id`.

**Fremdschlüssel-Regel (N5):**

| Fall | Constraint | `ON DELETE` | Beispiel |
| --- | --- | --- | --- |
| Normaler Bezug | ja | passend zum Fachfall (`cascade` bei abhängigen Daten) | `term_user_overrides.user_id` → `cascade` |
| Optionaler Bezug auf Person | ja | `set null` | `term_revisions.author_id` |
| Selbstbezug / Revision | ja | `set null` | `terms.merged_into_id`, `terms.published_revision_id` (E3) |
| Verweis muss Löschen von Person oder Organisation überleben (Journal, Audit, Ereignisse, Einstellungen) | **nein**, nur Index | – | `settings.updated_by_id`, Audit-Einträge |

Wo der Bezug ohne Constraint bleibt, wird die Person zusätzlich **als Text** gespeichert (`author_label`, `actor_label`), damit der Eintrag nach der Pseudonymisierung lesbar bleibt (DEC-020).

**Indizes (N11, E2):**

- **Jede** Fremdschlüssel-Spalte ist indexiert – auch ohne Constraint. SQLite und PostgreSQL legen für einen Fremdschlüssel keinen Index von selbst an (anders als MySQL); darum `->index()` immer explizit setzen.
- Eindeutigkeit fachlich festhalten: z. B. `UNIQUE(term_id, user_id)` in `term_user_overrides`, je Begriff und Sprache in `term_translations`, je Paar und Art in `term_relations`.
- **Nicht** eindeutig: `term_aliases.alias` – Mehrdeutigkeit ist fachlich erlaubt.
- Zusammengesetzte Indizes in der Reihenfolge der häufigsten Abfrage (zuerst die Gleichheitsbedingung, dann Sortierung).
- **Weitere Indizes situativ:** Jeder Index verlangsamt das Schreiben. Über die Fremdschlüssel und die fachlichen Eindeutigkeiten hinaus kommt ein Index nur dazu, wenn eine konkrete, häufige Abfrage ihn braucht (mit `EXPLAIN` belegt); ungenutzte Indizes werden entfernt.

## Datentypen, Enums, JSON und Mehrsprachigkeit

Status und Arten sind PHP-Enums mit englischen Werten, JSON ist nur für Abbilder und Protokolle erlaubt, und Übersetzungen liegen in eigenen Tabellen.

**Enums (N8, E4, K2):**

- Jedes Status- oder Art-Feld ist ein Backed Enum in `App\Enums` mit einer Methode für das deutsche Label; das Modell castet die Spalte auf das Enum.
- In der Datenbank als Text gespeichert (kein natives `enum` – auf SQLite nicht vorhanden, auf PostgreSQL schwer erweiterbar) und **ab Beginn auf beiden Datenbanken mit CHECK-Regel** auf die erlaubten Werte begrenzt.
- Neue Werte sind erlaubt; sie brauchen eine Migration, die die CHECK-Regel erweitert. **Umbenennen** nur mit Datenüberführung und – ab API 1.0 – mit neuer API-Version (K3).
- Die gültigen Werte stehen in KONZEPT-KONVENTIONEN, z. B. `lifecycle_status`: `current`, `historical`, `under_review`; `proposals.status`: `organization_review`, `submitted`, `query`, `accepted`, `rejected`, `withdrawn`.

**Typen:**

| Inhalt | Laravel | SQLite (STRICT) | PostgreSQL | Grenze (Validierung + CHECK) |
| --- | --- | --- | --- | --- |
| Abkürzung | `string` | `TEXT` | `text` | 60 Zeichen |
| Bezeichnung | `string` | `TEXT` | `text` | 255 Zeichen |
| Kurzbeschreibung | `string` | `TEXT` | `text` | 160 Zeichen (Tooltip) |
| Beschreibung, Notiz | `text` | `TEXT` | `text` | Markdown erlaubt |
| Zeitpunkt | `timestampTz` | `TEXT` (ISO 8601, UTC) | `timestamptz` | Spalte `_at` |
| Datum | `date` | `TEXT` (ISO 8601) | `date` | Spalte `_on`, `valid_from` / `valid_until` |
| Ja/Nein | `boolean` | `INTEGER` | `boolean` | 0/1 |
| UUID | `uuid` | `TEXT` | `uuid` | 36 Zeichen |
| Sprache | `string` | `TEXT` | `text` | 2 Zeichen, ISO 639-1 |
| Zähler, Anzahlen | `integer` | `INTEGER` | `integer`/`bigint` | nie `float` für Zählbares |

Längen werden in der Validierung (Form Request) **und** als CHECK-Regel gleich gesetzt, damit nie still gekürzt oder überlang gespeichert wird.

**Datum und Uhrzeit:** Für Menschen (Website, Administration, Excel, PDF, Mails) gilt immer **Datum `DD.MM.YYYY`** (z. B. 12.11.2026) und **Uhrzeit `HH:MM:SS` im 24-Stunden-Format** (z. B. 21:53:07), Zeitzone Europe/Zurich. Gespeichert wird in der Datenbank und in Maschinenformaten (API, JSON, CSV-Schlüssel) nach ISO 8601 (`2026-11-12`, `2026-11-12T20:53:07Z`, UTC) – nur so sortieren und vergleichen Datenbank und Programme richtig. Formatiert wird an einer Stelle im Code (Helfer bzw. TypeScript-Funktion), nie in der Abfrage.

**JSON (N12):** nur für Abbilder, Protokolle und Listen ohne eigene Abfragen – `snapshot`, `changes`, `change_set`, `context`, `details`, `scopes`, `aliases_override`. Sobald nach einem Wert gefiltert, sortiert oder verknüpft werden muss, wird er eine Spalte oder eine eigene Tabelle.

**Mehrsprachigkeit ist Pflicht ab Projektstart (N9, X1):** Jede Tabelle mit Text, den Menschen lesen (Bezeichnung, Beschreibung, Label), bekommt **von Anfang an** eine `<objekt>_translations`-Tabelle mit `locale` (`de`, `fr`, `it`, `en`), eindeutig je Objekt und Sprache – auch wenn zuerst nur Deutsch gepflegt wird. Deutsch steht in der Haupttabelle und ist Rückfallsprache, wenn eine Übersetzung fehlt. Aliase je Sprache über `term_aliases.language` (F1). Keine Sprachspalten wie `name_fr`. Texte der Oberfläche laufen ebenfalls über Übersetzungsdateien (Laravel `lang/`, Frontend-i18n), nie fest im Code.

**Berechnete Werte werden nicht gespeichert:** `display_label`, `is_full_term`, `meaning_count`, `is_ambiguous` berechnet `App\Glossary\GlossaryDisplay` – eine Regel für Website und API.

## Datenschutz, Datentrennung und Löschen

Öffentliche Fachdaten und private Daten werden im Schema getrennt gehalten, und jede Tabelle mit Personenbezug braucht vor dem Merge eine Zeile im Löschkonzept.

**Drei Datenzonen (DEC-018, DEC-020):**

| Zone | Tabellen (Beispiele) | Darf hinaus? |
| --- | --- | --- |
| Gemeinsame Fachdaten | `terms`, `term_aliases`, `categories`, `sources`, `term_translations`, `term_relations` | ja, aber nur über die veröffentlichte Revision und kontrollierte Felder |
| Organisation / persönlich | `organization_term_overrides`, `term_user_overrides` | nein – nie in Revisionen, API-Vollstand, Change-Feed, GitHub Pages, Confluence, Excel; Antworten `Cache-Control: private, no-store` |
| Privat und intern | `users`, `user_identities`, `proposals`, `proposal_events`, `*_audit`, `import_batches`, `search_misses` | nein |

**Regeln für neue Tabellen und Spalten:**

1. **Zone festlegen**, bevor die Migration geschrieben wird; eine Spalte darf nicht zwei Zonen vermischen (keine persönlichen Werte in `terms`).
2. **Datenminimierung:** nur speichern, was ein beschlossener Ablauf braucht. Beispiel: letzte Anmeldung nur als Datum (`last_login_on`), `search_misses` ohne Person und ohne IP-Adresse.
3. **Löschfrist** im [Löschkonzept](https://github.com/MarkusKeller8200/EduKSX-Glossary/blob/main/docs/LOESCHKONZEPT.md) eintragen und in `php artisan glossary:loeschfristen` umsetzen (inkl. `--trocken`). Neue Fristen ändern die Version des Löschkonzepts und die Datenschutzerklärung.
4. **Kontolöschung** (`AccountManager::delete`, `Journal::forget`) erweitern, wenn die neue Tabelle auf Personen zeigt: löschen (`cascade`) oder pseudonymisieren («Gelöschtes Konto», Bezüge leeren).
5. **Veröffentlichungsgrenze** in `tests/Feature/PublicBoundaryTest.php` nachführen, sobald eine neue Spalte an Website, API oder Export grenzt.
6. **Keine Geheimnisse im Klartext:** Tokens nur gehasht (`service_account_tokens`), 2FA-Geheimnisse verschlüsselt (Laravel-Cast `encrypted`).

**Unveränderlich bleibt unveränderlich:** Revisionen und Audit-Einträge werden nie aktualisiert oder gelöscht. Einzige dokumentierte Ausnahme ist die Pseudonymisierung bei Kontolöschung (DEC-020). Rücknahmen sind neue Revisionen (DEC-022).

**Nach einer Wiederherstellung** sofort `glossary:loeschfristen` ausführen und seit der Sicherung gelöschte Konten erneut löschen.

## Migrationen

Jede Schemaänderung ist eine Laravel-Migration, läuft ohne Datenverlust, ist getestet und bleibt eine Version lang abwärtskompatibel.

**Erweitern – umstellen – aufräumen (AGENTS.md, DEC-027 U18):** Beim Update laufen alte und neue Version kurz mit derselben Datenbank, und ein Rücksprung startet die alte Version auf der migrierten Datenbank. Darum:

1. **Version n:** neue Spalte/Tabelle anlegen, Daten kopieren, Code liest neu und schreibt beides.
2. **Version n+1:** alte Spalte/Tabelle entfernen oder umbenennen.

`tests/Unit/MigrationCompatibilityTest.php` verbietet in `up()` neuer Migrationen `dropColumn`, `renameColumn`, `dropTable`, `rename` und `change`. Eine bewusste Ausnahme trägt im Code den Vermerk `// abwaertskompatibel: <Begründung>`.

**Pflichtteile jeder Migration:**

| Nr. | Regel | Herkunft |
| --- | --- | --- |
| 1 | Dateiname mit Zeitstempel und sprechendem Namen (`2026_11_12_000100_create_term_relations.php`); eine Migration = ein fachlicher Schritt | Laravel |
| 2 | Vor dem Ausrollen Sicherung der Datenbank (der Container macht das vor jedem Update) und Probelauf auf einer Kopie der dev-Datenbank | M1 |
| 3 | Jede Datenüberführung zählt vorher und nachher und bricht bei Abweichung mit einer Exception ab | M2 |
| 4 | Daten, die entfallen, zuerst als JSON nach `storage/app/archiv/` schreiben und die Anzahl protokollieren | M3 |
| 5 | Umbenennen mit `renameColumn` (Daten bleiben), keine neue Tabelle mit Kopie – ausser SQLite verlangt es | M4 |
| 6 | Je Migration ein Test: Daten anlegen, migrieren, Inhalt prüfen | M5 |
| 7 | `down()` stellt Struktur **und** Daten wieder her, soweit möglich (Vorbild: `2026_11_07_001200_shared_and_personal_data.php`) | DEC-020 |
| 8 | Keine Modellklassen in Migrationen verwenden (sie ändern sich später); Daten mit `DB::table()` bewegen, in Blöcken (`chunkById`) | Praxis |
| 9 | Schreibende Datenüberführung in einer Transaktion | Praxis |

**Sonderfall 0.12.0 (DEC-030):** Die Umbenennungen N4/N6/N7 und das Zusammenfassen der 23 Migrationen zu einem bereinigten Anfangsstand (O2, O4, M6) sind vom Projektverantwortlichen beschlossen. Bestehende dev-Datenbanken erkennt eine Übergangs-Migration und überführt sie Schritt für Schritt. **Vorbedingung M0:** ein vollständiger Dump der dev-Datenbank (Funktion aus #268) liegt vor.

## Abfragen, Transaktionen und Performance

Abfragen laufen über Eloquent oder den Query Builder mit Bindings, schreibende Abläufe in einer Transaktion, und Listen laden Beziehungen vorab.

- **Zugriffsschicht:** Fachlogik in Services (z. B. `App\Glossary`, `App\Access`), nicht in Controllern. Der MCP-Server und andere Kanäle nutzen diese Services, **nie die Datenbank direkt** (DEC-011).
- **Kein Roh-SQL mit Benutzereingaben:** immer Bindings; `whereRaw`/`DB::statement` nur mit Platzhaltern und Begründung im Kommentar.
- **N+1 vermeiden:** Listen mit `with()` laden; in der Entwicklung `Model::preventLazyLoading()` einschalten.
- **Transaktionen:** alles, was zusammengehört, in **eine** `DB::transaction()` – z. B. neue Revision + `published_revision_id` + Publikationsereignis + Journal, oder Zusammenführen zweier Begriffe. Kurz halten: keine Mails oder HTTP-Aufrufe in der Transaktion (SQLite sperrt beim Schreiben die ganze Datei); Folgeaktionen mit `afterCommit` in die Queue.
- **Gleichzeitiges Bearbeiten:** Änderungen an Begriffen prüfen die Basisrevision (Vorschläge haben sie bereits); weicht sie ab, wird abgelehnt statt still überschrieben.
- **Grosse Mengen:** Importe, Exporte und Löschläufe mit `chunkById` bzw. `lazyById` und als Job (`job_batches`, F4), nie alles in den Speicher.
- **Öffentliche Abfragen** lesen nur veröffentlichte, aktive Begriffe über einen zentralen Scope; persönliche Darstellung nur für das angemeldete Konto (`user_id` = angemeldetes Konto).
- **Indizes prüfen:** neue Abfragen auf grossen Tabellen mit `EXPLAIN QUERY PLAN` (SQLite) bzw. `EXPLAIN` (PostgreSQL) kontrollieren; ein Full-Table-Scan auf `terms`, `term_aliases` oder `term_revisions` in einer häufigen Abfrage ist ein Fehler.

## Konkurrierende Speicherung, Rollback und Snapshots

Gleichzeitiges Speichern darf nie Daten still überschreiben, eine grössere Änderung läuft ganz oder gar nicht, und jeder Snapshot ist konsistent und zurückspielbar.

**Konkurrierende Speicherung (optimistisches Sperren):**

1. Jede änderbare Tabelle mit Fachdaten hat eine Spalte `lock_version` (integer, Start 1). Begriffe mit Revisionen nutzen stattdessen die Basisrevision.
2. Das Formular bzw. die API schickt die gelesene `lock_version` mit. Gespeichert wird mit `UPDATE … WHERE id = ? AND lock_version = ?` und `lock_version = lock_version + 1`.
3. Trifft das Update **0 Zeilen**, hat jemand anderes inzwischen gespeichert: Abbruch mit Konflikt (HTTP 409), Anzeige beider Stände, nichts wird überschrieben.
4. Zähler und Summen nie im PHP lesen-rechnen-schreiben, sondern atomar in der Datenbank (`increment()`, `UPDATE … SET n = n + 1`).
5. Eindeutigkeit (z. B. Permalink-Code) sichert ein UNIQUE-Index, nicht eine vorherige Abfrage.
6. SQLite: WAL und `busy_timeout` 5000 ms; schreibende Transaktionen kurz halten. Bei `database is locked` nach Ablauf des Timeouts wird der ganze Ablauf einmal wiederholt (`DB::transaction(fn, attempts: 3)`), nicht nur die letzte Anweisung.
7. **Umsetzung:** eigenes kleines Trait `HasLockVersion` im Projekt statt eines Pakets – die Logik umfasst wenige Zeilen, und die verfügbaren Pakete werden von einzelnen Personen gepflegt. `updated_at` ist kein Ersatz, weil zwei gleichzeitige Speicherungen denselben Zeitstempel haben können.

**Grössere Änderungen: alles oder nichts:**

- Jede Änderung, die mehrere Zeilen oder Tabellen betrifft (Import, Zusammenführen, Veröffentlichen mehrerer Begriffe, Datenüberführung, Löschlauf je Konto), läuft in **einer** Transaktion. Bei einem Fehler wird die **gesamte** Transaktion zurückgerollt; es bleibt kein halber Stand.
- Fehler werfen eine Exception und werden nicht innerhalb der Transaktion abgefangen und ignoriert; Teilschritte dürfen höchstens über Savepoints (verschachtelte `DB::transaction`) zurückgenommen werden.
- Seiteneffekte ausserhalb der Datenbank (Mails, Webhooks, Dateien, Queue-Jobs) erst nach dem Commit (`afterCommit`); bei Rollback finden sie nicht statt.
- Ist eine Änderung zu gross für eine Transaktion (z. B. sehr grosser Import), läuft sie zuerst in Staging-Tabellen (`import_rows`) und wird am Ende in einer Transaktion übernommen; abbrechen = Staging verwerfen.
- Migrationen mit Datenüberführung laufen ebenfalls in einer Transaktion (SQLite und PostgreSQL können DDL zurückrollen); zusätzlich sichert der Container vorher die Datenbank.

**Snapshots:**

| Regel | Umsetzung |
| --- | --- |
| Laufend gesichert | **Litestream** überträgt die Änderungen aus dem WAL fortlaufend in einen S3-kompatiblen Speicher ausser Haus (z. B. Swiss Backup); Wiederherstellung auf jeden Zeitpunkt der Aufbewahrung, Datenverlust im Fehlerfall höchstens Sekunden |
| Konsistent | Einzelsnapshots nur über die Datenbank erstellen: SQLite `VACUUM INTO` bzw. `.backup`, PostgreSQL `pg_dump` – nie die Datei im Betrieb kopieren |
| Zeitstempel | Dateiname mit Zeitstempel und Version, z. B. `glossary_2026-11-12_21-53-07_v0.12.0.sqlite` |
| Prüfbar | Prüfsumme (SHA-256) und Anzahl Zeilen je Tabelle in einer Begleitdatei; `PRAGMA integrity_check` nach dem Erstellen |
| Zurückspielbar | Wiederherstellung als Befehl bzw. in der Administration; danach Migrationen auf den aktuellen Stand und `glossary:loeschfristen` |
| Getestet | Wiederherstellung regelmässig (mindestens monatlich) auf einer Kopie prüfen: Zeilen und Prüfsumme vergleichen |
| Wann | Litestream dauernd; Einzelsnapshot vor jedem Update, jeder Migration und grossen Importen; Aufbewahrung nach Löschkonzept (10 lokal, 30 Tage ausser Haus) |

## Löschen mit Wiederherstellungsfrist

Gelöschte Datensätze lassen sich während einer Frist wiederherstellen und werden danach endgültig entfernt.

1. **Vorläufig löschen:** Tabellen mit Fach- und Organisationsdaten haben `deleted_at` (N6) und `deleted_by_id` (N4); Laravel `SoftDeletes`. Der Datensatz verschwindet aus allen normalen Abfragen, aus Website, API, Change-Feed und Exporten.
2. **Wiederherstellen:** während der Frist in der Administration (Papierkorb je Objektart), mit Eintrag `restored` in der Historie. **Wer wiederherstellen darf und wie lange die Fristen dauern, können nur Super-Admins einstellen** (eigenes Recht, Schutzklasse mit MFA nach DEC-017); jede Änderung daran steht im Audit-Protokoll.
3. **Endgültig löschen:** nach Ablauf der Frist löscht `glossary:loeschfristen` den Datensatz physisch; die Historie behält den letzten Stand als Archiv (ausser Personendaten, siehe unten). Die Frist ist unter «Einstellungen» je Objektart einstellbar (nur Super-Admins); Vorgabe [30 Tage], im Löschkonzept eintragen.
4. **Eindeutigkeit:** Werte, die nie neu vergeben werden dürfen (Permalink-Code, UUID), bleiben über alle Zeilen eindeutig, auch gelöschte. Fachliche Werte, die nach dem Löschen wieder frei sein sollen, bekommen einen partiellen UNIQUE-Index mit `WHERE deleted_at IS NULL` (SQLite und PostgreSQL); beim Wiederherstellen wird geprüft, ob der Wert inzwischen neu belegt ist.
5. **Abhängige Daten:** vorläufiges Löschen betrifft das Objekt und seine abhängigen Datensätze gemeinsam (in einer Transaktion); Wiederherstellen stellt sie gemeinsam wieder her.
6. **Konten mit Löschfrist:** Löscht eine Person ihr Konto (oder wird es gelöscht), wird es zuerst **vorläufig gelöscht**: Anmeldung gesperrt, Sitzungen, Tokens und «angemeldet bleiben» sofort beendet, persönliche Darstellung ausgeblendet, Bezeichnung überall als «Gelöschtes Konto» angezeigt. Während der Frist (Vorgabe [30 Tage], einstellbar nur durch Super-Admins) kann ein Super-Admin das Konto auf Wunsch der Person wiederherstellen. Nach Ablauf löscht `glossary:loeschfristen` das Konto endgültig und pseudonymisiert Historie und Journal (`Journal::forget`). Die Person erhält beim Löschen eine Bestätigung mit dem Datum der endgültigen Löschung. Ausnahme: Ein Antrag auf sofortige Löschung nach Datenschutzrecht wird sofort vollzogen. Diese Regel ersetzt im Löschkonzept die Zeile «Konto auf Wunsch – sofort» und muss in der Datenschutzerklärung nachgeführt werden.
7. **Revisionen** bleiben ausgenommen: Begriffe werden über Zurückziehen (Tombstone, DEC-022) und `is_active` gesteuert, nicht über `deleted_at`.

## Änderungshistorie: wer, wann, was

Jede Datenveränderung – Anlegen, Ändern und Löschen – wird historisch gespeichert, mit Person, Zeitpunkt und den geänderten Werten.

**Zwei Wege, eine Regel:**

| Daten | Historie | Bemerkung |
| --- | --- | --- |
| Begriffe (Fachdaten mit Freigabe) | bestehendes Revisionssystem `term_revisions` mit `change_set` und `term_publication_events` | unveränderliche Abbilder, kein zweites System (DEC-020, DEC-022) |
| alle anderen änderbaren Tabellen | zentrale Tabelle `data_changes` | automatisch über ein Eloquent-Trait (z. B. `RecordsChanges`) |
| Rollen, Rechte, Konten, Servicekonten | bestehende `user_audit` / `service_account_audit` | Sicherheitsprotokoll, bleibt |

**Aufbau von `data_changes`:**

| Spalte | Inhalt |
| --- | --- |
| `id` | fortlaufende Zahl (Protokoll, N3) |
| `subject_type`, `subject_id` | Tabelle und `id` des Datensatzes (ohne Constraint, mit Index – N5) |
| `action` | Enum `created`, `updated`, `deleted`, `restored` |
| `changes` | JSON Feld → `{old, new}`; bei `created` alle gesetzten Felder, bei `deleted` der letzte vollständige Stand |
| `actor_type` | `user`, `service`, `system`, `import` |
| `actor_id`, `actor_label` | wer (Bezug ohne Constraint + Name als Text für Pseudonymisierung) |
| `context` | z. B. `{"via": "excel", "batch": …}`, Request-ID |
| `occurred_at` | wann, UTC |

**Regeln:**

- Die Historie wird in **derselben Transaktion** wie die Änderung geschrieben – rollt die Änderung zurück, verschwindet auch der Eintrag.
- Einträge sind unveränderlich: kein Update, kein Löschen ausser durch Löschfristen und Pseudonymisierung.
- Massenänderungen am Trait vorbei (`DB::table()->update()`, Migrationen) schreiben die Historie selbst oder protokollieren mindestens einen Sammeleintrag mit Anzahl und Kontext.
- Gleiches Ergebnis = kein Eintrag. Geheimnisse (Passwörter, Tokens, 2FA) werden nie in `changes` geschrieben, nur `"changed"`.
- **Datenschutz:** Für Daten mit Personenbezug gilt die Frist aus dem Löschkonzept auch für deren Historie; bei Kontolöschung werden `actor_label` und `actor_id` pseudonymisiert und Einträge über das Konto selbst gelöscht. Neue Frist für `data_changes` ins Löschkonzept aufnehmen.
- Anzeige: Verlauf je Datensatz in der Administration (alter und neuer Wert, Datum `DD.MM.YYYY`, Uhrzeit `HH:MM:SS`); nicht öffentlich.
- **Trigger nur zurückhaltend:** Die Historie wird im Code geschrieben (Trait), nicht mit Datenbank-Triggern. Ein Trigger ist nur zulässig, wenn ein Weg am Code vorbei anders nicht abzusichern ist; dann ist er in einer Migration für SQLite **und** PostgreSQL angelegt, hier dokumentiert, getestet und bei jeder Schemaänderung der Tabelle nachgeführt.
- **Löschen erfasst auch die Historie:** Wird ein Konto gelöscht oder läuft eine Frist für Personendaten ab, werden die Personendaten auch aus `changes` der betroffenen Einträge entfernt (Wert durch `"removed"` ersetzen); ändernde Person → «Gelöschtes Konto». Dasselbe gilt für Protokolle, Import-Staging und Exporte im Speicher.
- **Sicherungen:** Gelöschte Personendaten bleiben in Sicherungen bis zu deren Ablauf; sie werden nicht geöffnet. Nach einer Wiederherstellung läuft sofort `glossary:loeschfristen`, und seit der Sicherung gelöschte Konten werden erneut gelöscht (Löschkonzept).

## Tests

Jede Schemaänderung bringt eigene Tests mit; die bestehenden Grenz- und Kompatibilitätstests müssen grün bleiben.

| Test | Prüft | Wann anpassen |
| --- | --- | --- |
| `tests/Unit/MigrationCompatibilityTest.php` | keine entfernenden/umbenennenden Befehle in `up()` | nie aufweichen; Ausnahme nur per Vermerk |
| `tests/Feature/DataModelTest.php`, `GlossaryModelTest.php` | Tabellen, Beziehungen, Revisionen | neue Tabelle oder Beziehung |
| `tests/Feature/PublicBoundaryTest.php` | nichts Privates in Website, API, Export | neue Spalte nahe an der Öffentlichkeit |
| `tests/Feature/PrivacyTest.php`, `AccountTest.php` | Kontolöschung, Pseudonymisierung, Fristen | neue Tabelle mit Personenbezug |
| `tests/Feature/RestoreTest.php` | Rücknahme von Revisionen | Änderungen am Revisionsmodell |
| neu je Migration | Daten vorher anlegen, migrieren, Inhalt und Anzahl prüfen (M5) | jede Migration mit Datenüberführung |
| neu: Löschtest nach Wiederherstellung | Konto löschen, älteren Snapshot zurückspielen, glossary:loeschfristen ausführen: keine Personendaten mehr in Tabellen und Historie | neue Tabelle mit Personenbezug, Änderung an Historie oder Sicherung |
| neu: Papierkorb | vorläufig löschen, nicht mehr sichtbar, wiederherstellen innerhalb der Frist, endgültig löschen nach der Frist | neue Tabelle mit deleted_at |
| neu: Schemaprüfung | neue SQLite-Tabellen sind STRICT, Enum- und Längenspalten haben CHECK-Regeln, integrity_check und foreign_key_check ohne Befund | jede Migration |

Tests laufen mit SQLite im Speicher. Sobald PostgreSQL kommt, läuft die CI zusätzlich gegen PostgreSQL, damit Unterschiede (Längen, CHECK-Regeln, Sortierung) früh auffallen.

## Checkliste für Pull Requests mit DB-Änderung

Diese Liste in die PR-Beschreibung kopieren und abhaken.

- [ ] Konzept deckt die Änderung ab – sonst zuerst Konzept-PR (`docs/KONZEPT*.md`, Eintrag in `ENTSCHEIDE.md`)
- [ ] Namen nach N1–N12 (englisch, `snake_case`, Plural, `_id`, `_by_id`, `_at`/`_on`, `is_`/`has_`)
- [ ] Primärschlüssel richtig gewählt (UUID für Fachobjekte, Zahl für Protokolle)
- [ ] Fremdschlüssel nach N5, jede FK-Spalte mit Index
- [ ] Status/Arten als Enum mit deutschem Label, Werte in KONZEPT-KONVENTIONEN ergänzt
- [ ] JSON nur für Abbilder/Protokolle/Listen ohne Abfrage
- [ ] Datenzone bestimmt; nichts Privates an der Veröffentlichungsgrenze
- [ ] Löschfrist im Löschkonzept und in `glossary:loeschfristen`; Kontolöschung berücksichtigt
- [ ] Migration abwärtskompatibel (erweitern jetzt, aufräumen nächste Version)
- [ ] Datenüberführung zählt vorher/nachher, archiviert Entfallendes, `down()` vorhanden
- [ ] Läuft auf SQLite und ist PostgreSQL-tauglich (kein Roh-SQL ohne Weiche)
- [ ] Migrationstest geschrieben, alle Tests grün
- [ ] Probelauf auf Kopie der dev-Datenbank gemacht
- [ ] API-Werte unverändert oder Intranet-Abgleich (sek-intranet) informiert
- [ ] CHANGELOG-Eintrag (ohne Entscheidnummern), Commit mit `Refs #n`
- [ ] Texte für Menschen mit `_translations`-Tabelle ab Start (auch wenn nur Deutsch gepflegt wird)
- [ ] Optimistisches Sperren (`lock_version` bzw. Basisrevision), Konflikt ergibt 409 statt Überschreiben
- [ ] Mehrteilige Änderung in einer Transaktion, vollständiger Rollback bei Fehler getestet
- [ ] Historie für Anlegen, Ändern, Löschen (wer, wann, was) in derselben Transaktion
- [ ] Anzeige Datum `DD.MM.YYYY`, Uhrzeit `HH:MM:SS` (24 h); Speicherung ISO 8601 / UTC
- [ ] Snapshot vor der Migration erstellt und Wiederherstellung geprüft
- [ ] Neue SQLite-Tabelle als STRICT, CHECK-Regeln für Enums, Längen, Ja/Nein und Zeiträume in der ersten Migration
- [ ] Zeitpunkte als timestampTz, Zeichenketten ohne varchar-Länge (Grenze über Validierung + CHECK)
- [ ] Vorläufiges Löschen (deleted_at, deleted_by_id) mit Wiederherstellung und Frist, sofern Fach- oder Organisationsdaten
- [ ] Löschen von Personendaten entfernt sie auch aus der Historie
- [ ] Kein neuer Trigger – oder begründet, dokumentiert, für beide Datenbanken getestet
- [ ] Zusätzliche Indizes nur mit konkreter Abfrage begründet
- [ ] Dritte Normalform eingehalten: keine Listen in einem Feld, keine gespeicherten abgeleiteten Werte; jede doppelte Speicherung in der Ausnahmetabelle unter «Normalisierung» eingetragen

## Widersprüche und offene Punkte

Beim Abgleich der Konzepte (Stand Repo 12.11.2026) sind vier Stellen aufgefallen, die vor der Umsetzung von 0.12.0 bereinigt werden sollten.

| Nr. | Thema | Widerspruch | Vorschlag |
| --- | --- | --- | --- |
| W1 | PostgreSQL | DEC-010, KONZEPT-STACK und KONZEPT-DATENMODELL sagen «kein PostgreSQL»; DEC-028 und KONZEPT-DATENBANK O4 sagen «bald PostgreSQL». | DEC-010 als «abgelöst durch DEC-028» markieren und die beiden Konzepte nachführen. |
| W2 | Umbenennen | AGENTS.md und `MigrationCompatibilityTest` verbieten `renameColumn` in derselben Version; DEC-030 O2/M4 benennt in 0.12.0 direkt um. | Für 0.12.0 Ausnahme mit Vermerk `abwaertskompatibel: nie produktiv, DEC-030` – oder der neue Anfangsstand (M6) liegt vor dem Stichtag des Tests. |
| W3 | Migrationen zusammenfassen | In KONZEPT-DATENBANK steht O4 einmal als «jetzt zusammenfassen» (Antworten) und einmal als «nein, erst beim Wechsel auf PostgreSQL» (offene Fragen). | Tabelle «Offene Fragen» als überholt kennzeichnen oder entfernen. |
| W4 | Enums | N8/E4 verlangen PHP-Enums in `App\Enums`; der Ordner existiert noch nicht. | Mit 0.12.0 anlegen; ein Enum je Feld aus E4. |
| W5 | Super-Admin | Die Guideline verlangt Super-Admins für Fristen und Wiederherstellung; das Rollenkonzept (DEC-017) kennt Community, Moderator, Admin und Developer. | Festlegen, ob Super-Admin die geschützte Rolle Developer ist oder eine neue Rolle bzw. ein eigenes Recht; KONZEPT-BENUTZER nachführen. |
| W6 | Kontolöschung | Löschkonzept: Konto auf Wunsch sofort löschen; Guideline: zuerst vorläufig mit Frist. | Löschkonzept (neue Version) und Datenschutzerklärung anpassen; Frist rechtlich prüfen. |

Ausserdem: Diese Guideline ins Repo EduKSX-Architektur als Plattform-Vorgabe übernehmen (KONZEPT-KONVENTIONEN verlangt das für die Namensregeln) und von AGENTS.md aus verlinken, damit KI-Agenten sie automatisch finden.

## Quellen

- [EduKSX-Glossary – docs/KONZEPT-DATENBANK.md](https://github.com/MarkusKeller8200/EduKSX-Glossary/blob/main/docs/KONZEPT-DATENBANK.md)
- [docs/KONZEPT-DATENMODELL.md](https://github.com/MarkusKeller8200/EduKSX-Glossary/blob/main/docs/KONZEPT-DATENMODELL.md)
- [docs/KONZEPT-KONVENTIONEN.md](https://github.com/MarkusKeller8200/EduKSX-Glossary/blob/main/docs/KONZEPT-KONVENTIONEN.md)
- [docs/KONZEPT-STACK.md](https://github.com/MarkusKeller8200/EduKSX-Glossary/blob/main/docs/KONZEPT-STACK.md)
- [docs/LOESCHKONZEPT.md](https://github.com/MarkusKeller8200/EduKSX-Glossary/blob/main/docs/LOESCHKONZEPT.md)
- [docs/ENTSCHEIDE.md](https://github.com/MarkusKeller8200/EduKSX-Glossary/blob/main/docs/ENTSCHEIDE.md)
- [AGENTS.md](https://github.com/MarkusKeller8200/EduKSX-Glossary/blob/main/AGENTS.md), `config/database.php`, `tests/Unit/MigrationCompatibilityTest.php`
