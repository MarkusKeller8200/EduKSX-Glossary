# EduKSX-Glossary

Öffentliches Glossar mit Begriffen und Abkürzungen für Schweizer Volksschulen, Schulgemeinden und Gemeinden –
Website und REST-API. Ziel: **https://glossary.eduksx.ch** (in Aufbau); reduzierte Lesefassung:
**https://markuskeller8200.github.io/EduKSX-Glossary/**

## Stack

Wie das [sek-intranet](https://github.com/MarkusKeller8200/sek-intranet): Laravel 13 (PHP 8.4), Inertia 3, React 19,
TypeScript, shadcn/ui, Tailwind 4 – mit **SQLite** als Datenbank. Auftritt nach CI MarKusSXCH, GUI-Bausteine aus
dem Intranet übernommen. Konzepte: [docs/KONZEPT.md](docs/KONZEPT.md), Entscheide:
[docs/ENTSCHEIDE.md](docs/ENTSCHEIDE.md).

## Starten

Voraussetzungen: PHP 8.4, Composer, Node 22.

```bash
composer setup              # Pakete, .env, Schlüssel, SQLite, Migrationen, Build
php artisan glossary:admin  # ersten Admin anlegen – ohne Anmeldung lassen sich keine Daten ändern
composer dev                # http://localhost:8000
composer ci:check           # Prüfungen wie in der CI
```

Online stellen auf `https://glossary.eduksx.ch` (Hostinger-VPS, Docker, Traefik, Cloudflare): [DEPLOY.md](DEPLOY.md).

## Aufbau

| Pfad                           | Inhalt                                                                              |
| ------------------------------ | ----------------------------------------------------------------------------------- |
| `app/`, `routes/`, `database/` | Laravel: Website, Anmeldung, Administration, API `/api/v1`                          |
| `resources/js/`                | React-Seiten und Bausteine (aus dem Intranet, [Liste](docs/UEBERNAHME-INTRANET.md)) |
| `openapi/glossary-v1.yaml`     | Beschreibung der API (OpenAPI 3.1)                                                  |
| `site/`                        | reduzierte Lesefassung über GitHub Pages                                            |
| `VERSION`, `CHANGELOG.md`      | Version und Versionshistorie (Seite «Versionen»)                                    |
| `docs/`                        | Konzepte, Entscheide, Ablauf auf GitHub                                             |

## Mitmachen

Begriffe vorschlagen, Fehler melden oder Verbesserungen anregen über die
[Issue-Formulare](https://github.com/MarkusKeller8200/EduKSX-Glossary/issues/new/choose).
Wie Meldungen freigegeben, umgesetzt und veröffentlicht werden, steht in [CONTRIBUTING.md](CONTRIBUTING.md) und
[docs/GITHUB-ABLAUF.md](docs/GITHUB-ABLAUF.md).

## Unterstützen

Ein ⭐, Rückmeldungen über die Issues oder ein Kaffee auf [Ko-fi](https://ko-fi.com/markussxch) helfen weiter –
mehr in [docs/UNTERSTUETZEN.md](docs/UNTERSTUETZEN.md).

---

Eingerichtet mit [my-github-blueprint](https://github.com/MarkusKeller8200/my-github-blueprint)
(Konfiguration: `.github/blueprint.toml`).
