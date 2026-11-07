# Aus dem Intranet übernommen

Liste der Dateien, die aus `MarkusKeller8200/sek-intranet` übernommen wurden (docs/KONZEPT-DESIGN.md, DEC-015).
Stand des Intranets: Commit `main` vom 07.11.2026 (Version 0.36.0 + Unveröffentlicht). Verbesserungen fliessen als
Issue in beide Projekte.

Rechte: Code des Projektverantwortlichen; die shadcn/ui-Bausteine stehen unter MIT. Die Lizenz des Glossary ist
noch festzulegen (KONZEPT, Lizenzen).

## Unverändert

| Datei im Glossary                                                         | Herkunft im Intranet |
| ------------------------------------------------------------------------- | -------------------- |
| `resources/js/components/ui/{card,input,label,badge,separator,alert,checkbox}.tsx` | gleicher Pfad |
| `resources/js/hooks/use-appearance.tsx`, `resources/js/lib/utils.ts`      | gleicher Pfad        |
| `resources/js/components/{input-error,text-link,appearance-tabs}.tsx`     | gleicher Pfad        |
| `resources/js/types/vite-env.d.ts`                                        | gleicher Pfad        |
| `app/Http/Middleware/HandleAppearance.php`, `app/Http/Controllers/Controller.php` | gleicher Pfad |
| `artisan`, `public/index.php`, `public/.htaccess`                         | gleicher Pfad        |
| `config/{cache,filesystems,logging,mail,queue,services,session}.php`      | gleicher Pfad        |
| `.editorconfig`, `.gitattributes`, `.dockerignore`, `.npmrc`, `pint.json`, `components.json`, `tsconfig.json` (ohne `modules/`) | gleicher Pfad |
| `.github/scripts/report-failure.sh`                                       | gleicher Pfad        |

## Angepasst

| Datei                                         | Abweichung                                                                                |
| --------------------------------------------- | ----------------------------------------------------------------------------------------- |
| `resources/js/components/ui/button.tsx`       | Variante `link` in `text-link` statt `text-primary` (Orange als Schrift hat zu wenig Kontrast) |
| `resources/css/app.css`                       | Farbwerte nach CI MarKusSXCH, Schrift Inter, neue Werte `--brand-*`, `--link`; ohne Zeiterfassung |
| `app/Http/Middleware/SecurityHeaders.php`     | ohne Einbindungen von Fachanwendungen (`frame-src 'self'`)                                |
| `app/Http/Middleware/HandleInertiaRequests.php` | nur Konto, Version, Links                                                               |
| `app/Support/AppVersion.php`                  | Konfiguration `glossary.*`, `planned` aus `Release.php` übernommen, ohne Mitteilungen     |
| `app/Providers/AppServiceProvider.php`        | Recht `administer`, Rate-Limit der API, Passwort ≥ 12 Zeichen auch lokal                  |
| `bootstrap/app.php`, `config/{app,auth,database,inertia}.php` | ohne Intranet-Middleware; Konten in SQLite (Eloquent); SQLite-Datei im Volumen; SSR aus |
| `composer.json`, `package.json`, `vite.config.ts` | Name; ohne Module/Plugins; nur Schrift Inter; ohne dnd-kit, Scalar, TanStack (kommen mit den Bausteinen) |
| `Dockerfile`, `docker/entrypoint.sh`, `docker/compose.prod.yaml` | SQLite mit Sicherung vor dem Start, `migrate --force`, ohne `noindex`, Namen «glossary» |
| `.github/workflows/{ci,deploy}.yml`           | aus `tests.yml`, `deploy.yml`, `_deploy.yml`; Deployment nur mit `DEPLOY_ENABLED`         |
| `resources/views/app.blade.php`               | ohne Themes, PWA, Sprachen, Schriftwahl                                                   |
| `tests/TestCase.php`                          | SQLite im Speicher statt JSON-Demodaten                                                   |

## Neu im Glossary

`BrandWordmark` (`resources/js/components/brand-wordmark.tsx`, an die GUI-Prototypen des Intranets zurückmelden),
Layouts `public-layout`/`admin-layout`, Seiten `home`, `versions`, `auth/login`, `admin/dashboard`, Befehl
`glossary:admin`, `routes/api.php` mit `/api/v1`, `openapi/glossary-v1.yaml`.

## Als Nächstes (mit den jeweiligen Issues)

`data-grid` (Tabelle) mit `@tanstack/react-table`, `term.tsx`/`term-text.tsx`, Glossar-Seiten
(`pages/glossary/*`), `Glossary.php` (Regeln, Import, Doppelte), Seitenleiste.
