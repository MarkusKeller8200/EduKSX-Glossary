# Einrichten und Deployment: glossary.eduksx.ch

> Eigene Instanz für eine Schule oder Gemeinde: [docs/SELBST-HOSTEN.md](docs/SELBST-HOSTEN.md).

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

## 6. E-Mail-Versand (#40, #219)

**Einfachster Weg:** Administration → System → «Versand einrichten» (Recht `system`): Anbieter wählen, Zugang
eintragen, «Speichern und testen». Das Passwort wird verschlüsselt in der Datenbank gespeichert. Das geht nur, wenn
`MAIL_MAILER` **nicht** in der Umgebung bzw. `.env` des Servers steht – sonst gilt die Umgebung (Vorrang) und das
Formular ist gesperrt. Alternative für den Betrieb mit Secrets: wie folgt über `.env`.

Der Versand ist an keinen Anbieter gebunden. Eingestellt wird er in `/docker/glossary/.env` auf dem Server;
Zugangsdaten nie ins Repository. Danach `docker compose up -d` und in der Administration unter **System** eine
Testmail senden (Recht «System», d. h. Developer).

**Registrierung (#302):** Selbstregistrierung nur mit dem Schalter «Registrierung offen» (Administration ›
Einstellungen › Konten, Vorgabe aus, `GLOSSARY_REGISTRATION_OPEN`) und – bei E-Mail und Passwort – eingerichtetem
Versand. «Passwort vergessen» und Einladungen brauchen nur den Versand.

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

# Infomaniak bzw. Hostinger (SMTP des Anbieters) – im Betrieb Infomaniak mit noreply@eduksx.ch (#300);
# bei fremder DNS-Zone (Cloudflare) dort SPF include:spf.infomaniak.ch, DKIM aus dem Infomaniak Manager, DMARC
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

| Aufgabe              | Befehl auf dem Server (`cd /docker/glossary`)                                          |
| -------------------- | -------------------------------------------------------------------------------------- |
| Konfiguration prüfen | `docker compose exec app php artisan glossary:pruefen` (#196)                          |
| Status               | `docker compose ps`                                                                    |
| Logs                 | `docker compose logs -f app`                                                           |
| Admin/Passwort       | `docker compose exec app php artisan glossary:admin`                                   |
| Warteschlange/Mails  | `docker compose logs -f worker`, `docker compose exec worker php artisan queue:failed` |
| Developer            | `docker compose exec app php artisan glossary:developer E-MAIL` (`--entziehen`)        |
| Sicherungen ansehen  | `docker compose exec app ls storage/app/backups`                                       |

- **Logs (#60):** Die Dienste `app`, `worker` und `scheduler` schreiben nach stderr; Docker rotiert je Dienst nach
  Grösse (höchstens 3 Dateien à 10 MB, `x-logging` in `compose.prod.yaml`). Damit die Frist von 14 Tagen sicher
  gilt, alte Container-Logs auf dem Host täglich entfernen, z. B. mit einem Cronjob
  `find /var/lib/docker/containers -name '*-json.log.*' -mtime +14 -delete` oder mit dem Log-Treiber `journald`
  und `MaxRetentionSec=14day` in `/etc/systemd/journald.conf`. Prüfen: `docker compose config | grep -A4 logging`.
- Vor jedem Start sichert der Container die SQLite-Datei nach `storage/app/backups` (die letzten 10). Eine Sicherung
  ausser Haus folgt (offener Punkt im Hosting-Konzept).
- Daten liegen im Volumen `glossary_glossary-storage`; es bleibt bei jedem Deployment erhalten.

## Umgebungen und Freigabe (DEC-035, #304)

| Umgebung | Server               | Adresse                    | Was kommt an                | Wann                                       | Image-Tag    |
| -------- | -------------------- | -------------------------- | --------------------------- | ------------------------------------------ | ------------ |
| dev      | Hostinger            | `glossary-dev.markussx.ch` | jeder Merge auf `main`      | sofort (GitHub Actions, unten)             | `main-<sha>` |
| test     | `test-01` Infomaniak | `glossary-test.eduksx.ch`  | jede Beta und jedes Release | sofort (Komodo im VPN, MarKusSX-Operation) | `beta`       |
| prod     | `prod-01` Infomaniak | `glossary.eduksx.ch`       | jedes Release               | So 03:05, nur wenn test grün (Komodo)      | `stable`     |

- **Beta freigeben:** `python3 .github/blueprint/release.py set X.Y.Z-beta.N --commit` und pushen → Pre-Release auf
  GitHub, Image `:X.Y.Z-beta.N` und `:beta` → test. Nur nach ausdrücklicher Freigabe (wie ein Release).
- **Release freigeben:** `release.py set X.Y.Z --commit` → Release, Image `:X.Y.Z`, `:X.Y`, `:latest`, `:stable` und
  `:beta` → test sofort, prod im nächsten Wartungsfenster.
- test und prod: Stacks, Komodo und Ausrollen im Repo MarKusSX-Operation (`stacks/test/glossary`,
  `stacks/produktion/glossary`).
- Alle Umgebungsvariablen mit Standardwerten und Werten je Umgebung: [docs/UMGEBUNGSVARIABLEN.md](docs/UMGEBUNGSVARIABLEN.md).

## dev auf Hostinger mit Benutzer `deploy` (#209)

Hostinger ist der Entwicklungsserver (dev) unter `glossary-dev.markussx.ch` (DEC-035); test läuft auf `test-01`, prod auf
`prod-01` (docs/KONZEPT-DEPLOYMENT.md, Abschnitt «Umgebungen» unten). Ausgerollt wird nicht mehr als root, sondern als Benutzer `deploy` mit einem eigenen
Schlüssel je App. Gilt gleich für das Intranet auf demselben Server. Auf dev liegen **keine echten Konten** (nur Admin,
Registrierung aus).

### 1. Benutzer `deploy` anlegen (hPanel → VPS → Browser-Terminal, als root)

```bash
id deploy >/dev/null 2>&1 || useradd --create-home --shell /bin/bash deploy
passwd -l deploy                                   # kein Passwort, nur Schlüssel
usermod -aG docker deploy                          # darf Docker bedienen (faktisch Root, darum nur Testdaten)
install -d -m 700 -o deploy -g deploy /home/deploy/.ssh
```

### 2. Je App einen Schlüssel erzeugen und für `deploy` eintragen

```bash
for app in glossary intranet; do
  ssh-keygen -t ed25519 -N "" -C "github-deploy-$app-deploy" -f "/root/$app-deploy-neu"
  printf 'no-port-forwarding,no-agent-forwarding,no-X11-forwarding,no-pty %s\n' \
    "$(cat /root/$app-deploy-neu.pub)" >> /home/deploy/.ssh/authorized_keys
done
chown deploy:deploy /home/deploy/.ssh/authorized_keys
chmod 600 /home/deploy/.ssh/authorized_keys
```

### 3. Ordner an `deploy` übergeben

```bash
chown -R deploy:deploy /docker/glossary /docker/intranet
chmod 600 /docker/glossary/.env /docker/intranet/.env
su - deploy -c 'docker ps --format "{{.Names}}"'   # muss die Container auflisten
```

### 4. Adresse und Registrierung im Glossary

```bash
sed -i 's/^GLOSSARY_HOST=.*/GLOSSARY_HOST=glossary-dev.markussx.ch/' /docker/glossary/.env
# frühere Adresse leitet weiter (Zertifikat für beide über Traefik, #304)
grep -q '^GLOSSARY_REDIRECT_HOST=' /docker/glossary/.env || echo 'GLOSSARY_REDIRECT_HOST=glossary.markussx.ch' >> /docker/glossary/.env
grep '^MAIL_MAILER' /docker/glossary/.env           # gesetzt = Versand nur über die Umgebung (Administration gesperrt)
```

### 5. DNS (Cloudflare bzw. DNS von markussx.ch)

`A glossary-dev → 76.13.156.138` und (für die Weiterleitung) `A glossary → 76.13.156.138`, **nur DNS** (graue Wolke). `intranet.markussx.ch` zeigt auf dieselbe IP – das ist
gewollt, Traefik verteilt nach Namen.

### 6. GitHub

| Repo            | Wo                                                                      | Eintrag                                                                                                         |
| --------------- | ----------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| EduKSX-Glossary | Settings → Environments → **dev** → Secrets                             | Secret `VPS_SSH_KEY` = Inhalt von `/root/glossary-deploy-neu` (`cat …`)                                         |
| EduKSX-Glossary | Environment **dev** → Variables                                         | `VPS_HOST=76.13.156.138`, `VPS_USER=deploy`, `GLOSSARY_HOST=glossary-dev.markussx.ch`; Secret `VPS_KNOWN_HOSTS` |
| EduKSX-Glossary | Settings → Secrets and variables → Actions → **Variables** (Repository) | `DEPLOY_ENABLED=true` – muss auf Ebene Repository stehen, weil der Workflow sie vor dem Environment prüft       |
| sek-intranet    | Settings → Environments → **production**                                | Secret `VPS_SSH_KEY` = Inhalt von `/root/intranet-deploy-neu`                                                   |
| sek-intranet    | Environment **production** → Variables                                  | `VPS_USER=deploy`                                                                                               |

`VPS_KNOWN_HOSTS` und `VPS_HOST` bleiben unverändert.

### 7. Ausrollen und prüfen

Actions → **deploy** → Run workflow, in beiden Repos. Danach:

- `https://glossary-dev.markussx.ch/up` und `https://intranet.markussx.ch/up` antworten; `https://glossary.markussx.ch` leitet auf glossary-dev weiter
- im Protokoll des Workflows steht keine Fehlermeldung zu Rechten (`Permission denied`)

### 8. Aufräumen (erst wenn Schritt 7 in beiden Repos grün ist)

```bash
shred -u /root/glossary-deploy-neu /root/intranet-deploy-neu        # private Schlüssel nur noch in GitHub
grep -n 'github-deploy' /root/.ssh/authorized_keys                  # alte root-Schlüssel anzeigen
sed -i '/github-deploy-glossary$/d;/github-deploy-intranet$/d' /root/.ssh/authorized_keys
```

Schlägt etwas fehl: Variable `VPS_USER` in GitHub löschen – dann rollt der Workflow wieder als root aus, solange
die alten Schlüssel noch eingetragen sind (darum Schritt 8 zuletzt).

## Fertiges Image aus der Registry (#175)

Der Workflow **image** baut nach jeder geprüften Änderung auf `main` ein Image und legt es in die GitHub Container
Registry: `ghcr.io/markuskeller8200/eduksx-glossary:main` bzw. `:main-<commit>`. Ein Release-Commit (Datei `VERSION`
geändert) erzeugt zusätzlich `:X.Y.Z`, `:X.Y` und `:latest` (amd64). Jedes Image ist mit cosign signiert und
hat einen Herkunftsnachweis.

**Einmalig von Hand:** Nach dem ersten Lauf ist das Paket privat. Für selbst gehostete Instanzen und Komodo ohne
Anmeldung: GitHub → Profil → Packages → `eduksx-glossary` → Package settings → Change visibility → Public.

Starten ohne Bauen (z. B. Test auf einem leeren Server):

```bash
mkdir -p /opt/glossary && cd /opt/glossary
curl -fsSLO https://raw.githubusercontent.com/MarkusKeller8200/EduKSX-Glossary/main/docker/compose.yaml
printf 'GLOSSARY_HOST=glossary.example.ch\n' > .env
docker compose pull && docker compose up -d
curl -fsS http://127.0.0.1:8080/up
```

Signatur prüfen:

```bash
cosign verify ghcr.io/markuskeller8200/eduksx-glossary:latest \
  --certificate-identity-regexp 'https://github.com/MarkusKeller8200/EduKSX-Glossary/' \
  --certificate-oidc-issuer https://token.actions.githubusercontent.com
```

## dev mit fertigem Image statt Bauen auf dem Server (#195)

Seit #195 baut der Server nichts mehr. Ablauf: Merge auf `main` → Workflow **image** (CI, Image bauen, signieren) →
ruft **deploy** auf → der Server holt `ghcr.io/markuskeller8200/eduksx-glossary:main-<commit>`, startet es, prüft
`/up` und springt bei einem Fehler auf das vorherige Image zurück. Ein Release entsteht erst, wenn **image**
(inklusive Ausrollen auf dev) grün ist.

Der Schlüssel in GitHub darf auf dem Server nur noch `/usr/local/bin/glossary-deploy` ausführen
(`docker/server/glossary-deploy.sh`): compose-Dateien übernehmen, ein Image aus der eigenen Registry ausrollen, Zustand
und Protokoll anzeigen. Keine Shell, kein anderes Image.

### Einmalig auf dem Server (Browser-Terminal, als root) – vor dem ersten Ausrollen

```bash
curl -fsSL https://raw.githubusercontent.com/MarkusKeller8200/EduKSX-Glossary/main/docker/server/glossary-deploy.sh -o /tmp/glossary-deploy.sh
less /tmp/glossary-deploy.sh                                  # kurz ansehen
install -m 755 -o root -g root /tmp/glossary-deploy.sh /usr/local/bin/glossary-deploy
# Glossary-Schlüssel von «deploy» auf diesen Befehl beschränken
sed -i 's|^no-port-forwarding,no-agent-forwarding,no-X11-forwarding,no-pty \(ssh-ed25519 .* github-deploy-glossary-deploy\)$|command="/usr/local/bin/glossary-deploy",no-port-forwarding,no-agent-forwarding,no-X11-forwarding,no-pty \1|' /home/deploy/.ssh/authorized_keys
grep github-deploy-glossary /home/deploy/.ssh/authorized_keys   # beginnt jetzt mit command="/usr/local/bin/glossary-deploy"
su - deploy -c '/usr/local/bin/glossary-deploy status'
```

Ausserdem muss das Paket öffentlich sein (Abschnitt «Fertiges Image»), sonst kann der Server das Image nicht laden.

Beim ersten Ausrollen schreibt das Skript in `/docker/glossary/.env` die Zeilen `GLOSSARY_IMAGE` und
`COMPOSE_FILE=compose.yaml:compose.traefik.yaml`. Befehle von Hand funktionieren danach wie bisher, z. B.
`cd /docker/glossary && docker compose exec app php artisan glossary:pruefen`. Die alten Ordner `src/`, `src.old/` und
die Datei `docker-compose.yml` werden nicht mehr gebraucht und können gelöscht werden.

**Anderer Stand von Hand:** Actions → deploy → Run workflow → Tag, z. B. `main-1a2b3c4` (zurück auf einen älteren
Stand) oder `0.10.0`.

**Ausweg ohne Registry** (z. B. ghcr.io nicht erreichbar): als root im Ordner `/docker/glossary` den Code holen und mit
`docker/compose.prod.yaml` bauen wie vor #195.

### test und prod auf prod-01 (MarKusSX-Operation)

Gleiches Image, Zusatz `docker/compose.caddy.yaml` (Netzwerk `proxy`, Name je Instanz über `GLOSSARY_ALIAS`):
`COMPOSE_FILE=compose.yaml:compose.caddy.yaml`, `GLOSSARY_IMAGE=ghcr.io/markuskeller8200/eduksx-glossary:0.10` (nur
Korrekturen) und im Caddyfile `glossary.eduksx.ch { reverse_proxy glossary:80 }`. Komodo aktualisiert im
Wartungsfenster (Operation-Issue #17).
