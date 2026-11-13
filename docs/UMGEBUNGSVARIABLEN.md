# Umgebungsvariablen

Diese Seite listet alle Umgebungsvariablen, die bei EduKSX-Glossary von Bedeutung sind. Sie stehen in der `.env` neben der
compose-Datei, in Komodo (test, prod) oder in GitHub. Ablauf und Server: [DEPLOY.md](../DEPLOY.md), Abschnitt
«Umgebungen und Freigabe».

Zeichen in den Tabellen: ★ = Pflicht oder wichtig · 🔒 = Geheimnis.

- **Geheimnisse:** Sie gehören nie ins Repository. Am besten übergebt ihr sie als Datei mit `NAME_FILE=/run/secrets/name`
  statt `NAME=…` (z. B. `APP_KEY_FILE`, `MAIL_PASSWORD_FILE`) und bewahrt sie in 1Password auf.
- **Standardwerte im Image:** Das Docker-Image setzt `APP_ENV=production`, `APP_DEBUG=false`, `LOG_CHANNEL=stderr`,
  `SESSION_DRIVER=database`, `SESSION_SECURE_COOKIE=true`, `CACHE_STORE=database`, `QUEUE_CONNECTION=sync` und SQLite im
  Volumen. Diese Werte müsst ihr auf dem Server nicht eintragen. Die Werte in `.env.example` für lokal gelten im
  Container nicht.
- **Prüfen:** `php artisan glossary:pruefen` im Container.

## 1. Werte je Umgebung

| Variable                       | dev (Hostinger)                | test (`test-01`)              | prod (`prod-01`)                                   |
| ------------------------------ | ------------------------------ | ----------------------------- | -------------------------------------------------- |
| ★ `GLOSSARY_HOST`              | `glossary-dev.markussx.ch`     | `glossary-test.eduksx.ch`     | `glossary.eduksx.ch`                               |
| ★ `GLOSSARY_IMAGE`             | `…/eduksx-glossary:main-<sha>` | `…/eduksx-glossary:beta`      | `…/eduksx-glossary:stable`                         |
| `GLOSSARY_REDIRECT_HOST`       | `glossary.markussx.ch`         | –                             | –                                                  |
| `GLOSSARY_ALIAS` (Caddy)       | –                              | `glossary-test`               | `glossary`                                         |
| `GLOSSARY_UPDATE_CHECK`        | `true`                         | `false` (Komodo aktualisiert) | `false` (Komodo aktualisiert)                      |
| `GLOSSARY_REGISTRATION_OPEN`   | nach Bedarf                    | nach Bedarf                   | `false`, bis freigegeben                           |
| `GLOSSARY_PRIVACY_DRAFT`       | `true`                         | `true`                        | `false`, sobald die Datenschutzerklärung final ist |
| ★🔒 `APP_KEY` / `APP_KEY_FILE` | eigener Schlüssel              | eigener Schlüssel             | eigener Schlüssel, nie teilen                      |

`…` steht für `ghcr.io/markuskeller8200`. `APP_URL` wird aus `GLOSSARY_HOST` gebildet (`https://<host>`).

## 2. App

| Variable                                                  | Standard                                                    | Bedeutung                                                                         |
| --------------------------------------------------------- | ----------------------------------------------------------- | --------------------------------------------------------------------------------- |
| `APP_NAME`                                                | `EduKSX-Glossary`                                           | Name in Oberfläche und Mails                                                      |
| `APP_ENV`                                                 | `production` (Image)                                        | Umgebung; lokal `local`                                                           |
| ★🔒 `APP_KEY` / `APP_KEY_FILE`                            | leer = einmalig im Volumen erzeugt (`storage/app/.app_key`) | Verschlüsselungsschlüssel; je Umgebung verschieden, mit der Sicherung aufbewahren |
| `APP_DEBUG`                                               | `false` (Image)                                             | auf Servern immer `false`                                                         |
| `APP_URL`                                                 | aus `GLOSSARY_HOST`                                         | öffentliche Adresse                                                               |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` / `APP_FAKER_LOCALE` | `de` / `en` / `de_CH`                                       | Sprache                                                                           |
| `APP_TIMEZONE`                                            | `Europe/Zurich`                                             | Zeitzone                                                                          |
| `APP_MAINTENANCE_DRIVER`                                  | `file`                                                      | Wartungsmodus                                                                     |
| `BCRYPT_ROUNDS`                                           | `12`                                                        | Aufwand für Passwort-Hashes                                                       |
| `VITE_APP_NAME`                                           | `${APP_NAME}`                                               | Name im Frontend-Build                                                            |
| `INERTIA_SSR`                                             | aus                                                         | Server-Side-Rendering                                                             |

## 3. Datenbank

| Variable                                                 | Standard                               | Bedeutung                            |
| -------------------------------------------------------- | -------------------------------------- | ------------------------------------ |
| `DB_CONNECTION`                                          | `sqlite`                               | `sqlite` oder `pgsql`                |
| `DB_DATABASE`                                            | `storage/app/database/glossary.sqlite` | Pfad (SQLite) bzw. Name (PostgreSQL) |
| `DB_BUSY_TIMEOUT`                                        | `5000`                                 | SQLite: Wartezeit in ms              |
| `DB_JOURNAL_MODE`                                        | `wal`                                  | SQLite: Journal                      |
| `DB_FOREIGN_KEYS`                                        | `true`                                 | SQLite: Fremdschlüssel prüfen        |
| `DB_HOST` / `DB_PORT` / `DB_USERNAME` / 🔒 `DB_PASSWORD` | `127.0.0.1` / `5432` / – / –           | nur bei PostgreSQL                   |

## 4. Session, Cache, Warteschlange, Protokoll

| Variable                                               | Standard (Image)            | Bedeutung                                                   |
| ------------------------------------------------------ | --------------------------- | ----------------------------------------------------------- |
| `SESSION_DRIVER`                                       | `database`                  |                                                             |
| `SESSION_LIFETIME`                                     | `120`                       | Minuten                                                     |
| `SESSION_ENCRYPT` / `SESSION_PATH` / `SESSION_DOMAIN`  | `false` / `/` / `null`      |                                                             |
| `SESSION_SECURE_COOKIE`                                | `true`                      | Cookies nur über HTTPS                                      |
| `CACHE_STORE`                                          | `database`                  |                                                             |
| `QUEUE_CONNECTION`                                     | `sync`                      | `database` mit dem Dienst «worker» (Mails mit Wiederholung) |
| `BROADCAST_CONNECTION` / `FILESYSTEM_DISK`             | `log` / `local`             |                                                             |
| `LOG_CHANNEL`                                          | `stderr` (Image)            | im Container an Vector bzw. `docker logs`                   |
| `LOG_STACK` / `LOG_LEVEL` / `LOG_DEPRECATIONS_CHANNEL` | `single` / `debug` / `null` |                                                             |
| `LOG_DAILY_DAYS`                                       | `14`                        | Aufbewahrung beim Kanal `daily`                             |

## 5. Mail

Der Versand wird am besten in der Administration eingerichtet (System › «Versand einrichten»). Ist `MAIL_MAILER` in der
Umgebung gesetzt, hat das Vorrang und sperrt die Einrichtung dort. Ohne Einrichtung gehen keine Mails hinaus; sie
landen nur im Protokoll.

| Variable                                  | Beispiel (Infomaniak)                   | Bedeutung                                      |
| ----------------------------------------- | --------------------------------------- | ---------------------------------------------- |
| `MAIL_MAILER`                             | `smtp`                                  | nur setzen, wenn nicht über die Administration |
| `MAIL_HOST` / `MAIL_PORT` / `MAIL_SCHEME` | `mail.infomaniak.com` / `465` / `smtps` |                                                |
| `MAIL_USERNAME`                           | Adresse des Postfachs                   |                                                |
| 🔒 `MAIL_PASSWORD` / `MAIL_PASSWORD_FILE` | –                                       | Passwort des Postfachs                         |
| `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME`    | `noreply@eduksx.ch` / `${APP_NAME}`     | Absender                                       |

## 6. Anmeldung und Schutz

| Variable                     | Standard                                  | Bedeutung                                        |
| ---------------------------- | ----------------------------------------- | ------------------------------------------------ |
| `MICROSOFT_CLIENT_ID`        | leer = Knopf ausgeblendet                 | Anmeldung mit Microsoft (Entra-App)              |
| 🔒 `MICROSOFT_CLIENT_SECRET` | –                                         |                                                  |
| `MICROSOFT_TENANT`           | `common`                                  | `common` für mehrere Schulen oder eine Tenant-ID |
| `MICROSOFT_REDIRECT_URI`     | `${APP_URL}/anmelden/microsoft/rueckkehr` | je Umgebung in der Entra-App eintragen           |
| `TURNSTILE_SITE_KEY`         | leer = aus                                | Spam-Schutz bei der Registrierung (Cloudflare)   |
| 🔒 `TURNSTILE_SECRET_KEY`    | –                                         |                                                  |

## 7. Glossary

| Variable                                                             | Standard                           | Bedeutung                                                                                                                         |
| -------------------------------------------------------------------- | ---------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| `GLOSSARY_REGISTRATION_OPEN`                                         | `false`                            | Startwert für «Registrierung offen»; danach gilt die Einstellung in der Administration. Offen nur mit eingerichtetem Mail-Versand |
| `GLOSSARY_REDIRECT_HOST`                                             | leer                               | früherer Host; leitet mit 301 auf `APP_URL` weiter                                                                                |
| `GLOSSARY_TRASH_DAYS`                                                | `30`                               | Tage im Papierkorb                                                                                                                |
| `GLOSSARY_RETENTION_AUDIT_YEARS`                                     | `5`                                | Aufbewahrung des Änderungsverlaufs in Jahren                                                                                      |
| `GLOSSARY_RETENTION_INACTIVE_MONTHS`                                 | `24`                               | Monate, bis ein Konto als inaktiv gilt                                                                                            |
| `GLOSSARY_PRIVACY_DRAFT`                                             | `true`                             | Datenschutzerklärung als Entwurf kennzeichnen                                                                                     |
| `GLOSSARY_PRIVACY_VERSION`                                           | `1.0-entwurf`                      | Version der Datenschutzerklärung                                                                                                  |
| `GLOSSARY_API_STABILITY`                                             | `preview`                          | Hinweis zur Stabilität der API                                                                                                    |
| `GLOSSARY_UPDATE_CHECK`                                              | `true`                             | Hinweis auf neue Versionen in der Administration                                                                                  |
| `GLOSSARY_UPDATE_REPO`                                               | `MarkusKeller8200/EduKSX-Glossary` | Quelle für den Update-Hinweis                                                                                                     |
| 🔒 `GLOSSARY_UPDATE_KEY`                                             | leer                               | Schlüssel für den Update-Hinweis                                                                                                  |
| `GLOSSARY_GITHUB_REPO`                                               | `MarkusKeller8200/EduKSX-Glossary` | «Fehler melden» / «Idee vorschlagen»; leer = ausgeblendet                                                                         |
| `GLOSSARY_PAGES_URL`                                                 | GitHub Pages des Projekts          | Link zur Dokumentation                                                                                                            |
| `GLOSSARY_DUMP_REQUIRE_PASSWORD`                                     | `false`                            | vollständiger Dump nur mit Passwort                                                                                               |
| 🔒 `GLOSSARY_DUMP_PASSWORD`                                          | leer                               | Passwort für `php artisan glossary:dump` ohne Eingabe                                                                             |
| `GLOSSARY_BUILD` / `GLOSSARY_BUILD_DATE` / `GLOSSARY_BUILD_DESCRIBE` | vom Image                          | Versionsanzeige; nicht von Hand setzen                                                                                            |

## 8. Server und Docker Compose

| Variable                                   | Standard                   | Bedeutung                                           |
| ------------------------------------------ | -------------------------- | --------------------------------------------------- |
| ★ `GLOSSARY_HOST`                          | – (Pflicht)                | Adresse ohne `https://`                             |
| ★ `GLOSSARY_IMAGE`                         | `…/eduksx-glossary:latest` | Image und Tag                                       |
| `GLOSSARY_PORT`                            | `8080` auf `127.0.0.1`     | Port ohne Reverse-Proxy                             |
| `GLOSSARY_INSTANZ`                         | `glossary`                 | Name des Traefik-Routers bei mehreren Instanzen     |
| `GLOSSARY_ALIAS`                           | `glossary`                 | Netzwerkname für Caddy (`reverse_proxy <alias>:80`) |
| `GLOSSARY_ROLE`                            | –                          | `worker` oder `scheduler` für Zusatzdienste         |
| `COMPOSE_FILE`                             | –                          | compose-Dateien, die zusammen gelten                |
| `GLOSSARY_CA_DIR`                          | `./ca`                     | eigene CA-Zertifikate (Schulnetz)                   |
| `HTTP_PROXY` / `HTTPS_PROXY` / `NO_PROXY`  | –                          | Proxy (Schulnetz)                                   |
| `GLOSSARY_TARGET` / `GLOSSARY_PROXY_DATEI` | –                          | nur im Deploy-Skript von dev                        |

## 9. Sicherung und Updater

| Variable                                         | Standard        | Bedeutung                                                                             |
| ------------------------------------------------ | --------------- | ------------------------------------------------------------------------------------- |
| ★ `RESTIC_REPOSITORY`                            | – (Pflicht)     | Ziel, z. B. `s3:https://s3.pub1.infomaniak.cloud/<bucket>/glossary` oder `/sicherung` |
| ★🔒 `RESTIC_PASSWORD` / `RESTIC_PASSWORD_FILE`   | –               | Schlüssel der Sicherung; ohne ihn keine Wiederherstellung                             |
| 🔒 `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` | –               | nur bei S3                                                                            |
| `SICHERUNG_ORDNER`                               | `./sicherung`   | nur bei einem Ordner als Ziel                                                         |
| `SICHERUNG_UHRZEIT`                              | `02:30`         | tägliche Sicherung                                                                    |
| `SICHERUNG_TAGE`                                 | `30`            | Aufbewahrung                                                                          |
| `SICHERUNG_TEST`                                 | –               | `jetzt` = sofort sichern und Wiederherstellung testen                                 |
| `TZ`                                             | `Europe/Zurich` | Zeitzone des Sicherungsdienstes                                                       |
| `GLOSSARY_DIR`                                   | – (Pflicht)     | Ordner der Installation für den Updater, z. B. `/opt/glossary`                        |

## 10. GitHub

Repository → Settings → Secrets and variables → Actions. Nur dev wird aus GitHub ausgerollt. test und prod holen ihre
Images selbst über Komodo (Repo MarKusSX-Operation), darum gibt es für sie keine GitHub-Environments.

| Typ      | Name              | Inhalt                                                                |
| -------- | ----------------- | --------------------------------------------------------------------- |
| Variable | `DEPLOY_ENABLED`  | `true` (Repository); fehlt sie, wird nur geprüft und nicht ausgerollt |
| Variable | `VPS_HOST`        | `76.13.156.138`                                                       |
| Variable | `VPS_USER`        | `deploy` (Vorgabe)                                                    |
| Variable | `GLOSSARY_HOST`   | `glossary-dev.markussx.ch` (Vorgabe)                                  |
| Secret   | `VPS_SSH_KEY`     | privater Deploy-Schlüssel                                             |
| Secret   | `VPS_KNOWN_HOSTS` | Ausgabe von `ssh-keyscan`                                             |
| –        | `GITHUB_TOKEN`    | automatisch, nichts eintragen                                         |

Ausser `DEPLOY_ENABLED` dürfen die Werte im Repository oder im Environment `dev` stehen; das Environment hat Vorrang.

## 11. test und prod (Komodo)

In den Stacks `stacks/test/glossary` und `stacks/produktion/glossary` im Repo MarKusSX-Operation genügen wenige
Werte: `GLOSSARY_HOST`, `GLOSSARY_IMAGE`, `GLOSSARY_ALIAS`, `APP_KEY_FILE` und `GLOSSARY_UPDATE_CHECK=false`,
optional `MAIL_*` sowie `MICROSOFT_*` und `TURNSTILE_*`. Alles andere setzt das Image. Schlüssel und Secrets trägt
Projektverantwortliche:r selbst ein.
