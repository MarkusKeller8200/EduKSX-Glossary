# Konzept: Versionen und Versionshistorie

**Entwurf** vom 07.11.2026 zur Abnahme (#6). Der allgemeine Ablauf – Issues mit Freigabe, Status-Labels,
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
- **Developer Release:** Jeder Push auf `main` wird ausgerollt. Ein Stand mit Commits nach dem letzten Tag zeigt
  z. B. «0.1.0 · Developer Release (+5)». Das Deployment übergibt `git describe --tags --long` (wie im Intranet).

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
