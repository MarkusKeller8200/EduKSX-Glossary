# Konzept: Deployment und Installationsarten (DEC-028, Entwurf)

Stand: Entwurf 08.11.2026 zur Abnahme. Baut auf [KONZEPT-UPDATES.md](KONZEPT-UPDATES.md) (DEC-027) und
[KONZEPT-HOSTING.md](KONZEPT-HOSTING.md) (DEC-013) auf; die Plattform-Schnittstellen stehen im Konzept
EduKSX-Plattform (Repo EduKSX-Architektur). Umsetzung erst nach Freigabe.

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

| Nr. | Vorschlag                                                                                                                                                                                                                                                                                                                |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| D1  | **Einmal bauen, überall gleich ausrollen.** Die CI baut das Image (amd64 und arm64), prüft es (Tests, Schwachstellen-Scan, Stückliste SBOM) und legt es mit festem Digest in die Registry. Vorschau und Produktion erhalten **dasselbe** Image; ausgerollt wird nur noch ein Digest, nie Quellcode.                      |
| D2  | **Zwei Kanäle.** Jeder Merge auf `main` → Image `main-<sha>` → Vorschau (`vorschau.glossary.eduksx.ch`). Ein Release → Tags `0.10.0`, `0.10`, `latest` (DEC-027 U1) → Produktion und selbst gehostete Instanzen. Produktion übernimmt den Digest, der in der Vorschau geprüft wurde.                                     |
| D3  | **Konfiguration nur über die Umgebung.** Alles, was sich je Installation unterscheidet, steht in `.env` bzw. Umgebungsvariablen, dokumentiert in `.env.example`. Geheimnisse auch als Datei (`*_FILE`, für Docker/Kubernetes Secrets). Ein Befehl `glossary:pruefen` zeigt fehlende oder widersprüchliche Einstellungen. |
| D4  | **Bausteine statt Varianten.** Eine Basis-compose-Datei plus Zusätze: eigener Proxy mit Zertifikat, vorhandener Proxy, Update-Dienst, Vorschau. Kein Code je Kunde (DEC-027 U13).                                                                                                                                        |
| D5  | **Prüfen und zurück.** Nach jedem Ausrollen Prüfung `/up` (läuft) und `/health` (Datenbank, Warteschlange, Speicher, Version stimmt). Schlägt sie fehl, startet das vorherige Image wieder; die Sicherung von unmittelbar davor steht bereit (DEC-027 U6, U7).                                                           |
| D6  | **Sicherung ausser Haus.** Täglich und vor jedem Update eine verschlüsselte Sicherung (Datenbank und Schlüssel) in einen S3-kompatiblen Speicher (Infomaniak Object Storage, Schul-NAS, MinIO) oder einen lokalen Pfad; Aufbewahrung nach Löschkonzept; Wiederherstellungstest monatlich automatisch.                    |
| D7  | **Nur ausgehend.** Keine Installation braucht offene Ports ausser HTTPS für die Benutzer. Abgleich, Update-Hinweis, Heartbeat und Anbindung an Ident laufen ausgehend und sind einzeln abschaltbar.                                                                                                                      |
| D8  | **Gleicher Weg für uns und für Schulen.** Unsere Instanzen nutzen dieselben Bausteine und denselben Update-Dienst wie selbst gehostete. Was bei uns nicht läuft, wird nicht verteilt (DEC-027 U10).                                                                                                                      |

## Optimierter Ablauf

```text
PR ──► ci (Prüfungen) ──► Merge main ──► Image bauen + Scan + SBOM + Signatur ──► ghcr.io/…:main-<sha>
                                                                                   │
                                     Vorschau: pull Digest ─► Sicherung ─► Start ─► /health ─► ok / zurück
                                                                                   │
Release (Tag v0.10.0) ──► gleichen Digest als 0.10.0 / latest markieren ──► Produktion: pull ─► … ─► ok / zurück
                                                                         └► selbst gehostete Instanzen (Update-Dienst,
                                                                            Ringe und Wartungsfenster nach DEC-027)
```

- **Ausrollen ohne Quellcode:** Auf dem Server liegen nur `compose.yaml`, die Zusätze und `.env`. Die compose-Dateien
  gehören zum Release (Anhang), damit Version und Aufbau zusammenpassen.
- **Schneller:** Kein Bauen auf dem Server, Image vorab geladen; ein Update dauert Sekunden statt Minuten.
  Blau/grün (DEC-027 U17) reduziert den Unterbruch weiter.
- **Nachvollziehbar:** Die Seite «Versionen» und `/health` zeigen Version, Commit und Digest; jedes Ausrollen steht
  im Protokoll (DEC-027 U9).
- **Sicherer:** Der Deploy-Schlüssel darf nur noch `pull` und Neustart für das Projekt `glossary` ausführen (erzwungener
  Befehl in `authorized_keys`) statt voller Root-Zugang; später übernimmt der Update-Dienst auch unser Ausrollen.

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
- **Getestet** wird die Anleitung mindestens auf Debian 12/13 und Ubuntu 24.04 (#179); weitere Systeme ohne Zusage.

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

| Stufe | Angebot (Infomaniak)                                      | Einsatz                                                                                     |
| ----- | --------------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| 1     | **VPS Cloud** (z. B. 2 vCPU, 4 GB), Rechenzentrum Schweiz | Betriebsart A wie heute auf Hostinger: Docker Compose, eigener Proxy, Snapshots des Servers |
| 1     | **Object Storage** (S3-kompatibel, mit Object Lock)       | Sicherungen ausser Haus (D6), unveränderbar für die Aufbewahrungsfrist                      |
| 1     | **Mail** (SMTP, `mail.infomaniak.com`)                    | Versand wie in `DEPLOY.md` beschrieben                                                      |
| 2     | **Public Cloud** mit **Kubernetes-Dienst**, Load Balancer | Betriebsart D und gemeinsamer Betrieb der Plattform (Ident, weitere Module)                 |
| 2     | **Database Service** (verwaltete Datenbank)               | Datenbankserver für D, sofern der Datenbank-Entscheid passt                                 |

- **Umzug** (Stufe 1): neue Instanz aus demselben Image aufsetzen, letzte Sicherung einspielen, Prüfung, DNS in
  Cloudflare umstellen (kurze TTL vorher), alte Instanz eine Woche lesend behalten. Das Intranet bleibt unberührt.
- **Vorher prüfen:** Vertrag zur Auftragsbearbeitung, Datenstandort Schweiz für alle genutzten Dienste, Kosten im
  Vergleich zu Hostinger, Messung von Antwortzeit und Ausrollzeit auf einer Testinstanz.
- **Cloudflare bleibt** für DNS und Schutz; Zertifikat am Server wie bisher (Let's Encrypt).

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

## Offene Fragen (Entscheid durch die Projektverantwortliche Person)

1. Vorschau-Instanz aus `main` einführen und Produktion nur noch aus Releases (deckt sich mit DEC-027, Frage 3)?
2. Zeitpunkt für Infomaniak Stufe 1: jetzt als Testinstanz, oder erst vor der Freischaltung der Community-Konten?
3. Datenbank für «verteilt»: MySQL/MariaDB (passt zum Angebot von Infomaniak) oder PostgreSQL (#191) – oder
   verteilt vorerst gar nicht anbieten und SQLite beibehalten?
4. Proxy für Einzelinstallationen (B, C): Caddy als einfacher Standard, Traefik nur für vorhandene Installationen?
5. Offline-Paket (C ohne Internet) von Beginn an oder erst auf Nachfrage einer Schule?
6. Welche Betriebssysteme sichern wir für die Selbstinstallation zu?

## Issues (Meilenstein «0.10.0 – Selbst hosten und Updates»)

| Issue | Inhalt                                                                 |
| ----- | ---------------------------------------------------------------------- |
| #194  | Konzept (dieses Dokument)                                              |
| #195  | Deployment über fertiges Image statt Bauen auf dem Server (D1, D2, D5) |
| #196  | Konfiguration vollständig über die Umgebung (D3)                       |
| #197  | Sicherung ausser Haus mit Wiederherstellungstest (D6)                  |
| #198  | Selbst installieren auf beliebigem VPS (Betriebsart B)                 |
| #199  | Installation im Schulnetz (Betriebsart C)                              |
| #200  | Verteilte Installation (Betriebsart D)                                 |
| #201  | Infomaniak prüfen und Umzug vorbereiten                                |
| #202  | Anbindung an EduKSX-Ident und eduksx/core per Einstellung              |

Dazu aus [KONZEPT-UPDATES.md](KONZEPT-UPDATES.md): #175 (Image je Release), #177 (Update-Dienst), #179 (Anleitung
«Selbst hosten»), #183 (Leitstand), #184 (blau/grün); Vorbereitung Plattform: #185–#193.
