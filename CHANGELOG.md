# Changelog – EduKSX-Glossary

Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/), Versionen nach
[Semantic Versioning](https://semver.org/lang/de/) (MAJOR.MINOR.PATCH). Neue Einträge kommen laufend unter
**Unveröffentlicht**; `python3 .github/blueprint/release.py set X.Y.Z --commit` macht daraus eine Version.

## [Unveröffentlicht]

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
