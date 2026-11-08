# Barrierefreiheit – Prüfprotokoll (WCAG 2.1 AA)

Stand 08.11.2026 (#139, Release 0.8.0). Prüfung am Code und mit den automatischen Tests (Kontrast:
`tests/Unit/ContrastTest.php`); eine Prüfung mit Bildschirmleser und Tastatur im Browser steht noch aus und wird
hier nachgetragen.

## Geprüfte Seiten

Startseite, Begriff, Vorschlag einreichen, Meine Vorschläge, Mein Konto, Anmelden/Registrieren, Hilfe;
Administration: Begriffe, Vorschläge, Listen, Benutzer, Servicekonten, Einstellungen.

## Befunde und Behebung

| Kriterium                  | Befund                                                                                         | Behebung                                                                               |
| -------------------------- | ---------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------- |
| 2.1.1 Tastatur             | Bestätigungen über `window.confirm` (10 Stellen), Fokus nicht steuerbar                        | Dialog `components/confirm-dialog.tsx` (Fokus im Dialog, Escape, `role="alertdialog"`) |
| 4.1.2 Name, Rolle, Wert    | Umschalter Ansicht/Farbschema nur mit Text bzw. gleichem Symbol                                | Symbol-Knöpfe mit `aria-label`, `aria-pressed`, Tooltip (#142)                         |
| 4.1.3 Statusmeldungen      | Meldungen nach Aktionen auf der Website nicht überall sichtbar                                 | Meldung einheitlich oben mit `role="status"` (Layout Website)                          |
| 1.3.1 Info und Beziehungen | Filter-Labels ohne Zustand für Bildschirmleser                                                 | `aria-pressed` und Gruppenname (#145)                                                  |
| 1.4.10 Reflow              | Vergleichstabellen (Zusammenführen, Vorschlag prüfen) verlieren beim Scrollen die erste Spalte | erste Spalte fixiert                                                                   |
| 1.4.3 Kontrast             | Labels, Status, CI-Farben hell/dunkel                                                          | Test `ContrastTest` (AA), Druckfarben dunkler                                          |
| 2.4.3 Fokusreihenfolge     | Kopfzeile Website/Administration verschieden                                                   | einheitliche Kopfzeile (#146)                                                          |

## Offen

- Prüfung mit NVDA/VoiceOver und nur Tastatur im Browser (Ergebnis hier nachtragen).
- Tabellen-Splitter: Bedienung mit Pfeiltasten vorhanden; Ansage der Breite prüfen.
