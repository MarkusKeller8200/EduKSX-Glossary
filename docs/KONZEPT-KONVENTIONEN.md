# Konzept: Konventionen für Namen und Werte

Stand: **beschlossen** 12.11.2026 (Chat, #266). Gilt für EduKSX-Glossary; als Vorlage für die ganze Produktfamilie ins
Plattform-Konzept (Repo EduKSX-Architektur) übernehmen.

## Grundsatz

**Technik englisch, Anzeige deutsch.** Alles, was Programme lesen – Tabellen, Spalten, Schlüssel, Werte von Status und
Arten, Felder und Werte der API, Ereignisnamen – ist englisch. Was Menschen lesen – Oberfläche, Hilfe, Excel-Spalten,
PDF, CHANGELOG, Konzepte – ist deutsch (Schweizer Schreibweise, ohne ß). Übersetzt wird an einer Stelle: über Labels im
Code (PHP-Enum bzw. TypeScript), nie in der Datenbank.

| Nr. | Regel                                                                                                                                                                                    |
| --- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| K1  | **Datenbank:** Namenskonzept N1–N12 in [KONZEPT-DATENBANK.md](KONZEPT-DATENBANK.md).                                                                                                     |
| K2  | **Werte** von Status und Arten: englisch, `snake_case`, als PHP-Enum (`App\Enums`) mit deutschem Label; dieselben Werte in Datenbank, API, Exporten für Maschinen (JSON, CSV-Schlüssel). |
| K3  | **API:** Feld- und Wertnamen englisch, `snake_case`; Version im Pfad (`/api/v1`). Werte sind Teil des Vertrags: neue Werte sind erlaubt, Umbenennen nur mit neuer Version (ab 1.0).      |
| K4  | **Excel und PDF** sind für Menschen: deutsche Spaltennamen und Labels; der Import nimmt deutsche Labels und englische Werte an.                                                          |
| K5  | **Routen der Website** deutsch (`/begriffe`, `/hilfe`), der API englisch.                                                                                                                |
| K6  | **Code:** Klassen, Methoden, Variablen englisch; bestehende deutsche Namen werden bei Gelegenheit angeglichen, nicht in einem eigenen Umbau.                                             |
| K7  | **Sprachen der Inhalte:** `de` (Grundsprache), `fr`, `it`, `en` als `locale` nach ISO 639-1.                                                                                             |

## Werte (neu ab 0.12.0)

| Feld                                                                                  | bisher                                                                                                                                       | neu                                                                                                                                                      |
| ------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `terms.lifecycle_status` (API `status`)                                               | `aktuell`, `historisch`, `zu_pruefen`                                                                                                        | `current`, `historical`, `under_review`                                                                                                                  |
| `proposals.status`                                                                    | `org_pruefung`, `eingereicht`, `rueckfrage`, `angenommen`, `abgelehnt`, `zurueckgezogen`                                                     | `organization_review`, `submitted`, `query`, `accepted`, `rejected`, `withdrawn`                                                                         |
| `proposal_events.type`                                                                | `eingereicht`, `org_bestaetigt`, `org_abgelehnt`, `org_weitergeleitet`, `rueckfrage`, `antwort`, `angenommen`, `abgelehnt`, `zurueckgezogen` | `submitted`, `confirmed_by_organization`, `rejected_by_organization`, `forwarded_by_organization`, `query`, `reply`, `accepted`, `rejected`, `withdrawn` |
| `term_sources.evidence`                                                               | `amtlich`, `belegt`, `sekundaer`, `offen`                                                                                                    | `official`, `documented`, `secondary`, `open`                                                                                                            |
| `users.institution_type`                                                              | `schule`, `schulgemeinde`, `gemeinde`, `kanton`, `andere`                                                                                    | `school`, `school_district`, `municipality`, `canton`, `other`                                                                                           |
| `roles.key`                                                                           | `gast`, `benutzer` (übrige bereits englisch)                                                                                                 | `guest`, `user`; Anzeigenamen bleiben deutsch (#288)                                                                                                     |
| übrige (`users.status`, `alias_type`, `change_type`, `action`, Rollen der Mitglieder) | bereits englisch                                                                                                                             | unverändert                                                                                                                                              |

Die API v1 liefert ab 0.12.0 die neuen Werte. **Betroffen ist der Abgleich im Intranet** (sek-intranet liest `status`):
vor dem Ausrollen auf test/prod dort nachführen.
