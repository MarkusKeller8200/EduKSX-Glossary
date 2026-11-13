# Konzept: Versionen und Versionshistorie

**Abgenommen** am 07.11.2026 (#6). Der allgemeine Ablauf – Issues mit Freigabe, Status-Labels,
Meilensteine, Releases – kommt aus dem [GitHub-Blueprint](https://github.com/MarkusKeller8200/my-github-blueprint)
und steht in [GITHUB-ABLAUF.md](GITHUB-ABLAUF.md). Hier steht, was im Glossar dazukommt; angelehnt an
`docs/KONZEPT-VERSIONIERUNG.md` im sek-intranet.

## Versionen der Software

- Version in `VERSION`, SemVer. Solange 0.x: Aufbau. **1.0.0 = erster öffentlicher Betrieb.**
- Von Beginn an: jede Änderung unter **«Unveröffentlicht»** im [CHANGELOG](../CHANGELOG.md) (Neu, Geändert,
  Behoben), mit Issue-Nummer.
- Eine neue Versionsnummer, ein Tag `vX.Y.Z` und ein GitHub-Release entstehen nur bei einem freigegebenen Release
  (`python3 .github/blueprint/release.py set X.Y.Z --commit`). Der erste Tag ist `v0.1.0` mit Abschluss des
  Meilensteins «0.1.0 – Konzept, Hosting und Stack».
- **Bezeichnung je Umgebung (DEC-036, #308):**

    | Umgebung | Art       | Bezeichnung     | Bedeutung                                                                    |
    | -------- | --------- | --------------- | ---------------------------------------------------------------------------- |
    | prod     | Release   | `0.13.0`        | MAJOR.MINOR.PATCH; Tag `v0.13.0`, GitHub-Release                             |
    | test     | Beta      | `0.13.0-beta.2` | Vorabversion der geplanten Version; Tag `v0.13.0-beta.2`, Pre-Release        |
    | dev      | Developer | `0.13.0-dev.5`  | geplante nächste Version, dann die Zahl der Commits seit dem letzten Release |
    - Beta und Developer zählen mit Punkt (`beta.2`, `dev.5`), damit `beta.10` nach `beta.9` einsortiert wird.
    - Developer ist nur eine Anzeige: kein Eintrag in `VERSION`, kein Tag, kein Release. Die Nummer kommt vom
      Deployment (`git describe --tags --long` gegen den letzten Release-Tag, Betas zählen nicht als Basis) und läuft
      über Betas hinweg weiter; nach einem Release beginnt sie neu.
    - Die geplante Version ist Minor, ausser unter «Unveröffentlicht» steht nur «Behoben» (dann Patch). Kommt im Lauf
      ein «Neu» dazu, springt die Bezeichnung z. B. von `0.12.1-dev.3` auf `0.13.0-dev.4`. Nach einer Beta gilt deren
      Version (`0.13.0-beta.1` → Developer `0.13.0-dev.N`).
    - Reihenfolge: Developer- und Beta-Bezeichnungen werden nirgends verglichen. Der Update-Hinweis vergleicht nur
      Releases. Strenges SemVer sortiert `dev` nach `beta` (alphabetisch), PHP umgekehrt; darum keine Logik auf diese
      Reihenfolge bauen.
    - Bei einem Release- oder Beta-Commit gibt das Deployment die Version selbst als Stand weiter, da der Tag erst nach
      dem Bauen entsteht; so zeigen test und prod nie einen Developer-Stand.

## Versionshistorie auf der Website

- Fuss jeder Seite: Version bzw. Developer Release, Link auf «Versionen».
- Seite **«Versionen»**: alle Einträge aus dem CHANGELOG, neueste zuerst; «Unveröffentlicht» als Developer
  Release markiert. Knöpfe «Fehler melden» und «Idee vorschlagen» füllen die laufende Version im Issue-Formular vor.
- Optional je Version ein Abschnitt `### Für Nutzer:innen` in Alltagssprache (wie «Für Mitarbeitende» im Intranet).

## Versionen der Daten (Vorschlag)

Unabhängig von der Software:

- **Revision je Begriff:** jede Veröffentlichung eines Eintrags erhöht `revision`; frühere Revisionen bleiben
  erhalten (Verlauf auf der Detailseite).
- **Datenstand (Publikationsrelease):** jede Freigabe erzeugt einen neuen Datenstand mit Nummer, Zeitpunkt und
  Prüfsumme (`/api/v1/releases/latest`). Ihn nutzt das Intranet für den Sync und ETag.
- Die Dokumentversion des Konzepts (V1.2) ist weder Software- noch Datenversion.

## API-Versionen

- `/api/v1` bleibt rückwärtskompatibel: Felder nur hinzufügen. Was bricht, kommt in `/api/v2`; v1 läuft mit
  Ankündigung im CHANGELOG weiter.
- Die OpenAPI-Datei trägt die API-Version; Änderungen daran stehen im CHANGELOG.
