# Konzept: Deployment und Installationsarten (DEC-028)

Stand: beschlossen 12.11.2026 (Entwurf 08.11.2026, ergänzt 11.11.2026 (Umgebungen, Operation, Datenschutz), offene Fragen entschieden 12.11.2026). Baut auf [KONZEPT-UPDATES.md](KONZEPT-UPDATES.md) (DEC-027) und
[KONZEPT-HOSTING.md](KONZEPT-HOSTING.md) (DEC-013) auf; die Plattform-Schnittstellen stehen im Konzept
EduKSX-Plattform (Repo EduKSX-Architektur). Umsetzung nach Freigabe der Issues.

## Ziel

Ein Glossary, ein Image, mehrere Betriebsarten: unsere zentrale Instanz (heute Hostinger, später Infomaniak), eine
Schule auf einem beliebigen VPS, eine Installation im eigenen Netz der Schule und – wenn nötig – eine verteilte
Installation über mehrere Server. Das Glossary läuft immer **eigenständig**; die Anbindung an EduKSX-Ident und
eduksx/core ist eine Einstellung, keine Voraussetzung.

## Ausgangslage

- `deploy.yml` überträgt bei jedem Merge den Quellcode per SSH (`git archive`) und baut das Image auf dem Server
  (`docker compose up --build`). Dauer und Ergebnis hängen vom Server ab; ein Rücksprung heisst «neu bauen».
- `compose.prod.yaml` setzt den Traefik des Intranets voraus (Labels, Zertifikat `letsencrypt`) und baut aus `./src`.
- Daten (SQLite), Sicherungen und Schlüssel liegen im Volume `storage/`; Sicherungen bleiben auf demselben Server.
- Konfiguration teils in `.env` auf dem Server, teils fest in der compose-Datei.

## Grundsätze

| Nr. | Vorschlag                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| --- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| D1  | **Einmal bauen, überall gleich ausrollen.** Die CI baut das Image (amd64; arm64 nur bei Bedarf, Entscheid 12.11.2026), prüft es (Tests, Schwachstellen-Scan, Stückliste SBOM) und legt es mit festem Digest in die Registry. dev, test und prod erhalten **dasselbe** Image; ausgerollt wird nur noch ein Digest, nie Quellcode.                                                                                                                                            |
| D2  | **Drei Umgebungen (Entscheid 11.11.2026, angepasst 13.11.2026, DEC-035).** Jeder Merge auf `main` → Image `main-<sha>` → **dev** auf Hostinger (`glossary-dev.markussx.ch`). Eine **Beta** (`X.Y.Z-beta.N`) → Tag `beta` → **test** auf `test-01` sofort. Ein **Release** → Tags `X.Y.Z`, `X.Y`, `latest`, `stable` und `beta` → **prod** auf `prod-01` im Wartungsfenster (nur wenn test grün), dazu test und selbst gehostete Instanzen. Alle laufen mit demselben Image. |
| D3  | **Konfiguration nur über die Umgebung.** Alles, was sich je Installation unterscheidet, steht in `.env` bzw. Umgebungsvariablen, dokumentiert in `.env.example`. Geheimnisse auch als Datei (`*_FILE`, für Docker/Kubernetes Secrets). Ein Befehl `glossary:pruefen` zeigt fehlende oder widersprüchliche Einstellungen.                                                                                                                                                    |
| D4  | **Bausteine statt Varianten.** Eine Basis-compose-Datei plus Zusätze: eigener Proxy mit Zertifikat, vorhandener Proxy, Update-Dienst, Traefik (dev) bzw. Caddy (test, prod). Kein Code je Kunde (DEC-027 U13).                                                                                                                                                                                                                                                              |
| D5  | **Prüfen und zurück.** Nach jedem Ausrollen Prüfung `/up` (läuft) und `/health` (Datenbank, Warteschlange, Speicher, Version stimmt). Schlägt sie fehl, startet das vorherige Image wieder; die Sicherung von unmittelbar davor steht bereit (DEC-027 U6, U7).                                                                                                                                                                                                              |
| D6  | **Sicherung ausser Haus (Entscheid 11.11.2026).** Täglich und vor jedem Update verschlüsselt (restic bzw. Backrest, Schlüssel nur in 1Password); Ziel **Infomaniak Swiss Backup** (Vorgabe in MarKusSX-Operation) oder ein **S3-kompatibler Speicher** (z. B. Infomaniak Object Storage, Schul-NAS). Nur Ziele in der Schweiz bzw. mit Vertrag zur Auftragsbearbeitung; Wiederherstellungstest automatisch.                                                                 |
| D7  | **Nur ausgehend.** Keine Installation braucht offene Ports ausser HTTPS für die Benutzer. Abgleich, Update-Hinweis, Heartbeat und Anbindung an Ident laufen ausgehend und sind einzeln abschaltbar.                                                                                                                                                                                                                                                                         |
| D8  | **Gleicher Weg für uns und für Schulen.** Unsere Instanzen nutzen dieselben Bausteine und denselben Update-Dienst wie selbst gehostete. Was bei uns nicht läuft, wird nicht verteilt (DEC-027 U10).                                                                                                                                                                                                                                                                         |

## Optimierter Ablauf

```text
PR ──► ci (Prüfungen) ──► Merge main ──► Image bauen + Scan + SBOM + Signatur ──► ghcr.io/…:main-<sha>
                                                                                   │
                                     dev (Hostinger): pull Digest ─► Sicherung ─► Start ─► /health ─► ok / zurück
                                                                                   │
Beta (VERSION X.Y.Z-beta.N, Pre-Release) ──► :X.Y.Z-beta.N, :beta ──► Komodo: test-01 sofort
Release (VERSION X.Y.Z, Tag vX.Y.Z) ──► :X.Y.Z, :X.Y, :latest, :stable, :beta ──► Komodo: test-01 sofort
                                                                               ─► prod-01 (So 03:05, nur wenn test grün)
                                                                               └► selbst gehostete Instanzen (Update-Dienst,
                                                                                  Ringe und Wartungsfenster nach DEC-027)
```

- **Ausrollen ohne Quellcode:** Auf dem Server liegen nur `compose.yaml`, die Zusätze und `.env`. Die compose-Dateien
  gehören zum Release (Anhang), damit Version und Aufbau zusammenpassen.
- **Schneller:** Kein Bauen auf dem Server, Image vorab geladen; ein Update dauert Sekunden statt Minuten.
  Blau/grün (DEC-027 U17) reduziert den Unterbruch weiter.
- **Nachvollziehbar:** Die Seite «Versionen» und `/health` zeigen Version, Commit und Digest; jedes Ausrollen steht
  im Protokoll (DEC-027 U9).
- **Sicherer:** Auf `prod-01` gibt es kein Ausrollen aus GitHub per SSH (MarKusSX-Operation E29, SSH nur über VPN);
  Komodo holt das Image. Nur der Entwicklungsserver auf Hostinger wird weiter aus GitHub per SSH beliefert (unten).

## Umgebungen und Betrieb mit MarKusSX-Operation (Entscheid 11.11.2026)

Unser Betrieb folgt dem Plattform-Konzept im Repo MarKusSX-Operation (Komodo, Caddy, Wartungsfenster, Backrest).
Das Glossary liefert dafür ein fertiges Image und eine compose-Datei je Proxy.

| Umgebung | Server                                                                | Adresse                    | Ausrollen                                                                                                                                   | Proxy                    | Datenbank                         | Daten                            |
| -------- | --------------------------------------------------------------------- | -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------ | --------------------------------- | -------------------------------- |
| **dev**  | Hostinger-VPS (privater Entwicklungsserver, nicht Teil von Operation) | `glossary-dev.markussx.ch` | jeder Merge: GitHub Actions per SSH, `docker compose pull` und Start (kein Bauen mehr auf dem Server; Bauen aus dem Code bleibt als Ausweg) | Traefik                  | SQLite                            | nur Testdaten, Registrierung aus |
| **test** | `test-01` (Infomaniak, eigener VPS)                                   | `glossary-test.eduksx.ch`  | Komodo holt jede Beta und jedes Release sofort (Image-Tag `beta`)                                                                           | Caddy (Netzwerk `proxy`) | wie prod                          | erfundene Daten (Operation E18)  |
| **prod** | `prod-01` (Infomaniak)                                                | `glossary.eduksx.ch`       | Komodo, Sonntag 03:05 (Image-Tag `stable`), nur wenn test grün                                                                              | Caddy                    | SQLite zu Beginn, bald PostgreSQL | echte Daten                      |

- **Infomaniak-Produkt:** VPS Lite und VPS Cloud sind beide zulässig (Rechenzentrum Schweiz). VPS Lite genügt bei
  wenigen Benutzern (Operation E35); Wechsel auf VPS Cloud oder Public Cloud, wenn Benutzerzahl oder Last steigen.
  Ob VPS Lite Snapshots bietet (Backup-Tabelle in Operation: wöchentlicher Hoster-Snapshot): ZU PRÜFEN.
- **Datenbank:** SQLite ist für dev und für den Start von prod zulässig (wenige Benutzer). Die Umstellung auf
  PostgreSQL (Operation: beschlossen, eigener Stack, später verwaltete Datenbank) folgt bald (#191); bis dahin bleibt
  die Anwendung mit beiden Datenbanken lauffähig, die CI prüft beide.
- **Adressen (DEC-035, 13.11.2026):** `glossary.eduksx.ch` zeigt auf `prod-01`, `glossary-test.eduksx.ch` auf `test-01`,
  `glossary-dev.markussx.ch` auf Hostinger; `glossary.markussx.ch` leitet auf die Dev-Adresse weiter
  (`GLOSSARY_REDIRECT_HOST`). Früher: Damit ändern sich DEC-013 (Hostinger, `glossary.eduksx.ch`) und Operation-Issue #17
  (`glossary.markussx.ch` auf `prod-01`); beide werden nachgeführt.
- **Deploy-Schlüssel auf dev – Nachteile:** Wer das Secret in GitHub oder einen Workflow übernimmt, kann auf dem
  Hostinger-Server Befehle ausführen; heute als `root`, also auch für das Intranet daneben. GitHub (USA) hält damit
  einen Schlüssel zu einem Server. Gegenmassnahmen: eigener Benutzer `deploy` ohne Passwort und ohne sudo, erzwungener
  Befehl in `authorized_keys` (nur `pull`/Start für `glossary`), eigener Schlüssel je App, Environment mit Freigabe,
  keine Geheimnisse von test/prod auf dev. Mitglied der Gruppe `docker` bleibt faktisch Root – darum bleibt dev
  strikt ohne echte Daten.
- **Sicherung:** test und prod sichert Backrest von Operation (Volumen, Datenbank-Dump vor dem Backup); die Sicherung
  vor jedem Update im Container bleibt. Selbst gehostete Instanzen nutzen D6.

## Betriebsarten

| Art                | Für wen                                       | Aufbau                                                                                                             | Daten                             | Updates                          |
| ------------------ | --------------------------------------------- | ------------------------------------------------------------------------------------------------------------------ | --------------------------------- | -------------------------------- |
| **A Zentral**      | unsere Instanz, Schulen als Organisation      | Hostinger-VPS heute, Infomaniak als Ziel (unten); hinter Cloudflare                                                | SQLite im Volume, Sicherung in S3 | CI bzw. Update-Dienst, Ring 0    |
| **B Eigener VPS**  | Schule oder Gemeinde mit beliebigem Linux-VPS | Docker Compose, Installationsskript; mit eigenem Proxy (Caddy oder Traefik, Let's Encrypt) oder hinter vorhandenem | SQLite im Volume                  | Update-Dienst nach Richtlinie    |
| **C Im Schulnetz** | Schule mit Vorgabe «Daten im eigenen Netz»    | wie B auf eigenem Server oder VM; optional ohne Internet                                                           | SQLite, Sicherung auf NAS         | Update-Dienst oder Offline-Paket |
| **D Verteilt**     | viele Benutzer, hohe Verfügbarkeit, Plattform | mehrere Web-Knoten hinter Lastverteiler, Kubernetes (Helm-Chart); Datenbankserver, Redis, Object Storage           | Datenbankserver (Entscheid offen) | rollend, blau/grün               |

### B – Selbst installieren auf einem beliebigen VPS

- **Voraussetzungen:** Linux mit Docker Engine und Compose-Plugin, 1 vCPU, 1–2 GB RAM, 10 GB Speicher, Domain mit
  DNS-Eintrag auf den Server, ausgehender Zugang zu `ghcr.io` und zu einem SMTP-Dienst.
- **Installationsskript** `install.sh` (aus dem Release, Prüfsumme veröffentlicht, kein `curl | sh` ohne Prüfung):
  prüft Docker und Ports, fragt Domain, Proxy (eigener oder vorhandener), Mail, Sicherungsziel und Update-Richtlinie,
  schreibt `.env`, startet, ruft `glossary:pruefen` auf und legt den ersten Admin an.
- **Proxy-Bausteine:** `compose.proxy.yaml` (Caddy mit automatischem Zertifikat – einfacher als Traefik für
  Einzelinstallationen) oder `compose.traefik.yaml` (Labels für einen vorhandenen Traefik, wie bei uns). Ohne
  Zusatz lauscht das Glossary nur auf `127.0.0.1:8080` für einen eigenen Proxy (nginx, Apache).
- **Zugesichert** (Entscheid 12.11.2026): Ubuntu 24.04/26.04 und Debian 12/13; die Anleitung wird dort getestet (#179). Weitere Systeme ohne Zusage.

### C – Installation im Schulnetz (on premise)

- Wie B; zusätzlich: **ausgehender Proxy** (`HTTP_PROXY`/`HTTPS_PROXY`), **eigene Zertifizierungsstelle** der Schule
  (Datei einbinden, wird vom Container vertraut), Zeitserver der Schule.
- **Ohne Internet:** Je Release ein **Offline-Paket** (Image als `docker save`-Archiv, compose-Dateien, Prüfsummen,
  Signatur). Einspielen mit `glossary-update --paket datei.tar` – gleiche Sicherung und gleicher Rücksprung wie beim
  Update-Dienst. Update-Hinweis, Abgleich der Inhalte und Heartbeat sind dann aus.
- **Anmeldung:** lokale Konten oder – sobald vorhanden – über EduKSX-Ident bzw. den OIDC-Anbieter der Schule
  (Entra ID, Google) mit derselben Einstellung wie in der Anbindung unten.
- **Sicherung** auf ein NAS der Schule (SMB/NFS als Pfad oder S3-kompatibel).

### D – Verteilte Installation

- **Voraussetzung** ist eine zustandslose App (#190): Sitzungen, Cache und Sperren in Redis, Dateien (Uploads,
  Exporte) in Object Storage, Warteschlange in Redis oder Datenbank. Erst dann laufen mehrere Web-Knoten parallel.
- **Datenbank:** SQLite erlaubt nur einen schreibenden Knoten. Für «verteilt» braucht es einen Datenbankserver.
  Laravel unterstützt MySQL/MariaDB und PostgreSQL; Infomaniak bietet verwaltete MySQL-Datenbanken. Der frühere
  Entscheid «kein PostgreSQL» und #191 werden hier zusammen neu beurteilt (offene Frage 3).
- **Aufbau:** Helm-Chart mit `web` (mehrere Repliken), `worker`, `scheduler` (genau einer), Migration als eigener
  Job vor dem Wechsel; Prüfungen als Liveness/Readiness. Abwärtskompatible Migrationen sind Pflicht (DEC-027 U18).
- **Wann:** erst, wenn Betriebsart A an Grenzen kommt oder die Plattform (Ident, weitere Module) gemeinsam betrieben
  wird. Bis dahin genügt ein Server mit Sicherung und schnellem Rücksprung.

## Deployment auf Infomaniak

Löst den offenen Punkt «Kriterien und Zeitpunkt für die Prüfung von Infomaniak» in [KONZEPT-HOSTING.md](KONZEPT-HOSTING.md).

| Stufe | Angebot (Infomaniak)                                      | Einsatz                                                                                                     |
| ----- | --------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| 1     | **VPS Lite** oder **VPS Cloud**, Rechenzentrum Schweiz    | `prod-01` mit test und prod (Operation E35, E36); VPS Lite zu Beginn, VPS Cloud bei steigender Benutzerzahl |
| 1     | **Swiss Backup** oder **Object Storage** (S3-kompatibel)  | Sicherungen ausser Haus (D6), verschlüsselt mit restic                                                      |
| 1     | **Mail** (SMTP, `mail.infomaniak.com`)                    | Versand für `eduksx.ch` (DEC-034, #300); DNS bleibt bei Cloudflare (SPF, DKIM, DMARC)                       |
| 2     | **Public Cloud** mit **Kubernetes-Dienst**, Load Balancer | Betriebsart D und gemeinsamer Betrieb der Plattform (Ident, weitere Module)                                 |
| 2     | **Database Service** (verwaltete Datenbank)               | Datenbankserver für D, sofern der Datenbank-Entscheid passt                                                 |

- **Umzug** (Stufe 1): neue Instanz aus demselben Image aufsetzen, letzte Sicherung einspielen, Prüfung, DNS in
  Cloudflare umstellen (kurze TTL vorher), alte Instanz eine Woche lesend behalten. Das Intranet bleibt unberührt.
- **Vorher prüfen:** Vertrag zur Auftragsbearbeitung, Datenstandort Schweiz für alle genutzten Dienste, Kosten im
  Vergleich zu Hostinger, Messung von Antwortzeit und Ausrollzeit auf einer Testinstanz.
- **Cloudflare bleibt** für DNS und Schutz; Zertifikat am Server wie bisher (Let's Encrypt).

## Umzug nach PostgreSQL (#191, #333)

Auf PostgreSQL liegen alle Tabellen des Glossary im Schema **`glossary`** (DB-Standard E2; `DB_SCHEMA`, Vorgabe
`glossary`). Fehlt das Schema, legt die Anwendung es beim ersten Verbindungsaufbau an; ein Zugang mit Rechten nur auf
dieses Schema (B5) braucht es vorher angelegt (`create schema glossary authorization <zugang>`).

Umzug einer bestehenden SQLite-Datenbank (ohne Datenverlust, Zählung je Tabelle):

1. Sicherung (`php artisan glossary:sicherung`) und Anwendung anhalten.
2. Verbindung `pgsql` in der `.env` eintragen (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`),
   `DB_CONNECTION` noch auf `sqlite` lassen.
3. `php artisan migrate --database=pgsql --force` – Ziel auf denselben Stand bringen.
4. Probelauf: `php artisan glossary:umzug-postgres --trocken`, danach
   `php artisan glossary:umzug-postgres --leeren` (`--leeren` entfernt die Grunddaten der Migrationen im Ziel).
   Der Befehl vergleicht die Zeilen je Tabelle und bricht bei Abweichung ab.
5. `DB_CONNECTION=pgsql` setzen, Anwendung starten, Anmeldung und API prüfen. Die SQLite-Datei bleibt als Rückfall.

Getestet im CI-Job PostgreSQL (`tests/Feature/UmzugPostgresTest.php`).

## Anbindung an EduKSX-Ident und eduksx/core

Das Glossary bleibt ohne Plattform voll nutzbar. Die Anbindung wird je Installation eingeschaltet; die
vorbereitenden Arbeiten stehen in #185–#193.

| Einstellung (Beispiel)                                       | Wirkung                                                                          |
| ------------------------------------------------------------ | -------------------------------------------------------------------------------- |
| `GLOSSARY_ANMELDUNG=lokal \| oidc \| beides`                 | lokale Konten, Anmeldung über Ident/OIDC (#185) oder beides (Übergang)           |
| `EDUKSX_IDENT_ISSUER`, `…_CLIENT_ID`, `…_CLIENT_SECRET_FILE` | Ident-Instanz und Zugang; Rollen und Organisation aus den Claims (#186)          |
| `EDUKSX_EREIGNISSE_SECRET_FILE`                              | Ereignisse und Back-Channel-Logout von Ident annehmen (#187)                     |
| `EDUKSX_DIENST_CLIENT_ID`, `…_SECRET_FILE`                   | Zugriff auf andere Dienste mit Client Credentials (#188)                         |
| `EDUKSX_LEITSTAND_URL`                                       | Heartbeat und Aufträge an den Leitstand, nur ausgehend (DEC-027 U14); leer = aus |
| `/.well-known/eduksx-module.json`, `/health`                 | Modul-Steckbrief und Zustand für Ident und Leitstand (#189), immer vorhanden     |

- **eduksx/core** ist ein PHP-Paket, kein laufender Dienst: Es kommt über Composer ins Image und ändert am Deployment
  nichts (#193).
- **Reihenfolge beim Ausrollen** mit Plattform: zuerst Ident, dann die Module; Module verkraften eine kurze
  Nichterreichbarkeit von Ident (laufende Sitzungen bleiben gültig, neue Anmeldungen warten mit Hinweis).

## Datenschutz-Prüfung (11.11.2026)

Geprüft gegen [DATENSCHUTZ-ENTWURF.md](DATENSCHUTZ-ENTWURF.md), [LOESCHKONZEPT.md](LOESCHKONZEPT.md),
[KONZEPT-HOSTING.md](KONZEPT-HOSTING.md) (DEC-019) und das Compliance-Konzept von MarKusSX-Operation.

| Nr. | Punkt                                            | Bewertung                                                                                                                                 | Massnahme                                                                                                                                                                              |
| --- | ------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| P1  | Echte Daten nur in der Schweiz                   | erfüllt für prod und Backups (Infomaniak, Swiss Backup)                                                                                   | dev auf Hostinger nur mit Testdaten: Registrierung aus (`MAIL_MAILER=log`), keine Kopie aus prod, Zugang zur Seite für Fremde sperren oder `noindex`                                   |
| P2  | Serverlogs höchstens 14 Tage (Löschkonzept)      | **Konflikt:** Vector schickt alle Container-Ausgaben an VictoriaLogs, Aufbewahrung dort 90 Tage; Zugriffsprotokolle enthalten IP-Adressen | Frist für das Glossary in Operation auf 14 Tage setzen oder IP-Adressen vor dem Versand kürzen (Vector) – Entscheid nötig                                                              |
| P3  | Sicherungen ausser Haus [30 Tage] (Löschkonzept) | **Konflikt:** Operation bewahrt 7 täglich, 4 wöchentlich, 6 monatlich auf; gelöschte Konten bleiben bis 6 Monate in Sicherungen           | Frist im Löschkonzept und in der Datenschutzerklärung auf 6 Monate anpassen (mit Regel «nach Wiederherstellung Löschfristen anwenden») oder für das Glossary kürzer – Entscheid nötig  |
| P4  | Auftragsbearbeiter dokumentiert                  | Datenschutzerklärung nennt Hostinger als Hosting von Konten und Datenbank                                                                 | auf Infomaniak (Server, Swiss Backup, Mail) ändern; Hostinger nur noch dev ohne Personendaten; Vertrag mit Infomaniak ist in Operation noch OFFEN – vor der Registrierung abschliessen |
| P5  | Cloudflare                                       | Operation E31: Apps mit Personendaten nur DNS (graue Wolke)                                                                               | keine Cloudflare-Cookies und kein Turnstile auf prod, solange nicht geklärt; Cookie-Tabelle anpassen                                                                                   |
| P6  | Zugangsdaten bei GitHub                          | nur dev-Schlüssel, keine Zugangsdaten zu prod (Operation E29)                                                                             | Gegenmassnahmen beim Deploy-Schlüssel (oben)                                                                                                                                           |
| P7  | S3-Ziele                                         | zulässig mit Verschlüsselung beim Absender und Vertrag                                                                                    | nur Anbieter in der Schweiz oder mit gleichwertigem Schutz und AVV; Schlüssel nur in 1Password                                                                                         |
| P8  | SQLite im Betrieb                                | unkritisch (Datei im Volumen, nur der App-Container hat Zugriff, in der Sicherung enthalten)                                              | beim Wechsel zu PostgreSQL: Verbindung nur im internen Netz, eigener Benutzer je App                                                                                                   |

Ergebnis: Die Entscheide verletzen keine Datenschutzziele, wenn P1 (dev ohne echte Daten) eingehalten wird. P2 und
P3 entschieden am 11.11.2026 nach Löschkonzept (Logs 14 Tage, Sicherungen 30 Tage); die Umsetzung liegt in
MarKusSX-Operation. P4 und P5 sind in der Datenschutzerklärung nachgeführt (#207, PR #208).

## Fragen und Entscheide

1. ~~Vorschau aus `main`~~ – entschieden 11.11.2026: dev auf Hostinger aus jedem Merge, test/prod nur aus Releases.
2. ~~Zeitpunkt Infomaniak~~ – entschieden 11.11.2026: `prod-01` nach MarKusSX-Operation, VPS Lite oder VPS Cloud.
3. ~~Datenbank~~ – entschieden 11.11.2026: SQLite für dev und zu Beginn in prod, bald PostgreSQL (#191).
4. ~~Proxy~~ – entschieden 12.11.2026: Caddy als Standard für Einzelinstallationen (B, C); Traefik nur für Server mit vorhandenem Traefik.
5. ~~Offline-Paket~~ – entschieden 12.11.2026: erst auf Wunsch einer Schule.
6. ~~Betriebssysteme~~ – entschieden 12.11.2026: Ubuntu 24.04/26.04 und Debian 12/13; andere ohne Zusage.
7. ~~Logfrist (P2)~~ – entschieden 11.11.2026: wie im Löschkonzept, 14 Tage, auch in der zentralen Protokollierung.
8. ~~Frist der Sicherungen (P3)~~ – entschieden 11.11.2026: wie im Löschkonzept, 30 Tage (eigener Backrest-Plan).

## Issues (Meilenstein «0.10.0 – Selbst hosten und Updates»)

| Issue | Inhalt                                                                            |
| ----- | --------------------------------------------------------------------------------- |
| #194  | Konzept (dieses Dokument)                                                         |
| #195  | Deployment über fertiges Image statt Bauen auf dem Server (D1, D2, D5)            |
| #196  | Konfiguration vollständig über die Umgebung (D3)                                  |
| #197  | Sicherung ausser Haus mit Wiederherstellungstest (D6)                             |
| #198  | Selbst installieren auf beliebigem VPS (Betriebsart B)                            |
| #199  | Installation im Schulnetz (Betriebsart C)                                         |
| #200  | Verteilte Installation (Betriebsart D)                                            |
| #201  | Infomaniak prüfen und Umzug vorbereiten                                           |
| #202  | Anbindung an EduKSX-Ident und eduksx/core per Einstellung                         |
| #207  | Datenschutz: Log- und Sicherungsfristen mit MarKusSX-Operation abgleichen (P2–P5) |

Dazu aus [KONZEPT-UPDATES.md](KONZEPT-UPDATES.md): #175 (Image je Release), #177 (Update-Dienst), #179 (Anleitung
«Selbst hosten»), #183 (Leitstand), #184 (blau/grün); Vorbereitung Plattform: #185–#193.
