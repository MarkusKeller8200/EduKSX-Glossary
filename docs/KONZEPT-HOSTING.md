# Konzept: Hosting und Domain

Entscheid des Projektverantwortlichen vom 07.11.2026 (#4), nach Entscheidungsvorlage im Chat; abgenommen mit
Anpassungen am selben Tag. Betrifft DEC-012
(Hosting) in [ENTSCHEIDE.md](ENTSCHEIDE.md).

## Entscheid

1. **Zuerst Hostinger, später Infomaniak prüfen.** Das Glossar läuft als eigener Container auf dem bestehenden
   Hostinger-VPS (srv1807739) neben dem Intranet-Prototyp, hinter dem vorhandenen Traefik. Es entstehen keine
   zusätzlichen Kosten, und das Deployment folgt demselben Muster wie das Intranet (GitHub Actions → SSH → Docker
   Compose, siehe `DEPLOY.md` im sek-intranet).
2. **Begründung für den Standort:** Das Glossar enthält nur öffentliche Begriffe. Besonders schützenswerte Daten gibt
   es nicht; ein Serverstandort in der Schweiz ist darum nicht zwingend (anders als beim Intranet, ADR-005).
   Personenbezogen sind höchstens die Angaben bei Community-Vorschlägen (Datenminimierung, KONZEPT Abschnitt
   Datenschutz).
3. **Infomaniak später prüfen**, sobald einer dieser Punkte eintritt: produktiver Betrieb mit Community-Konten,
   Umzug des Intranets auf Infomaniak (gemeinsamer Betrieb), Wunsch nach eigenem Server statt Platz neben dem
   Prototyp. Weil das Glossar ein Container ist, bleibt der Umzug einfach (Image, Volumen, Datenbank-Dump).
4. **Domain:** `glossary.eduksx.ch`. Die Domain `eduksx.ch` ist registriert und wird über **Cloudflare** geschützt.
   Weitere Einträge (CNAME) für andere EduKSX-Projekte kommen später dazu.
5. **Immer «Glossary»:** Subdomain, Ordner, Container und technische Namen heissen «glossary», wie im Produktnamen
   EduKSX-Glossary (englischer Funktionsbegriff) – nie «glossar».
6. **GitHub Pages bleibt** in reduzierter Form und wird künftig mitgepflegt (Abschnitt unten).

## Aufbau

```text
Browser ─► Cloudflare (DNS, Proxy, WAF) ─► Hostinger-VPS ─► Traefik (TLS) ─► Container «glossary»
                                                                              ├─ App (Web + /api/v1)
                                                                              └─ Datenbank (Volumen)
```

| Was                   | Wert                                                                 |
| --------------------- | -------------------------------------------------------------------- |
| Server                | Hostinger-VPS srv1807739 (gleicher Server wie der Intranet-Prototyp) |
| Ordner auf dem Server | `/docker/glossary` (Compose-Datei, `.env`, Quellcode in `src/`)      |
| Adresse               | `https://glossary.eduksx.ch`                                         |
| Prüfadresse           | `https://glossary.eduksx.ch/up`                                      |
| Daten                 | eigenes Docker-Volumen, getrennt vom Intranet                        |

- **Getrennt vom Intranet:** eigenes Compose-Projekt, eigenes Netzwerk, eigenes Volumen, eigener App-Schlüssel,
  eigener Deploy-Schlüssel. Gemeinsam sind nur Server und Traefik.
- **Suchmaschinen erlaubt:** Das Intranet sperrt Suchmaschinen aus; das Glossar soll gefunden werden (eigene
  `robots.txt`, kein `noindex`).

## GitHub Pages (reduzierte Fassung)

Neben der Anwendung bleibt unter `markuskeller8200.github.io/EduKSX-Glossary` eine schlanke, statische Fassung
bestehen und wird mitgepflegt:

- **Inhalt:** nur veröffentlichte Begriffe zum Lesen (Liste mit Suche, Index A–Z, Versionsangabe); keine Redaktion,
  keine Vorschläge, keine API. Vorschläge laufen über den Link zur Anwendung bzw. die GitHub-Issue-Formulare.
- **Daten:** `site/begriffe.json` ist ein Export des veröffentlichten Datenstands (gleiches Format wie
  `/api/v1/exports?format=json`), nicht von Hand gepflegt, sobald die Anwendung läuft. Aktualisiert wird beim
  Release bzw. per Workflow.
- **Hinweis** auf jeder Seite: «Vollständige Fassung: glossary.eduksx.ch».
- Ordner `site/`, Workflow `.github/workflows/pages.yml`; keine Abhängigkeit von der Anwendung zur Laufzeit.

## Cloudflare und Zertifikate

Die Nameserver von `eduksx.ch` sind noch nicht auf Cloudflare aktiv. Reihenfolge:

1. Zone `eduksx.ch` in Cloudflare anlegen, Nameserver beim Registrar umstellen, Aktivierung abwarten.
2. DNS-Eintrag `A glossar → 76.13.156.138`. Für den ersten Zertifikatsbezug «Nur DNS» (graue Wolke) wie beim
   Intranet; danach auf «Proxied» (orange Wolke) umstellen.
3. SSL/TLS-Modus **Full (strict)**: Cloudflare spricht mit dem Server nur über ein gültiges Zertifikat.
4. Zertifikat auf dem Server, eine der zwei Varianten (Entscheid bei der Einrichtung, offener Punkt):
    - **Let's Encrypt über Traefik** wie beim Intranet. Hinter dem Proxy erneuert sich das Zertifikat in der Regel
      weiter; bei Problemen auf die DNS-Prüfung mit einem Cloudflare-API-Token (nur «Zone DNS: Edit» für
      `eduksx.ch`) umstellen. Diese deckt auch spätere Subdomains ab.
    - **Cloudflare Origin Certificate** (bis 15 Jahre gültig, nur für Cloudflare gültig) in Traefik hinterlegen.
5. Optional später: Zugriff auf den Server nur noch von Cloudflare-Adressen (Firewall), Cloudflare-Regeln gegen
   Spam für das Vorschlagsformular (Rate Limiting, Turnstile).

Weitere Subdomains (CNAME) zeigen auf `glossary.eduksx.ch` oder direkt auf den Server; jede bekommt in Traefik eine
eigene Regel.

## Offene Punkte

Werden geklärt, wenn sie benötigt werden:

- Zertifikat: Let's Encrypt (HTTP- oder DNS-Prüfung) oder Cloudflare Origin Certificate.
- Sicherung der Datenbank (Ziel, Intervall, Wiederherstellungstest) – mit dem Stack-Entscheid.
- E-Mail-Versand für Rückmeldungen an Einreichende: ausgehend über einen SMTP-Dienst (Port 587).
- Kriterien und Zeitpunkt für die Prüfung von Infomaniak.
