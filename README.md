# EduKSX-Glossary

Öffentliches Glossar mit Begriffen und Abkürzungen für Schweizer Volksschulen, Schulgemeinden und Gemeinden –
Website, REST-API, Script zur Einbindung und Browsererweiterung. **Öffentliche Beta:** https://glossary.eduksx.ch ·
[Produkt-Intro](https://markuskeller8200.github.io/EduKSX-Glossary/produkt.html) ·
reduzierte Lesefassung: https://markuskeller8200.github.io/EduKSX-Glossary/

## Funktionen (Stand 0.17.0)

- **Website:** Suche, Filter nach Kategorie und Herkunft, Balken (mit «Mehr Details»), Tabelle oder Kacheln, Version
  je Begriff, Detailseite mit Verlauf, Permalink `/g/{Code}`; Ansichten Mobil, Darstellungsoptimiert, Breit und Voll;
  installierbar als WebApp; Hilfe nach Rolle (`/hilfe`).
- **Konten:** Registrierung mit Bestätigung, Microsoft-Anmeldung, zweiter Faktor (Pflicht ab Moderator und für
  Organisations-Admins, Selbst-Zurücksetzen per Mail), E-Mail ändern mit Bestätigung, Verlauf, «Meine Daten».
- **Organisationen:** gründen, einladen, eigene Darstellung der Begriffe, Freigaben, Servicekonten mit Scope `read-org`.
- **Ab Benutzer:** Export (Excel, CSV, PDF, Markdown), Vorschläge und Korrekturen mit Rückfragen, persönliche
  Darstellung eines Begriffs.
- **Redaktion:** Begriffe mit Revisionen, Entwurf und Veröffentlichung (optional Vier-Augen-Prinzip), Wiederherstellen,
  Doppelte zusammenführen, Quellenprüfung, Moderation der Vorschläge, Excel-Import mit Vorschau, Listen mit Excel.
- **Verwaltung:** Benutzer und Organisationen (lesend), Rollen, Passwort-Link, Servicekonten mit Token, Einstellungen,
  System (Mailversand, Sicherung und Wiederherstellung, Updates).
- **API und Einbindung:** `/api/v1` (OpenAPI 3.1, Doku `/api/docs`, Swagger `/api/swagger`, Postman), Index für die
  Erkennung, Change-Feed; Script zur Einbindung und Browsererweiterung für Chrome, Edge und Firefox
  (Seite «Für Entwickler»).
- **Betrieb:** signiertes Docker-Image je Release, SQLite oder PostgreSQL, Update-Dienst mit Rücksprung,
  Sitemap und Suchmaschinen nur für die öffentliche Instanz.

## Stack

Wie das [sek-intranet](https://github.com/MarkusKeller8200/sek-intranet): Laravel 13 (PHP 8.4), Inertia 3, React 19,
TypeScript, shadcn/ui, Tailwind 4 – mit **SQLite oder PostgreSQL**. Gemeinsame Bausteine aus `eduksx/core`.
Auftritt nach CI MarKusSXCH. Konzepte: [docs/KONZEPT.md](docs/KONZEPT.md), Entscheide:
[docs/ENTSCHEIDE.md](docs/ENTSCHEIDE.md).

## Starten

Voraussetzungen: PHP 8.4, Composer, Node 22. Unter Windows genügt ein Doppelklick auf **`start.bat`**: holt den neuesten
Stand des aktuellen Branches, richtet ein und startet. `start.bat BRANCH` checkt zuerst einen anderen Branch aus
(z. B. `start.bat excel-import`, zurück mit `start.bat main`), `start.bat --ohne-pull` startet ohne GitHub. Von Hand:

```bash
composer setup              # Pakete, .env, Schlüssel, SQLite, Migrationen, Build
php artisan glossary:admin  # ersten Admin anlegen – ohne Anmeldung lassen sich keine Daten ändern
php artisan glossary:developer E-MAIL  # Rolle Developer (nur per Befehl, protokolliert)
composer dev                # http://localhost:8000
composer ci:check           # Prüfungen wie in der CI
```

Betrieb: **selbst hosten** mit [docs/SELBST-HOSTEN.md](docs/SELBST-HOSTEN.md) (Docker, Caddy oder Traefik, Updates).
Die öffentliche Instanz läuft auf test-01/prod-01 über das Betriebs-Repo `markussx-operation`; die Entwicklungsumgebung
(Hostinger) beschreibt [DEPLOY.md](DEPLOY.md), die Umgebungsvariablen [docs/UMGEBUNGSVARIABLEN.md](docs/UMGEBUNGSVARIABLEN.md).

## Aufbau

| Pfad                                  | Inhalt                                                                              |
| ------------------------------------- | ----------------------------------------------------------------------------------- |
| `app/`, `routes/`, `database/`        | Laravel: Website, Anmeldung, Administration, API `/api/v1`                          |
| `resources/js/`                       | React-Seiten und Bausteine (aus dem Intranet, [Liste](docs/UEBERNAHME-INTRANET.md)) |
| `openapi/glossary-v1.json`            | Beschreibung der API (OpenAPI 3.1, auch als YAML unter `/api/openapi.yaml`)         |
| `site/`                               | Lesefassung und Produkt-Intro über GitHub Pages                                     |
| `packages/highlighter/`, `extension/` | Kern der Erkennung (MIT), Script zur Einbindung, Browsererweiterung                 |
| `VERSION`, `CHANGELOG.md`             | Version und Versionshistorie (Seite «Versionen»)                                    |
| `docs/`                               | Konzepte, Entscheide, Ablauf auf GitHub                                             |

## Rechtliches und Drittlizenzen

- **Code:** [GNU Affero General Public License 3.0 oder später](LICENSE) (AGPL-3.0-or-later) ab Version 0.15.0, bis
  0.14.0 MIT. Wer eine geänderte Fassung im Netz betreibt, stellt deren Quellcode bereit (Link «Quellcode» in der
  Fusszeile, `GLOSSARY_SOURCE_URL`).
- **Script zur Einbindung und Browsererweiterung:** MIT ([packages/highlighter/LICENSE](packages/highlighter/LICENSE)),
  damit fremde Seiten sie ohne Auflagen einbinden können.
- **Daten:** CC BY 4.0 – frei nutzbar mit Quellenangabe «EduKSX-Glossary (glossary.eduksx.ch)».
- **Marke:** EduKSX und MarKusSXCH sind nicht Teil der Lizenzen.

Öffentliche Übersicht mit Erklärung: [/legals](https://glossary.eduksx.ch/legals); Herkunft und Prüfstand:
[docs/LEGAL.md](docs/LEGAL.md); vollständiger Lizenzbericht je Release als Anhang `license-report.md`.

## Mitmachen

Begriffe und Korrekturen schlagen Sie im Glossary selbst vor (ab Rolle Benutzer, DEC-024). Fehler der Software
melden oder Verbesserungen anregen über die
[Issue-Formulare](https://github.com/MarkusKeller8200/EduKSX-Glossary/issues/new/choose).
Wie Meldungen freigegeben, umgesetzt und veröffentlicht werden, steht in [CONTRIBUTING.md](CONTRIBUTING.md) und
[docs/GITHUB-ABLAUF.md](docs/GITHUB-ABLAUF.md).

## Unterstützen

Ein ⭐, Rückmeldungen über die Issues oder ein Kaffee auf [Ko-fi](https://ko-fi.com/markussxch) helfen weiter –
mehr in [docs/UNTERSTUETZEN.md](docs/UNTERSTUETZEN.md).

---

Eingerichtet mit [my-github-blueprint](https://github.com/MarkusKeller8200/my-github-blueprint)
(Konfiguration: `.github/blueprint.toml`).
