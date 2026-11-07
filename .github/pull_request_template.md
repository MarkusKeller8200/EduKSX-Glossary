## Was ändert sich?

<!-- Kurz beschreiben, was der Pull Request tut und warum. -->

Refs #
<!-- bzw. Fixes # – verknüpftes Issue. Umgesetzt wird nur, was mit `_1 bereit` freigegeben ist. -->

## Art der Änderung

- [ ] Fehlerbehebung (`fehler`)
- [ ] Verbesserung bestehender Funktion (`verbesserung`)
- [ ] Neue Funktion (`funktion`)
- [ ] Dokumentation / Build / CI

## Wie getestet?

<!-- z. B. neue Tests, von Hand geprüft mit … -->

## Checkliste

- [ ] `composer ci:check` läuft lokal ohne Fehler
- [ ] Neue oder geänderte Funktionen haben Tests
- [ ] Eintrag in `CHANGELOG.md` unter «Unveröffentlicht» (Neu / Geändert / Behoben)
- [ ] Doku nachgeführt
- [ ] Begriffe mit Quelle bzw. Definition, alphabetisch eingeordnet
- [ ] Keine echten Personendaten
- [ ] `VERSION` nicht geändert (Versionen setzt Projektverantwortliche:r)

## Gestaltung (bei Änderungen an der Oberfläche, CI-Manual Abschnitt 13)

- [ ] Wortmarke exakt («EduKSX-Glossary», Segmentfarben unverändert)
- [ ] Segment- und Markenfarben nur für Wortmarke und Akzente, Orange nie als Schrift
- [ ] Hell und dunkel geprüft
- [ ] Kontrast AA: Text ≥ 4.5 : 1, Linien und Fokus ≥ 3 : 1 (`tests/Unit/ContrastTest.php` für die Farbwerte)
- [ ] Farbige Labels nur aus der Palette (`resources/js/lib/colors.ts`)

## Bildschirmfotos

<!-- bei Änderungen an der Oberfläche – ohne echte Personendaten -->
