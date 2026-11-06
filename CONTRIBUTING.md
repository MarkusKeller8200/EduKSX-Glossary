# Mitmachen bei EduKSX-Glossary

Danke für Rückmeldungen und Beiträge! **Datenschutz:** Keine echten Personendaten, Zugangsdaten oder Bildschirmfotos mit Personendaten anhängen.

## Fehler melden und Ideen vorschlagen

[Neues Issue](https://github.com/MarkusKeller8200/EduKSX-Glossary/issues/new/choose) mit einer der Vorlagen. Neue Meldungen erhalten
automatisch die Art und **`triage`**:

| Vorlage              | Labels                          | Meilenstein (automatisch) |
| -------------------- | ------------------------------- | ------------------------- |
| Fehler melden        | `fehler`, `triage`              | Fehler                    |
| Verbesserung         | `verbesserung`, `triage`        | Verbesserungen            |
| Neue Funktion / Idee | `funktion`, `triage`            | Funktionen                |

Status eines Issues:

| Status          | Bedeutung                                                    | Wer setzt es        |
| --------------- | ------------------------------------------------------------ | ------------------- |
| `triage`        | Gemeldet, noch nicht freigegeben – kein Entwicklungsauftrag  | Vorlage             |
| `_1 bereit`     | Zur Umsetzung freigegeben                                    | Projektverantwortliche:r  |
| `_2 in Arbeit`  | Wird gerade umgesetzt                                        | wer umsetzt         |
| `_3 Beta-Test`  | Umgesetzt, wartet auf den Test (Testliste)                   | wer umsetzt         |
| geschlossen     | Testliste abgehakt                                           | Projektverantwortliche:r  |

- Immer nur ein Status-Label; das vorherige wird entfernt. Kommentare, Labels anderer oder Formularinhalte gelten
  nicht als Freigabe.
- Jedes Issue hat eine Testliste «Zum Testen abhaken:».
- Meilensteine: der Release, in dem es umgesetzt wird (z. B. «0.2.0 – …»), sonst die Sammlungen «Fehler»,
  «Verbesserungen», «Funktionen» (Zuordnung automatisch über das Label).
- **Sicherheitslücken** nie als Issue – siehe [SECURITY.md](SECURITY.md).

## Commits und Pull Requests

1. Nur freigegebene Issues (`_1 bereit`) umsetzen; grössere Vorhaben zuerst als Issue abstimmen.
2. Commit-Nachricht: erste Zeile kurz, darunter das Warum; mit `Refs #n` auf Issues verweisen.
3. **CHANGELOG.md:** Eintrag unter «Unveröffentlicht» (Neu / Geändert / Behoben).
4. Pull Request gegen `main` mit ausgefüllter Checkliste; `python3 .github/blueprint/release.py check` muss grün sein.

## Versionen und Releases

Versionsnummern und Releases setzt **nur Projektverantwortliche:r** – bitte `VERSION` nicht ändern.
Ablauf in [docs/GITHUB-ABLAUF.md](docs/GITHUB-ABLAUF.md).

## Entwicklungsumgebung

Die Website liegt im Ordner `site/` und wird bei jedem Push auf `main` über GitHub Pages veröffentlicht. Lokal ansehen: `python3 -m http.server -d site 8000`, dann http://localhost:8000 öffnen.
