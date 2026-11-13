# Konzept: Programmierstack

**Abgenommen** am 07.11.2026 (#5; DEC-009, DEC-010, DEC-014). Ziel: am sek-intranet ausrichten, damit
Bausteine, Werkzeuge und Arbeitsweise gleich bleiben. Anpassung 07.11.2026: **kein PostgreSQL** – für die
Datenmenge reicht SQLite vollauf. **Abgelöst 12.11.2026 (DEC-028, DEC-033):** SQLite zu Beginn, PostgreSQL für den
zentralen Betrieb; Tests und Migrationen laufen in der CI gegen beide.

## Stack

| Schicht       | Technologie                                                                                                                               | wie im Intranet                            |
| ------------- | ----------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------ |
| Backend       | Laravel 13 (PHP 8.4)                                                                                                                      | ja                                         |
| Frontend      | React 19 + TypeScript, Inertia 3, shadcn/ui (Radix), Tailwind 4                                                                           | ja                                         |
| GUI-Bausteine | aus dem Intranet übernommen (`components/ui`, `data-grid`, `term`), Farbwerte nach CI MarKusSXCH ([KONZEPT-DESIGN.md](KONZEPT-DESIGN.md)) | ja, Aussehen anders                        |
| Build         | Vite (Vite Plus wie im Intranet)                                                                                                          | ja                                         |
| Datenbank     | **SQLite**, eine Datei im Docker-Volumen, Laravel-Migrationen; Tests mit SQLite im Speicher                                               | Intranet: JSON, SQLite vorgesehen          |
| Austausch     | JSON (Übernahme aus dem Intranet, Export, GitHub Pages), Excel (XLSX)                                                                     | ja                                         |
| API           | Laravel-Routen `/api/v1`, OpenAPI 3.1 (`openapi/glossary-v1.json`), Doku mit Scalar                                                       | ja                                         |
| Anmeldung     | **lokaler Admin ab dem ersten Deployment** (Session, Passwort, zweiter Faktor)                                                            | anders (Intranet: Demo-Login, später OIDC) |
| Tests         | PHPUnit, PHPStan Level 7 (Larastan), Pint, `vp check`                                                                                     | ja                                         |
| Prüfbefehl    | `composer ci:check` (inkl. `release.py check`)                                                                                            | ja                                         |
| Betrieb       | Docker-Image (PHP 8.4 + Apache), Compose, Traefik auf dem Hostinger-VPS                                                                   | ja                                         |
| Deployment    | GitHub Actions → SSH → `docker compose up --build`, Prüfadresse `/up`                                                                     | ja                                         |

### Warum so

- **Gleich wie das Intranet:** Komponenten, Glossar-Logik (`Glossary.php`, Import, Doppelte) und Werkzeuge lassen
  sich gezielt übernehmen; ein Stack für beide Projekte.
- **SQLite:** einige tausend Begriffe und wenige gleichzeitig Schreibende. SQLite bietet trotzdem Transaktionen,
  Fremdschlüssel und Migrationen – das brauchen Revisionen, Moderation und der Sync-Cursor. Kein zweiter Container,
  Sicherung als eine Datei, Umzug einfach. Reine JSON-Dateien wie im Intranet-Prototyp wären möglich, müssten aber
  später umgebaut werden; darum gleich SQLite, JSON bleibt Austauschformat.
- **Inertia für die Website, eigene Routen für die API:** Website und API rufen dieselbe Service-Schicht auf.

## Anmeldung von Beginn an (beschlossen, DEC-014)

Damit keine ungewollten Datenänderungen entstehen, gibt es **ab dem ersten Deployment** eine Anmeldung:

- Öffentlich ist nur **Lesen** (Website, `GET /api/v1/...`). Alles, was Daten ändert (Redaktion, Import,
  Vorschläge freigeben), verlangt eine Anmeldung mit Recht; es gibt keinen Demo-Login und kein gemeinsames Passwort.
- Erster Admin wird auf dem Server per Befehl angelegt: `php artisan glossary:admin` (fragt E-Mail und Passwort,
  speichert nur den Hash); Developer nur mit `php artisan glossary:developer` (#36). Community-Konten registrieren
  sich selbst mit Double-Opt-in oder melden sich mit Microsoft an (#41, #45).
- Passwort mindestens 12 Zeichen, Begrenzung der Anmeldeversuche, Sitzung nur über HTTPS (Cookie `secure`,
  `httponly`, `samesite=lax`).
- **Zweiter Faktor (TOTP)** für Admins, umgesetzt 07.11.2026 (#11): **ohne Laravel Fortify** (Zusatzpakete waren
  in der Cloud-Sitzung nicht installierbar), TOTP nach RFC 6238 in `App\Support\Totp` (geprüft mit den
  RFC-Testwerten), Schlüssel verschlüsselt, 8 Wiederherstellungscodes nur als Hash, jeder Code nur einmal. Pflicht:
  Ohne bestätigten zweiten Faktor führt jede Seite der Administration zur Einrichtung. Zurücksetzen auf dem Server
  mit `php artisan glossary:admin E-MAIL --reset-2fa`. QR-Code als SVG mit dem Paket `qrcode` (MIT, #70).
- Community-Anmeldung: umgesetzt mit 0.4.0 (siehe [KONZEPT-BENUTZER.md](KONZEPT-BENUTZER.md)).

## Aufbau im Repository

```text
app/            Laravel (Services unter app/Glossary, Controller, Models)
database/       Migrationen, Seeder (Startinhalt aus dem Intranet als JSON)
resources/js/   React-Seiten und Komponenten (Bausteine aus dem Intranet)
routes/         web.php (Website, Anmeldung, Redaktion), api.php (/api/v1)
openapi/        glossary-v1.json (auch als /api/openapi.yaml ausgeliefert)
docker/         compose.prod.yaml, entrypoint.sh
tests/          Feature-, API- und Sync-Tests
site/           reduzierte statische Fassung (GitHub Pages), Daten aus dem Export
```

## Varianten

| Punkt     | Vorschlag                | Alternative                              | Abwägung                                                                                                                            |
| --------- | ------------------------ | ---------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| Speicher  | SQLite                   | JSON-Dateien wie im Intranet-Prototyp    | JSON: Repositories 1:1 aus dem Intranet. SQLite: Transaktionen für Revisionen und Sync ohne späteren Umbau.                         |
| Website   | Inertia (wie Intranet)   | eigenes statisches Frontend über die API | Inertia: ein Code für Website und Redaktion. Statisch: zweites Projekt. Für Suchmaschinen bei Bedarf Inertia mit SSR (`build:ssr`). |
| 2. Faktor | eigenes TOTP (umgesetzt) | Laravel Fortify oder Passkeys            | Fortify ist offiziell und gepflegt; das Intranet hat es bewusst weggelassen (dort später OIDC).                                     |

## Offen (wird geklärt, wenn benötigt)

- Community-Anmeldung entschieden und umgesetzt (0.4.0): bestätigtes persönliches Konto mit Double-Opt-in bzw. Anmeldung mit Microsoft. Microsoft-App-Tenant für den Betrieb und Edulog-Aufnahme (#46) sind weiterhin offen.
- Lizenz für Software und Daten (vor dem Übernehmen von Code aus dem privaten Intranet).
- Versionen beim Start auf Support prüfen (Laravel, PHP, Node).
- Sicherung der SQLite-Datei (Ziel, Intervall, Wiederherstellungstest).

## Geschützter Ausbau der Benutzerverwaltung (DEC-017)

- Der bestehende lokale Admin und die implementierte TOTP-Absicherung bleiben der technische Ausgangspunkt; sie werden **nicht** als bloss geplant bezeichnet.
- Der detaillierte Rollen- und Identitätsausbau folgt [KONZEPT-BENUTZER.md](KONZEPT-BENUTZER.md). Bestehende `is_admin`-Konten werden ausschliesslich nach Admin migriert. Die Developer-Vergabe erfolgt gesichert und auditierbar durch Developer oder einen geschützten Serverprozess.
- MFA ist für alle Personen mit effektiv privilegierten Berechtigungen obligatorisch und kann nicht über die Rollenmatrix deaktiviert werden.
- Persönliche Konten und technische Servicekonten sind getrennt. Die öffentliche Lese-API bleibt ohne Anmeldung zugänglich.
- Community-Registrierung, Microsoft und später Edulog werden stufenweise integriert. Der Maildienst, die Microsoft-App-Registrierung und die Edulog-Vertrags- und Attributprüfung sind vor der jeweiligen Freischaltung zu klären.
