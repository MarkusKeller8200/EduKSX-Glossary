# Konzept: DB-Guideline EduKSX-Glossary (#270, #272, #310)

Stand: 13.11.2026 (Version 1.2) · Verantwortlich: Markus Keller · Status: **Entwurf zur Abnahme** (Version 1.0: DEC-031,
Version 1.1: DEC-032) · arbeitet nach **DB-Standard 1.2** der Plattform

Diese Guideline wendet den **DB-Standard der EduKSX-Plattform** (Repo EduKSX-Architektur,
`docs/KONZEPT-DB-GUIDELINE.md`, nicht öffentlich) auf das Glossary an. Allgemeine Regeln – Namen, Normalisierung,
Typen, Migrationen, Transaktionen, Historie, Mandanten, Datenklassen, Löschregister, Identitäten, Sicherung, Sprachen –
stehen dort und werden hier nicht wiederholt; die Verweise in Klammern (A1, B3 …) zeigen auf die Abschnitte des
Standards. Hier steht nur, was für das Glossary gilt: Tabellen, Befehle, Werte, Ausnahmen und offene Punkte.

Bei Widerspruch gilt: Entscheid im Register [ENTSCHEIDE.md](ENTSCHEIDE.md) > Konzept des Glossary > diese Guideline >
Plattform-Standard.

**Grundlagen im Glossary:** [KONZEPT-DATENBANK.md](KONZEPT-DATENBANK.md) (DEC-030, Namen N1–N12, M0–M6),
[KONZEPT-DATENMODELL.md](KONZEPT-DATENMODELL.md) (DEC-020, DEC-022), [KONZEPT-KONVENTIONEN.md](KONZEPT-KONVENTIONEN.md)
(K1–K7), [KONZEPT-ORGANISATION.md](KONZEPT-ORGANISATION.md) (DEC-025), [LOESCHKONZEPT.md](LOESCHKONZEPT.md) (DEC-019),
[AGENTS.md](../AGENTS.md).

## 1. Plattform des Glossary (C1)

- Heute **SQLite**, Datei `storage/app/database/glossary.sqlite` im Docker-Volumen; Einstellungen in
  `config/database.php` (WAL, `busy_timeout` 5000 ms, Fremdschlüssel an). Später **PostgreSQL** als zentraler Dienst
  (DEC-028, Plattformkonzept).
- **Ab sofort** laufen Tests und Migrationen in der CI gegen SQLite **und** PostgreSQL (C1).
- Bestehende Tabellen werden mit dem bereinigten Anfangsstand (M6) als **STRICT** und mit CHECK-Regeln neu angelegt
  (A4).

## 2. Geltungsbereiche der Glossary-Tabellen (B1)

| Geltungsbereich      | Tabellen                                                                                                                                                                                                                                        |
| -------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Global**           | `terms`, `term_aliases`, `term_categories`, `term_sources`, `term_translations`, `term_relations`, `categories`, `category_translations`, `sources`, `source_translations`, `term_revisions`, `term_publication_events`, `publication_releases` |
| **Organisation**     | `organizations`, `organization_members`, `organization_invitations`, `organization_term_overrides`, Vorschläge mit `organization_id`, Servicekonten einer Organisation                                                                          |
| **Persönlich**       | `term_user_overrides` (mit `approved_organization_id` für die Freigabe an eine Organisation, DEC-025), Einstellungen eines Kontos                                                                                                               |
| **Plattform/intern** | `users`, `user_identities`, `roles`, `user_roles`, `user_audit`, `service_accounts`, `service_account_tokens`, `service_account_audit`, `settings`, `import_batches`, `import_rows`, `search_misses`, `data_changes`                            |

Persönliche Anpassungen gelten **organisationsübergreifend** für das Konto; die Freigabe an eine Organisation ist ein
eigener Schritt (DEC-025: persönlich > Organisation > gemeinsam).

Nur global gilt die Veröffentlichungsgrenze (DEC-018): Website, API, Change-Feed, GitHub Pages, Confluence und Exporte
erhalten ausschliesslich veröffentlichte, globale Felder; persönliche und Organisationsdaten nie, Antworten mit
persönlicher Darstellung `Cache-Control: private, no-store`.

## 3. Datenklassen der wichtigsten Felder (B2)

| Tabelle                        | öffentlich                                                      | intern                  | personenbezogen                               | geheim             |
| ------------------------------ | --------------------------------------------------------------- | ----------------------- | --------------------------------------------- | ------------------ |
| `terms` und Übersetzungen      | Abkürzung, Bezeichnung, Beschreibungen, URL, Status, Gültigkeit | `note`, `is_active`     | –                                             | –                  |
| `term_revisions`               | `snapshot`, `change_set` (nur globale Felder)                   | Nummer, Art, Zeitpunkte | `author_label`, `author_id`, `approved_by_id` | –                  |
| `term_user_overrides`          | –                                                               | –                       | alle `*_override`                             | –                  |
| `proposals`, `proposal_events` | –                                                               | Status, Änderung        | Begründung, Kommentar, Bezug zur Person       | –                  |
| `users`                        | –                                                               | Status, Rollen          | Name, E-Mail, Institution, `last_login_on`    | Passwort-Hash, 2FA |
| `service_account_tokens`       | –                                                               | Name, Präfix, Ablauf    | –                                             | `token_hash`       |
| `search_misses`                | –                                                               | Suchtext, Anzahl        | – (keine Person, keine IP)                    | –                  |

Vollständig je Spalte im Code mit 0.12.0, mit Test und daraus erzeugter Übersicht (W7, #276).

## 4. Namen und Bereinigung 0.12.0 (A1, DEC-030)

Abweichungen von A1, die mit 0.12.0 umbenannt werden: `…_by` → `…_by_id` (u. a. `term_revisions.approved_by`,
`settings.updated_by`), `merged_into` → `merged_into_id`, `term_sources.retrieved_at` → `retrieved_on`,
`users.password_set` → `has_password`, `organization_members.granted_role` → `is_role_granted`,
`proposal_events.by_author` → `is_by_author`, `users.news_opt_in` → `has_news_opt_in`, `proposals.kind` →
`proposal_type`, `proposal_events.type` → `event_type`, `duplicate_decisions.kind` → `match_type`. **Umgesetzt** im
Anfangsstand `database/migrations/2026_11_01_000000_anfangsstand.php` mit Übergang `App\Database\Uebergang012` (#266);
Helfer für STRICT und CHECK: `App\Database\StrictSchema`; Enums in `app/Enums` (#275). Werte von Status und Arten
englisch nach [KONZEPT-KONVENTIONEN.md](KONZEPT-KONVENTIONEN.md) (z. B. `lifecycle_status`: `current`, `historical`,
`under_review`).

## 5. Ausnahmen von der Normalisierung (A2)

| Wo                                                  | Was ist doppelt                                      | Grund                                        | Wie aktuell gehalten                                                                                               |
| --------------------------------------------------- | ---------------------------------------------------- | -------------------------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| `term_revisions.snapshot`                           | Abbild des Begriffs (nur öffentliche/interne Felder) | Revisionen unveränderlich (DEC-022)          | bewusst eingefroren                                                                                                |
| `terms.published_revision_id`                       | Zeiger auf die veröffentlichte Revision              | schneller Zugriff auf den öffentlichen Stand | in derselben Transaktion wie das Veröffentlichen; Zugehörigkeit per zusammengesetztem Fremdschlüssel (Abschnitt 6) |
| `author_label`, `actor_label`                       | Name als Text neben der ID                           | lesbar nach Kontolöschung                    | ersetzt durch «Gelöschtes Konto»                                                                                   |
| `term_revisions.change_set`, `data_changes.changes` | alte und neue Werte                                  | Historie                                     | unveränderlich; Personendaten nach B2 nicht enthalten                                                              |
| `publication_releases`                              | Datenstand mit Prüfsumme                             | Abgleich der Konsumenten                     | je Veröffentlichung neu                                                                                            |

Bereinigt mit 0.12.0: `terms.search_terms` entfällt (nur noch `term_aliases`, Typ `search`, E1); `term_sources.url`
entfällt (Quelle ist `terms.url`, K1).

## 6. Schlüssel im Glossary (A3)

- UUID v7 für `terms`, `proposals`, `organizations`, `users`, `service_accounts`; fortlaufende Zahl für Protokolle und
  Ereignisse.
- Permalink-Code `/g/{code}` (6 Zeichen, unveränderlich, DEC-026): eindeutig über **alle** Zeilen, auch gelöschte; wird
  nie neu vergeben.
- **`terms.published_revision_id` gehört zum selben Begriff:** `term_revisions` erhält UNIQUE `(term_id, id)`;
  `terms (id, published_revision_id)` verweist darauf als zusammengesetzter Fremdschlüssel, `ON DELETE SET NULL` auf
  die Revision. Dasselbe für `merged_into_id` ist nicht nötig (zeigt bewusst auf einen anderen Begriff).
- `term_aliases.alias` ist nicht eindeutig (Mehrdeutigkeit erlaubt); eindeutig sind `(term_id, user_id)` in
  `term_user_overrides`, `(term_id, locale)` in `term_translations`, `(term_id, related_term_id, relation_type)` in
  `term_relations`.

## 7. Migrationen im Glossary (A5)

- `tests/Unit/MigrationCompatibilityTest.php` prüft die Abwärtskompatibilität ab Stichtag `2026_11_12_000000`.
- **0.12.0 (DEC-030):** Das Glossary war nie produktiv; darum gilt die Ausnahme «bereinigter Anfangsstand» (A5)
  einmalig: Zusammenfassen der Migrationen und direkte Umbenennungen, mit Vermerk `abwaertskompatibel: nie produktiv,
  DEC-030` und Übergangs-Migration für die dev-Datenbank. Vorbedingung M0: vollständiger Dump (#268, vorhanden).
- **Altwerte (M3):** `term_legacy_values` enthält laut Löschkonzept keine Personendaten. Das Archiv in
  `storage/app/archiv/` wird verschlüsselt, als «intern» klassifiziert und nach [12 Monaten] gelöscht; Frist ins
  Löschkonzept.

## 8. Historie im Glossary (A7)

| Daten                                 | Historie                                                                                  |
| ------------------------------------- | ----------------------------------------------------------------------------------------- |
| Begriffe                              | `term_revisions` (`change_set`) und `term_publication_events` – kein zweites System       |
| Rollen, Rechte, Konten, Servicekonten | `user_audit`, `service_account_audit` (Sicherheitsprotokoll, mit Hash-Verkettung nach A7) |
| alle übrigen änderbaren Tabellen      | `data_changes` mit `source` (`web`, `api`, `import`, `mcp`, `job`, `system`, `migration`) |

## 9. Löschen im Glossary (B3)

- Befehl `php artisan glossary:loeschfristen` (täglich 03:15, `--trocken` zur Vorschau) setzt Fristen, Papierkorb und
  Pseudonymisierung (`AccountManager::delete`, `Journal::forget`) um.
- **Papierkorb** für: Begriffe-Entwürfe ohne Revision, Kategorien, Herkünfte, Organisationen, Vorschläge, Konten.
  **Nicht** für: Verknüpfungen, Aliase, Übersetzungen (folgen dem Hauptobjekt), Protokolle. Veröffentlichte Begriffe
  werden nie gelöscht, sondern zurückgezogen (Tombstone, DEC-022) oder mit `is_active` ausgeblendet.
- **Konten:** vorläufig löschen mit Frist [30 Tage], Wiederherstellung durch Super-Admins, sofortige Löschung auf
  Antrag nach Datenschutzrecht (B3).
- **Löschregister** (B3) ersetzt die bisherige Regel «nach Wiederherstellung `account.deleted` im aktuellen Protokoll
  suchen», weil dieses Protokoll beim Zurückspielen mit überschrieben wird. Ablage: eigener Bucket bei Swiss Backup mit
  Objektsperre, getrennt vom Backrest/restic-Plan (#207).

## 10. Sicherung im Glossary (C2)

Litestream nach Swiss Backup (laufend) und Einzelsnapshot vor jedem Update durch `docker/entrypoint.sh` (die letzten 10
lokal), ausser Haus 30 Tage, verschlüsselt. Dateiname `glossary_<YYYY-MM-DD_HH-MM-SS>_v<version>.sqlite` mit
Begleitdatei (SHA-256, Zeilen je Tabelle). RPO/RTO für zentrale Dienste: 1 Minute / 4 Stunden (Plattform-Entscheid E4,
12.11.2026).

## 11. Tests im Glossary (C3)

| Test                                                       | Prüft                                                                               |
| ---------------------------------------------------------- | ----------------------------------------------------------------------------------- |
| `tests/Unit/MigrationCompatibilityTest.php`                | Abwärtskompatibilität der Migrationen                                               |
| `tests/Feature/DataModelTest.php`, `GlossaryModelTest.php` | Tabellen, Beziehungen, Revisionen                                                   |
| `tests/Feature/PublicBoundaryTest.php`                     | Veröffentlichungsgrenze                                                             |
| `tests/Feature/PrivacyTest.php`, `AccountTest.php`         | Kontolöschung, Pseudonymisierung, Fristen                                           |
| `tests/Feature/OrganizationTest.php`                       | Mandantentrennung mit zwei Organisationen (ausbauen nach B1)                        |
| `tests/Feature/RestoreTest.php`                            | Rücknahme von Revisionen                                                            |
| neu: Löschregister                                         | Sicherung zurückspielen, Register anwenden, keine registrierten Personendaten mehr  |
| neu: Papierkorb                                            | vorläufig löschen, wiederherstellen, endgültig löschen                              |
| neu: Schemaprüfung                                         | STRICT, CHECK, `integrity_check`, `foreign_key_check`; CI auf SQLite und PostgreSQL |

Zusätzlich zur PR-Checkliste des Standards: API-Werte unverändert oder Abgleich im Intranet (sek-intranet) informiert;
CHANGELOG ohne Entscheidnummern; Commit mit `Refs #n`.

## 12. DB-Standard 1.2 im Glossary (Teil E, B7–B14)

| Regel | Im Glossary | Umsetzung |
| --- | --- | --- |
| E1 Modulschlüssel | `glossary` | – |
| E2 Schema je Modul | SQLite-Datei `glossary.sqlite`; auf PostgreSQL Schema `glossary` | mit PostgreSQL #191 |
| E3 Plattform-IDs | `platform_id` in `organizations` und `users`; Organisations-ID aus Ident beim Anschluss (#186) | #311 |
| E5 Versionsbindung | dieses Dokument: «arbeitet nach DB-Standard 1.2»; Konformitätstests aus `eduksx/core`, sobald herausgelöst | – |
| B8 Auskunft und Datenherausgabe | «Meine Daten» und Export in der Administration | #312 |
| B9 Zweck je Tabelle | im Code neben den Datenklassen (#276); daraus das Verzeichnis der Bearbeitungen | #314 |
| B10 Folgenabschätzung | keine besonders schützenswerten Daten; Kurz-Folgenabschätzung vor dem öffentlichen Betrieb zusammen mit der Prüfung der Fristen | #278 |
| B11 Lesezugriffe | entfällt (keine Daten der Klasse besonders schützenswert) | – |
| B12 Anonymisierte Kopien | Dump (#268) für dev und test nur anonymisiert | #313 |
| B13 Einschränkung | `users.is_processing_restricted` | #312 |
| B14 Zusammenlegen | heute kein Zusammenlegen geplant; Intranet-Abgleich bleibt API | – |

## 13. Offene Punkte

Alle Punkte sind entschieden (12.11.2026, DEC-033); umgesetzt werden sie über die genannten Issues.

| Nr. | Thema                      | Entscheid                                                                                                                      | Umsetzung                                                         |
| --- | -------------------------- | ------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------- |
| W1  | PostgreSQL                 | DEC-010 abgelöst: SQLite zu Beginn, PostgreSQL für den zentralen Betrieb; CI gegen beide                                       | Konzepte nachgeführt (#274); PostgreSQL #191; CI gegen beide #287 |
| W2  | Umbenennen                 | neuer Anfangsstand mit Dateien vor dem Stichtag des Kompatibilitätstests; Test bleibt unverändert; Übergangs-Migration für dev | 0.12.0                                                            |
| W3  | Migrationen zusammenfassen | jetzt (O4); alte Antwort in KONZEPT-DATENBANK als überholt markiert                                                            | #274                                                              |
| W4  | Enums                      | mit 0.12.0, mit CHECK-Regeln                                                                                                   | #275                                                              |
| W5  | Super-Admin                | Plattformrolle aus Ident, bis dahin Developer (DEC-032)                                                                        | KONZEPT-BENUTZER nachführen                                       |
| W6  | Kontolöschung              | Löschkonzept 1.1 und Datenschutzerklärung nachgeführt; Fristen in Klammern                                                     | #274                                                              |
| W7  | Datenklassen               | je Spalte im Code, Test, erzeugte Übersicht                                                                                    | #276                                                              |
| W8  | Identitäten                | `issuer` + `subject` mit 0.12.0                                                                                                | #277                                                              |
| –   | Löschfristen               | rechtlich prüfen vor dem öffentlichen Betrieb                                                                                  | #278                                                              |
| –   | Row-Level Security         | Prototyp mit PostgreSQL                                                                                                        | EduKSX-Architektur #13                                            |

## Verlauf

- 12.11.2026: Version 1.0 abgenommen (DEC-031, #270).
- 12.11.2026: Version 1.1 abgenommen (DEC-032) – allgemeine Regeln in den Plattform-Standard verschoben, Glossary-Teil mit Geltungsbereichen,
  Datenklassen, Löschregister, zusammengesetztem Fremdschlüssel für `published_revision_id`, Archivfrist (#272).
- 12.11.2026: offene Punkte W1–W8 entschieden (DEC-033, #274).
- 13.11.2026: Version 1.2 – Anwendung von DB-Standard 1.2 (Modularität, Datenschutz) (#310).
