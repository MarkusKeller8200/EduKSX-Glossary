# Verzeichnis der Bearbeitungen – Modul `glossary`

Erzeugt aus `app/Data/Purposes.php` und `app/Data/DataClasses.php` mit `php artisan glossary:verzeichnis` – nicht
von Hand ändern. Vorlage: EduKSX-Architektur `docs/vorlagen/DATENSCHUTZ-VERZEICHNIS.md`. Grundlage: DB-Standard
B7–B9, API-Standard C9. Arbeitshilfe, keine Rechtsberatung; rechtliche Prüfung offen (#278, #66). Fristen in
eckigen Klammern sind noch nicht rechtlich geprüft.

## 1. Angaben zum Modul

| Feld | Inhalt |
| --- | --- |
| Modul (Schlüssel, Marketingname) | `glossary`, EduKSX-Glossary |
| Betriebsart | zentral; selbst gehostet möglich (docs/SELBST-HOSTEN.md) |
| Verantwortliche (Datenherrschaft) | Betreiber des Glossary; bei Organisationen die Schule bzw. Gemeinde für ihre Darstellung |
| Anwendbares Recht | IDG ZH / DSG / DSGVO (strengstes gilt, DB B7) |
| Höchste Datenklasse | personenbezogen (Konten); keine besonders schützenswerten Daten |
| Folgenabschätzung nötig? | Kurz-Folgenabschätzung vor dem öffentlichen Betrieb (DB B10, #278) |
| Datenort | Hosting, Sicherungen und Protokolle in der Schweiz (docs/KONZEPT-HOSTING.md) |

## 2. Bearbeitungen

| Zweck | Rechtsgrundlage / Auftrag | Geltungsbereich | Betroffene | Datenkategorien (höchste Klasse je Tabelle) | Tabellen | Operationen (`operationId`) | Empfänger | Löschfrist |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Öffentliches Begriffsverzeichnis: Begriffe erfassen, prüfen, veröffentlichen, suchen und an Abnehmer abgeben | Auftrag des Betreibers (Verzeichnis für Schulen und Gemeinden); veröffentlichte Daten unter CC BY 4.0 | global | keine (Fachdaten); im Journal Redaktion als Bezeichnung | intern (12); personenbezogen (2) | `terms`, `term_aliases`, `term_categories`, `term_sources`, `term_translations`, `term_relations`, `categories`, `category_translations`, `sources`, `source_translations`, `term_revisions`, `term_publication_events`, `publication_releases`, `duplicate_decisions` | `getStatus`, `listTerms`, `getTerm`, `getIndex`, `listCategories`, `listSources`, `getLatestRelease`, `listChanges`, `exportTerms` | öffentlich (Website, API, Change-Feed, Exporte, GitHub Pages) | dauerhaft; Begriffe werden zurückgezogen statt gelöscht; Personenbezug im Journal bei Kontolöschung entfernt |
| Konten, Anmeldung und Rechte (Redaktion, Moderation, Verwaltung, Community) | Nutzungsbedingungen bei der Registrierung; Anmeldung mit Microsoft nur nach Verknüpfung durch die Person | Plattform/intern | registrierte Personen (Lehrpersonen, Schulverwaltung, Gemeinden, Fachpersonen) | geheim (1); personenbezogen (1); intern (3) | `users`, `user_identities`, `roles`, `user_roles`, `role_permissions` | – | Verwaltung des Glossary; Mail-Versand (Bestätigung, Hinweise) | unbestätigt 7 Tage; inaktiv ohne Rolle 24 Monate (Hinweis 30 Tage vorher); auf Wunsch endgültig nach [30 Tagen] |
| Nachvollziehbarkeit und Informationssicherheit: Änderungen, Rechte, Anmeldedienste, Servicekonten | Pflicht zur Informationssicherheit und Nachvollziehbarkeit von Änderungen | Plattform/intern | Personen, die etwas ändern oder deren Konto geändert wird | personenbezogen (3) | `user_audit`, `service_account_audit`, `data_changes` | – | Developer und Verwaltung des Glossary | [5 Jahre] nach Eintrag; personenbezogene Werte nie gespeichert (nur «geändert»); Bezug bei Kontolöschung entfernt |
| Organisationen (Schulen, Gemeinden): Mitglieder, Einladungen, Darstellung der Organisation | Nutzungsbedingungen; Organisation durch eine berechtigte Person angelegt | Organisation | Mitglieder und eingeladene Personen einer Organisation | intern (3); personenbezogen (1) | `organizations`, `organization_members`, `organization_invitations`, `term_organization_overrides` | `listOrganizationTerms`, `getOrganizationTerm` | Mitglieder der Organisation; Servicekonten der Organisation (Scope glossary:terms.read) | Einladungen bis Ablauf; Organisation im Papierkorb [30 Tage], dann endgültig |
| Persönliche Darstellung von Begriffen («Meine Darstellung») | Nutzungsbedingungen; freiwillige Angaben der Person | Persönlich | die Person selbst | personenbezogen (1) | `term_user_overrides` | – | nur die Person; nach Freigabe ihre Organisation | mit dem Konto |
| Vorschläge für neue Begriffe und Korrekturen, Moderation | Nutzungsbedingungen; Mitwirkung am Verzeichnis | Organisation bzw. Persönlich | Personen und Servicekonten, die Vorschläge einreichen; Moderation | personenbezogen (2) | `proposals`, `proposal_events` | `createProposal`, `getProposal`, `withdrawProposal` | Moderation; Organisations-Admins für Vorschläge ihrer Mitglieder | 24 Monate nach Abschluss mit Personenbezug, dann ohne; bei Kontolöschung sofort ohne |
| Schnittstellen für Integrationen (Servicekonten und Tokens), Ereignisse der Plattform | Nutzungsbedingungen; Servicekonto durch eine berechtigte Person angelegt | Organisation bzw. Plattform/intern | verantwortliche Person des Servicekontos | intern (3); geheim (1) | `service_accounts`, `service_account_tokens`, `received_events`, `api_idempotency_keys` | `getServiceWhoami` | Integrationen mit gültigem Token (z. B. Intranet); EduKSX-Ident (Ereignisse) | Tokens bis Ablauf bzw. Widerruf; Ereignis-IDs 30 Tage; Idempotency-Keys 24 Stunden |
| Betrieb und Verbesserung: Einstellungen, Importe aus dem Intranet, Suchen ohne Treffer | Betrieb des Verzeichnisses | Plattform/intern | Person, die importiert (als Bezeichnung); sonst keine | intern (2); personenbezogen (1) | `settings`, `import_batches`, `search_misses` | – | Redaktion und Verwaltung des Glossary | Import-Protokoll 12 Monate; Suchen ohne Treffer 90 Tage nach dem letzten Auftreten; Einstellungen dauerhaft |

## 3. Bekanntgaben und Unterauftragnehmer

| Empfänger | Zweck | Daten | Ort | Grundlage / Vertrag |
| --- | --- | --- | --- | --- |
| Hosting (Infomaniak bzw. eigener Server) | Betrieb | alle | Schweiz | Auftragsbearbeitung |
| Mail-Versand | Bestätigung, Hinweise | E-Mail-Adresse, Name | Schweiz | Auftragsbearbeitung |
| Microsoft Entra ID | Anmeldung (nur nach Verknüpfung) | Kennung, Mandant | gemäss Vertrag der Schule | Einwilligung bei der Verknüpfung |
| EduKSX-Ident (sobald angeschlossen) | Anmeldung, Ereignisse | Kennung, Rollen | Schweiz | Plattform S1, S4 |

## 4. Technische und organisatorische Massnahmen

- Geltungsbereiche getrennt (DB B1), Datenklassen je Spalte (DB B2, docs/DATENKLASSEN.md), Löschregister (DB B3)
- Zweiter Faktor für privilegierte Rechte; Servicekonten mit Scopes und befristeten Tokens
- Historie ohne personenbezogene Werte (DB A7); Sicherheitsprotokoll [5 Jahre]
- Sicherung vor jedem Update und ausser Haus (docs/LOESCHKONZEPT.md); Kopien für dev und test nur anonymisiert (DB B12)

## 5. Rechte der Betroffenen

| Recht | Umsetzung im Glossary |
| --- | --- |
| Auskunft, Datenherausgabe | «Mein Konto» → «Meine Daten» (JSON) und Export in der Verwaltung (DB B8) |
| Berichtigung | «Mein Konto»; Verwaltung |
| Löschung | «Mein Konto» → Konto löschen; `ident.user.deleted` (S4); Löschkonzept |
| Einschränkung | `users.is_processing_restricted` (DB B13) |
