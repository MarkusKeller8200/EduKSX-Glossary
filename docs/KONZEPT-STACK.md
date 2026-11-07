# Konzept: Programmierstack

**Entwurf** vom 07.11.2026 zur Abnahme (#5, DEC-009/DEC-010). Ziel laut Konzept: am sek-intranet ausrichten
(Laravel/React ist Favorit), damit Bausteine, Werkzeuge und Arbeitsweise gleich bleiben.

## Vorschlag

| Schicht    | Technologie                                                         | wie im Intranet |
| ---------- | ------------------------------------------------------------------- | --------------- |
| Backend    | Laravel 13 (PHP 8.4)                                                | ja              |
| Frontend   | React 19 + TypeScript, Inertia 3, shadcn/ui (Radix), Tailwind 4     | ja              |
| GUI-Bausteine | aus dem Intranet übernommen (`components/ui`, `data-grid`, `term`), Farbwerte nach CI MarKusSXCH ([KONZEPT-DESIGN.md](KONZEPT-DESIGN.md)) | ja, Aussehen anders |
| Build      | Vite (Vite Plus wie im Intranet)                                    | ja              |
| Datenbank  | **PostgreSQL 17** (Container), Tests mit SQLite                     | Ziel im Intranet |
| API        | Laravel-Routen `/api/v1`, OpenAPI 3.1 (`openapi/glossary-v1.yaml`), Doku mit Scalar | ja (Scalar) |
| Excel      | XLSX lesen/schreiben (Bibliothek wie im Intranet bzw. PhpSpreadsheet) | ja           |
| Anmeldung  | Admin/Redaktion: Laravel-Session mit zweitem Faktor; Community offen | anders (Intranet: OIDC später) |
| Tests      | PHPUnit, PHPStan Level 7 (Larastan), Pint, `vp check`               | ja              |
| Prüfbefehl | `composer ci:check` (inkl. `release.py check`)                      | ja              |
| Betrieb    | Docker-Image (PHP 8.4 + Apache), Compose, Traefik auf dem Hostinger-VPS | ja          |
| Deployment | GitHub Actions → SSH → `docker compose up --build`, Prüfadresse `/up` | ja            |

### Warum so

- **Gleich wie das Intranet:** Komponenten (Tabelle `data-grid`, Formulare, Term-Tooltip, Themes), Glossar-Logik
  (`Glossary.php`, Import, Doppelte) und Werkzeuge lassen sich gezielt übernehmen; ein Stack für beide Projekte.
- **Inertia für die Website, eigene Routen für die API:** Website und API rufen dieselbe Service-Schicht auf.
- **PostgreSQL statt JSON-Dateien:** öffentliche Mehrbenutzer-Bearbeitung, Revisionen, Moderation und Sync-Cursor
  brauchen Transaktionen und referentielle Integrität (Konzept V1.2, Abschnitt 7). Die JSON-Daten des Intranets
  sind die Quelle für die Übernahme.

### Aufbau im Repository

```text
app/            Laravel (Services, Controller, Models; Service-Schicht unter app/Glossary)
database/       Migrationen, Seeder (Startinhalt aus dem Intranet)
resources/js/   React-Seiten und Komponenten
routes/         web.php (Website, Redaktion), api.php (/api/v1)
openapi/        glossary-v1.yaml
docker/         compose.prod.yaml, entrypoint.sh
tests/          Feature-, API- und Sync-Tests
site/           reduzierte statische Fassung (GitHub Pages), Daten aus dem Export
```

## Varianten

| Punkt     | Vorschlag                    | Alternative                    | Abwägung                                                                         |
| --------- | ---------------------------- | ------------------------------ | -------------------------------------------------------------------------------- |
| Datenbank | PostgreSQL 17                | SQLite (eine Datei im Volumen) | SQLite ist einfacher zu sichern und braucht keinen zweiten Container; PostgreSQL ist für gleichzeitige Community-Bearbeitung, Volltextsuche und späteren Umzug robuster. |
| Website   | Inertia (wie Intranet)       | eigenes statisches Frontend über die API | Inertia: ein Code für Website und Redaktion. Statisch: schneller und Cache-freundlich, aber zweites Projekt. Suchmaschinen: Inertia mit SSR (`build:ssr`) bei Bedarf. |
| Admin-Login | Session + TOTP/Passkey     | OIDC (Microsoft Entra)         | OIDC passt zum Intranet, setzt aber eine Organisation voraus; für ein öffentliches Projekt reicht zuerst ein lokales Admin-Konto mit zweitem Faktor. |

## Offen

- Community-Anmeldung (ohne Konto mit Bestätigung, GitHub, Microsoft, Edulog).
- Lizenz für Software und Daten (vor dem Übernehmen von Code aus dem privaten Intranet).
- Versionen beim Start auf Support prüfen (Laravel, PHP, PostgreSQL, Node).
- Sicherung der Datenbank (Ziel, Intervall, Wiederherstellungstest).
