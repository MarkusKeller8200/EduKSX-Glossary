# Einrichten und Deployment: glossary.eduksx.ch

Anleitung für das Gerüst (#7). Aufbau wie im sek-intranet (`DEPLOY.md` dort), Konzept in
[docs/KONZEPT-HOSTING.md](docs/KONZEPT-HOSTING.md).

```text
git push ─► GitHub Actions: ci (composer ci:check) ─► Code per SSH auf den VPS ─► docker compose up --build
                                                                               │
Browser ─► Cloudflare ─► glossary.eduksx.ch ─► Traefik (HTTPS) ─► Container «glossary» ─► Volumen (SQLite, Schlüssel)
```

## 1. Lokal einrichten (Windows, wie beim Intranet)

Voraussetzungen: PHP 8.4 (mit `pdo_sqlite`, `zip`), Composer, Node 22. Docker ist lokal nicht nötig.

```powershell
git clone https://github.com/MarkusKeller8200/EduKSX-Glossary.git
cd EduKSX-Glossary
git checkout geruest        # bis der PR gemergt ist
composer setup              # Pakete, .env, Schlüssel, SQLite-Datei, Migrationen, npm install, Build
php artisan glossary:admin  # ersten Admin anlegen (fragt E-Mail, Name, Passwort ≥ 12 Zeichen)
php artisan glossary:developer E-MAIL --grund="Erstes Setup"  # optional: Rolle Developer (protokolliert)
composer dev                # http://localhost:8000, Administration unter /admin
```

Prüfen wie in der CI (muss grün sein, bevor gemergt wird):

```powershell
npm run check:fix           # Format und Lint (TypeScript) automatisch korrigieren
composer lint               # PHP-Format (Pint) automatisch korrigieren
composer ci:check           # Lint, Typen (TypeScript, PHPStan Level 7), Pint, Tests
```

Danach die erzeugten Lock-Dateien mitcommitten, damit die CI und der Server dieselben Versionen verwenden:

```powershell
git add composer.lock package-lock.json
git commit -m "Lock-Dateien für das Gerüst"
git push
```

Fehler beim ersten Lauf bitte als Text in den Chat bzw. ins Issue #7 kopieren – das Gerüst ist ohne
Paketinstallation geschrieben und darum noch nicht ausgeführt worden.

## 2. Server (Hostinger-VPS, einmalig)

Der VPS mit Traefik läuft bereits (Intranet). Für das Glossary braucht es nur einen eigenen Ordner und einen
eigenen Deploy-Schlüssel. hPanel → VPS → Browser-Terminal:

```bash
mkdir -p /docker/glossary
ssh-keygen -t ed25519 -N "" -C github-deploy-glossary -f /root/.ssh/glossary-deploy
cat /root/.ssh/glossary-deploy.pub >> /root/.ssh/authorized_keys
cat /root/.ssh/glossary-deploy                       # → Secret VPS_SSH_KEY
ssh-keyscan -t ed25519 76.13.156.138 2>/dev/null     # → Secret VPS_KNOWN_HOSTS
rm /root/.ssh/glossary-deploy                        # privater Schlüssel nur in GitHub
```

## 3. GitHub-Einstellungen

Repository → Settings → Secrets and variables → Actions:

| Typ      | Name              | Inhalt                                                |
| -------- | ----------------- | ----------------------------------------------------- |
| Secret   | `VPS_SSH_KEY`     | ganzer Inhalt von `glossary-deploy`                   |
| Secret   | `VPS_KNOWN_HOSTS` | Ausgabe von `ssh-keyscan`                             |
| Variable | `VPS_HOST`        | `76.13.156.138`                                       |
| Variable | `GLOSSARY_HOST`   | `glossary.eduksx.ch`                                  |
| Variable | `DEPLOY_ENABLED`  | `true` – erst setzen, wenn Schritte 2–4 erledigt sind |

Solange `DEPLOY_ENABLED` fehlt, prüft der Workflow «deploy» nur (Job ci) und überspringt das Ausrollen.

## 4. Cloudflare (sobald die Nameserver von eduksx.ch aktiv sind)

1. DNS → `A glossary 76.13.156.138`, zuerst **Nur DNS** (graue Wolke), damit Traefik das Zertifikat bezieht.
2. Nach dem ersten erfolgreichen Deployment auf **Proxied** (orange Wolke) umstellen.
3. SSL/TLS → **Full (strict)**.

## 5. Erstes Deployment und Admin

1. Variable `DEPLOY_ENABLED=true` setzen, Actions → **deploy** → Run workflow.
2. Admin auf dem Server anlegen (einmalig; ohne Admin lässt sich nichts ändern):

```bash
cd /docker/glossary
docker compose exec app php artisan glossary:admin
docker compose exec app php artisan glossary:developer E-MAIL --grund="Erstes Setup"   # Developer nur so
```

3. Prüfen: `https://glossary.eduksx.ch/up`, `https://glossary.eduksx.ch/versionen`, Anmelden unter `/anmelden`.

## 6. E-Mail-Versand (#40)

Der Versand ist an keinen Anbieter gebunden. Eingestellt wird er in `/docker/glossary/.env` auf dem Server;
Zugangsdaten nie ins Repository. Danach `docker compose up -d` und in der Administration unter **System** eine
Testmail senden (Recht «System», d. h. Developer). Ohne echten Versand (`MAIL_MAILER=log`) bleibt die
Selbstregistrierung ausgeschaltet.

```dotenv
# gemeinsam
MAIL_FROM_ADDRESS=noreply@eduksx.ch
MAIL_FROM_NAME="EduKSX-Glossary"

# Microsoft 365 / Exchange Online (Postfach mit SMTP AUTH)
MAIL_MAILER=smtp
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_USERNAME=noreply@eduksx.ch
MAIL_PASSWORD=…

# Google Workspace / Gmail (App-Passwort oder SMTP-Relay)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=…
MAIL_PASSWORD=…

# Infomaniak bzw. Hostinger (SMTP des Anbieters)
MAIL_MAILER=smtp
MAIL_HOST=mail.infomaniak.com      # bzw. smtp.hostinger.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=…
MAIL_PASSWORD=…
```

Versanddienste (Postmark, Amazon SES, Resend, Mailgun) gehen über die Treiber von Laravel; manche brauchen ein
Paket. Mails laufen über die Warteschlange: Der Dienst `worker` arbeitet sie ab und wiederholt fehlgeschlagene
Versuche (3 Versuche, Abstand 1 bzw. 5 Minuten). Fehlgeschlagene Aufträge zeigt die Systemseite; erneut senden mit
`docker compose exec worker php artisan queue:retry all`.

## Betrieb

| Aufgabe             | Befehl auf dem Server (`cd /docker/glossary`)                                          |
| ------------------- | -------------------------------------------------------------------------------------- |
| Status              | `docker compose ps`                                                                    |
| Logs                | `docker compose logs -f app`                                                           |
| Admin/Passwort      | `docker compose exec app php artisan glossary:admin`                                   |
| Warteschlange/Mails | `docker compose logs -f worker`, `docker compose exec worker php artisan queue:failed` |
| Developer           | `docker compose exec app php artisan glossary:developer E-MAIL` (`--entziehen`)        |
| Sicherungen ansehen | `docker compose exec app ls storage/app/backups`                                       |

- **Logs (#60):** Die Dienste `app`, `worker` und `scheduler` schreiben nach stderr; Docker rotiert je Dienst nach
  Grösse (höchstens 3 Dateien à 10 MB, `x-logging` in `compose.prod.yaml`). Damit die Frist von 14 Tagen sicher
  gilt, alte Container-Logs auf dem Host täglich entfernen, z. B. mit einem Cronjob
  `find /var/lib/docker/containers -name '*-json.log.*' -mtime +14 -delete` oder mit dem Log-Treiber `journald`
  und `MaxRetentionSec=14day` in `/etc/systemd/journald.conf`. Prüfen: `docker compose config | grep -A4 logging`.
- Vor jedem Start sichert der Container die SQLite-Datei nach `storage/app/backups` (die letzten 10). Eine Sicherung
  ausser Haus folgt (offener Punkt im Hosting-Konzept).
- Daten liegen im Volumen `glossary_glossary-storage`; es bleibt bei jedem Deployment erhalten.
