# Changelog – EduKSX-Glossary

Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/), Versionen nach
[Semantic Versioning](https://semver.org/lang/de/) (MAJOR.MINOR.PATCH). Neue Einträge kommen laufend unter
**Unveröffentlicht**; `python3 .github/blueprint/release.py set X.Y.Z --commit` macht daraus eine Version.

## [Unveröffentlicht]

### Neu

- **Datenmodell (#12):** Begriffe, Kategorien, Herkunft, Schreibweisen und Revisionen in SQLite. Jede Änderung
  ergibt eine unveränderliche Revision; öffentlich ist nur die freigegebene, Änderungen danach bleiben Entwurf.
  Jede Veröffentlichung erzeugt einen neuen Datenstand mit Prüfsumme (für den Sync). Offizielle Felder nur mit
  Recht änderbar, mehrdeutige Abkürzungen erlaubt, IDs aus dem Intranet bleiben erhalten. Administration zeigt
  Anzahl Begriffe, Listen und Datenstand.
- **Startdatei `start.bat` (#20):** lokaler Start unter Windows per Doppelklick – richtet beim ersten Start alles
  ein und fragt nach dem Admin, sonst Pakete und Datenbank nachführen; `start.bat --pull` holt zuerst den neuesten
  Stand.

### Behoben

- **Versionen auf dem Server (#20):** `CHANGELOG.md` wurde beim Deployment (`git archive`) ausgelassen; die Seite
  «Versionen» wäre leer geblieben. Ausserdem löst eine Änderung am CHANGELOG jetzt ein Deployment aus.

## [0.1.0] – 2026-11-07

### Neu

- **Programmiergerüst (#7):** Laravel 13, Inertia 3, React 19, Tailwind 4 wie im Intranet, SQLite als Datenbank.
  Öffentliche Startseite und Seite «Versionen» (aus diesem CHANGELOG, mit Developer Release), Anmeldung lokaler
  Konten und Administration – ohne Anmeldung lassen sich keine Daten ändern; erster Admin per
  `php artisan glossary:admin`. API-Einstieg `/api/v1` mit OpenAPI 3.1. Auftritt nach CI MarKusSXCH mit Wortmarke
  EduKSX-Glossary; Bausteine aus dem Intranet übernommen. Docker-Image, Prüfung (`ci`) und Deployment auf den
  Hostinger-VPS (`deploy`, eingeschaltet mit `DEPLOY_ENABLED`).
- **Projektkonzept** (`docs/KONZEPT*.md`, Register `docs/ENTSCHEIDE.md`): aus Konzept V1.2 (ChatGPT) und dem
  Glossar des Intranets zusammengeführt – Datenmodell, API und Sync, Stack (SQLite), Auftritt nach CI MarKusSXCH,
  Versionierung, Confluence und MCP (später).
- **Konzept Hosting (#4):** `docs/KONZEPT-HOSTING.md` – zuerst Container auf dem Hostinger-VPS hinter Traefik,
  Infomaniak später prüfen; Domain `glossary.eduksx.ch` über Cloudflare; GitHub Pages bleibt als reduzierte,
  statische Lesefassung.
- **Projekt-Setup** nach my-github-blueprint: Issue-Vorlagen, Labels, Meilensteine, Release-Ablauf, statische
  Übergangsseite über GitHub Pages.
