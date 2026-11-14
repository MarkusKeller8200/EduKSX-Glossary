# EduKSX-Glossary

Öffentliches Glossar mit Begriffen und Abkürzungen für Schweizer Volksschulen, Schulgemeinden und Gemeinden –
Website und REST-API. Ziel: **https://glossary.eduksx.ch** (in Aufbau); reduzierte Lesefassung:
**https://markuskeller8200.github.io/EduKSX-Glossary/**

## Funktionen (Stand 0.8.0)

- **Website:** Suche, Filter nach Kategorie und Herkunft, Tabelle oder Kacheln, Detailseite je Begriff mit Verlauf;
  Ansichten Mobil, Darstellungsoptimiert, Breit und Voll; Hilfe zu allen Feldern (`/hilfe`).
- **Konten:** Registrierung mit Bestätigung, Microsoft-Anmeldung, zweiter Faktor (Pflicht ab Organisations-Admin),
  Rollen Gast, Benutzer, Organisations-Admin, Moderator, Admin, Developer.
- **Ab Benutzer:** Export (Excel, CSV, PDF, Markdown), Vorschläge und Korrekturen mit Rückfragen, persönliche
  Darstellung eines Begriffs.
- **Redaktion:** Begriffe mit Revisionen, Entwurf und Veröffentlichung (optional Vier-Augen-Prinzip), Wiederherstellen,
  Doppelte zusammenführen, Quellenprüfung, Altwerte, Moderation der Vorschläge, Excel-Import mit Vorschau, Listen
  (Kategorien, Herkunft) mit Excel.
- **Verwaltung:** Benutzer und Rollen, Passwort-Link, Servicekonten mit Token, allgemeine Einstellungen, System.
- **API:** `/api/v1` (OpenAPI 3.1, Doku unter `/api/docs`), Change-Feed für den Abgleich, Vorschläge per Token;
  Lesefassung über GitHub Pages.

## Stack

Wie das [sek-intranet](https://github.com/MarkusKeller8200/sek-intranet): Laravel 13 (PHP 8.4), Inertia 3, React 19,
TypeScript, shadcn/ui, Tailwind 4 – mit **SQLite** als Datenbank. Auftritt nach CI MarKusSXCH, GUI-Bausteine aus
dem Intranet übernommen. Konzepte: [docs/KONZEPT.md](docs/KONZEPT.md), Entscheide:
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

Online stellen auf `https://glossary.eduksx.ch` (Hostinger-VPS, Docker, Traefik, Cloudflare): [DEPLOY.md](DEPLOY.md).

## Aufbau

| Pfad                           | Inhalt                                                                              |
| ------------------------------ | ----------------------------------------------------------------------------------- |
| `app/`, `routes/`, `database/` | Laravel: Website, Anmeldung, Administration, API `/api/v1`                          |
| `resources/js/`                | React-Seiten und Bausteine (aus dem Intranet, [Liste](docs/UEBERNAHME-INTRANET.md)) |
| `openapi/glossary-v1.json`     | Beschreibung der API (OpenAPI 3.1, auch als YAML unter `/api/openapi.yaml`)         |
| `site/`                        | reduzierte Lesefassung über GitHub Pages                                            |
| `VERSION`, `CHANGELOG.md`      | Version und Versionshistorie (Seite «Versionen»)                                    |
| `docs/`                        | Konzepte, Entscheide, Ablauf auf GitHub                                             |

## Rechtliches und Drittlizenzen

Die Herkunft verwendeter Frameworks, kopierter Komponenten und weiterer Bibliotheken ist in
[docs/LEGAL.md](docs/LEGAL.md) dokumentiert. Die öffentliche Übersicht ist unter `/legals` vorgesehen.
`sek-intranet` und `my-github-blueprint` sind eigene Repositories; geprüft werden die darin
enthaltenen Fremdanteile und deren Lizenzpflichten. Der eigene Code steht ab Version 0.15.0 unter der [GNU Affero General Public License 3.0 oder später](LICENSE) (AGPL-3.0-or-later, DEC-040; Versionen bis 0.14.0 unter MIT), die Daten unter CC BY 4.0. Wer eine geänderte Fassung im Netz betreibt, stellt deren Quellcode bereit (Link «Quellcode» in der Fusszeile, `GLOSSARY_SOURCE_URL`). Ausnahme: `packages/highlighter` (Einbindungs-Script `public/embed/` und Browser-Erweiterung) bleibt unter MIT ([eigene LICENSE](packages/highlighter/LICENSE)), damit fremde Seiten es ohne Auflagen einbinden können; Drittkomponenten behalten ihre Lizenzen. Diese Dokumentation ersetzt keine vollständige Lizenzinventur.

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
