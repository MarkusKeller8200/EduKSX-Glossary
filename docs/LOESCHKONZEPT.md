# Löschkonzept (#42)

**Version 1.0 · Stand 07.11.2026 · Status: vorläufig.** Die Fristen in eckigen Klammern sind fachlich und rechtlich noch
zu prüfen (Freigabecheck in [DATENSCHUTZ-ENTWURF.md](DATENSCHUTZ-ENTWURF.md) Abschnitt 11, DEC-019). Umgesetzt durch
`php artisan glossary:loeschfristen`. Der Befehl läuft täglich um 03:15 Uhr über den Dienst `scheduler`; mit
`--trocken` zeigt er nur an, was er tun würde.

| Datenkategorie                                | Frist und Auslöser                                                                      | Umsetzung                                                                   | Ausnahmen                                                                          |
| --------------------------------------------- | --------------------------------------------------------------------------------------- | --------------------------------------------------------------------------- | ---------------------------------------------------------------------------------- |
| Unbestätigte Registrierung                    | 7 Tage nach Anlage                                                                      | `glossary:loeschfristen`                                                    | –                                                                                  |
| Inaktives Community-Konto (ohne Rolle)        | 24 Monate ohne Anmeldung; Hinweis per Mail 30 Tage vorher (`inactivity_notice_sent_at`) | `glossary:loeschfristen`; Anmelden setzt den Hinweis zurück                 | ohne Mailversand kein Hinweis und keine Löschung; Konten mit Rolle nie automatisch |
| Konto auf Wunsch                              | sofort («Mein Konto» → Konto löschen)                                                   | `AccountManager::delete`                                                    | letztes Developer- bzw. Verwaltungskonto                                           |
| Sitzungen, «angemeldet bleiben»               | beim Abmelden, Sperren, Löschen, Passwortwechsel; sonst nach [120] Minuten Inaktivität  | Datenbank-Sitzungen, `remember_token`                                       | –                                                                                  |
| Persönliche Darstellung («Meine Darstellung») | mit dem Konto                                                                           | `TermUserOverride`, beim Löschen jedes Kontos (`Journal::forget`)           | –                                                                                  |
| Änderungsjournal der Begriffe                 | bleibt; beim Löschen des Kontos Bezeichnung → «Gelöschtes Konto», Bezüge leer           | `Journal::forget` (DEC-020)                                                 | Bezeichnungen ohne Personenbezug (z. B. «Excel-Import») bleiben                    |
| Altwerte aus dem Intranet-Format              | bis zur Zuordnung; danach als Nachweis beim Begriff                                     | `term_legacy_values`, Seite «Altwerte»                                      | keine Personendaten; nie öffentlich                                                |
| Verknüpfte Anmeldedienste                     | beim Lösen der Verknüpfung oder Löschen des Kontos                                      | (mit #45)                                                                   | –                                                                                  |
| Letzte Anmeldung                              | nur das Datum, überschrieben bei jeder Anmeldung                                        | `users.last_login_on`                                                       | –                                                                                  |
| Audit-Protokoll (Rollen, Rechte, Konten, MFA) | [5 Jahre] nach Eintrag                                                                  | `glossary:loeschfristen` (`GLOSSARY_RETENTION_AUDIT_YEARS`)                 | Eintrag zum Löschen eines Kontos enthält nur die ID                                |
| Vorschläge und Moderation                     | [noch festzulegen mit der Vorschlagsfunktion]                                           | –                                                                           | nach Kontolöschung ohne Personenbezug («Community»)                                |
| Server-Logs                                   | [14 Tage]                                                                               | Docker rotiert je Dienst nach Grösse (3 × 10 MB, `compose.prod.yaml`); 14 Tage über den Host (DEPLOY.md) | –                                                                                  |
| Fehlgeschlagene Mail-Aufträge                 | [30 Tage]                                                                               | `queue:prune-failed` (täglich)                                              | –                                                                                  |
| Sicherungen der Datenbank                     | die letzten 10 vor jedem Update; ausser Haus [30 Tage]                                  | `docker/entrypoint.sh`; Sicherung ausser Haus offen                         | Wiederherstellung: Löschfristen danach erneut anwenden                             |

## Nach einer Wiederherstellung

Wird eine Sicherung zurückgespielt, sofort `php artisan glossary:loeschfristen` ausführen. Seit der Sicherung
gelöschte Konten (Einträge `account.deleted` im aktuellen Protokoll) nochmals löschen und gesperrte Konten erneut
sperren.

## Änderungen

Änderungen an Fristen erhöhen die Version dieses Dokuments und werden in der Datenschutzerklärung nachgeführt.
