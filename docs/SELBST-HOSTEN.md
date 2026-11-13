# EduKSX-Glossary selbst hosten

Für Schulen und Gemeinden, die das Glossary auf einem eigenen Server betreiben (#179, DEC-027, DEC-028). Die meisten
Schulen sind im zentralen Glossary als Organisation gut bedient; eine eigene Instanz gibt es auf ausdrücklichen
Wunsch.

## Voraussetzungen

- Linux-Server mit Docker Engine und Compose-Plugin. **Zugesichert:** Ubuntu 24.04/26.04 und Debian 12/13 (amd64).
- 1 vCPU, 1–2 GB RAM, 10 GB Speicher.
- Eine Adresse (z. B. `glossary.schule.ch`), deren DNS-Eintrag auf den Server zeigt.
- Ausgehend: `ghcr.io` (Image), `api.github.com` und `raw.githubusercontent.com` (Installation, Hinweis auf neue
  Versionen), ein SMTP-Dienst für Mails.
- Eingehend nur HTTPS (443) und für das Zertifikat Port 80 – keine weiteren offenen Ports.

## Installieren

```bash
curl -fsSLO https://raw.githubusercontent.com/MarkusKeller8200/EduKSX-Glossary/main/docker/install.sh
less install.sh        # ansehen
sudo sh install.sh
```

Das Skript fragt Ordner (Vorgabe `/opt/glossary`), Adresse, Proxy, Update-Dienst und Sicherung, schreibt `.env`,
startet die Dienste, prüft die Konfiguration (`glossary:pruefen`) und legt den ersten Admin an. Danach:
`https://<adresse>/anmelden` → zweiten Faktor einrichten → Administration → System:

1. **Versand einrichten** (Mail) – «Speichern und testen».
2. **Konfiguration** prüfen – keine Fehler.
3. **Updates** – installierte Version, Update-Dienst «aktiv».

Ein zweiter Lauf des Skripts ändert eine bestehende Installation nicht ungefragt (nur Dateien aktualisieren).

## Bausteine

Alles läuft mit demselben Image; was dazukommt, steht in `COMPOSE_FILE` in `.env`:

| Datei                    | Wofür                                                                    |
| ------------------------ | ------------------------------------------------------------------------ |
| `compose.yaml`           | App, Warteschlange, geplante Aufgaben; App nur auf `127.0.0.1:8080`      |
| `compose.proxy.yaml`     | Caddy mit automatischem HTTPS (Standard)                                 |
| `compose.traefik.yaml`   | hinter einem vorhandenen Traefik                                         |
| `compose.schulnetz.yaml` | ausgehender Proxy, eigene Zertifizierungsstelle (Ordner `ca/`)           |
| `compose.updater.yaml`   | Update-Dienst (Updates aus der Administration, automatische Korrekturen) |
| `compose.backup.yaml`    | Sicherung ausser Haus mit restic (verschlüsselt, S3 oder Ordner)         |

Ohne Proxy-Baustein lauscht die App nur auf `127.0.0.1:8080` – dann leitet ein eigener nginx/Apache dorthin weiter.

## Im Schulnetz

- **Proxy:** `HTTPS_PROXY`/`HTTP_PROXY` in `.env`, Baustein `compose.schulnetz.yaml`.
- **Eigene Zertifizierungsstelle:** Zertifikate (PEM, Endung `.crt`) in den Ordner `ca/` legen, dann
  `docker compose up -d`. Sie gelten zusätzlich zu den üblichen, z. B. für die Anmeldung über den OIDC-Dienst der Schule.
- **Ohne Internet:** Offline-Paket nur auf Wunsch (Entscheid 12.11.2026). Bis dahin: Hinweis auf neue Versionen
  ausschalten (`GLOSSARY_UPDATE_CHECK=false`).

## Updates

- **Hinweis:** Die Administration zeigt neue Versionen (höchstens täglich geprüft, abschaltbar unter Einstellungen →
  Updates).
- **Aus der Administration:** System → Updates → «Jetzt aktualisieren». Nur mit dem Recht `system` (Developer, wird mit
  `php artisan glossary:developer` vergeben) und zweitem Faktor; optional zusätzlich ein Schlüssel
  (`GLOSSARY_UPDATE_KEY` in `.env`).
- **Automatisch:** Einstellungen → Updates: «nur Korrekturen» (Vorgabe), «Korrekturen und neue Funktionen» oder «aus»;
  nie automatisch über eine Hauptversion; im eingestellten Zeitfenster (Vorgabe 03:00).
- **Ablauf:** Image holen → Neustart (die Datenbank wird vorher gesichert) → Prüfung → bei Fehler automatisch zurück auf
  den vorherigen Stand samt Datenbank. Protokoll unter System → Updates.
- **Von Hand:** in `.env` `GLOSSARY_IMAGE=ghcr.io/markuskeller8200/eduksx-glossary:X.Y.Z` setzen, dann
  `docker compose pull && docker compose up -d`.
- **Zurück von Hand:** frühere Version in `GLOSSARY_IMAGE` eintragen und starten. Hat die neue Version die Datenbank
  geändert, die Sicherung `storage/app/backups/vor-update-…sqlite` von unmittelbar davor einspielen (Abschnitt
  Wiederherstellen).

## Sicherung und Wiederherstellung

- Vor jedem Start sichert der Container die Datenbank (`storage/app/backups/vor-update-*.sqlite`, die letzten 10).
- Täglich 02:15 eine vollständige Kopie (`taeglich.sqlite`); mit `compose.backup.yaml` sichert restic sie um 02:30
  verschlüsselt ausser Haus, bewahrt sie 30 Tage auf und testet am 1. des Monats die Wiederherstellung. Den Stand zeigt
  System → Konfiguration.
- Sofort sichern und testen: `docker compose run --rm -e SICHERUNG_TEST=jetzt backup jetzt`.
- **Vollständiger Dump** (#268): Administration › System › «Vollständige Sicherung (Dump)» oder
  `docker compose exec app php artisan glossary:dump --passwort`. ZIP mit exakter Kopie der Datenbank
  (`datenbank/glossary.sqlite`), allen Tabellen als JSON Lines (`tabellen/*.jsonl`, für einen Umzug auf eine andere
  Datenbank) und `manifest.json` (Zeilen je Tabelle, Prüfsummen). Mit Passwort AES-256-verschlüsselt;
  `GLOSSARY_DUMP_REQUIRE_PASSWORD=true` erlaubt nur noch verschlüsselte Dumps. Wiederherstellen wie unten mit
  `datenbank/glossary.sqlite`.
- **Wiederherstellen:**

```bash
cd /opt/glossary
docker compose run --rm --entrypoint restic backup restore latest --tag glossary --target /data/app/backups/wiederherstellung
docker compose stop app worker scheduler
# …/wiederherstellung/data/app/backups/taeglich.sqlite als storage/app/database/glossary.sqlite einsetzen
# (ohne die Dateien -wal und -shm; Vorgehen wie in DEPLOY.md beim Übertragen der Datenbank)
docker compose up -d
docker compose exec app php artisan glossary:loeschfristen   # bereits ausgeführte Löschungen erneut anwenden
```

## Prüfen und Betrieb

| Aufgabe              | Befehl (im Ordner der Installation)                                         |
| -------------------- | --------------------------------------------------------------------------- |
| Konfiguration prüfen | `docker compose exec app php artisan glossary:pruefen`                      |
| Zustand              | `docker compose ps`                                                         |
| Protokoll            | `docker compose logs -f app`                                                |
| Admin, Passwort      | `docker compose exec app php artisan glossary:admin`                        |
| Developer (Updates)  | `docker compose exec app php artisan glossary:developer E-MAIL --grund="…"` |
