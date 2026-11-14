# Konzept: Abgleich mit dem öffentlichen Glossary (Master)

Stand: **Entwurf 14.11.2026**, Vorschlag **DEC-041** (#180). Konkretisiert U11 aus [KONZEPT-UPDATES.md](KONZEPT-UPDATES.md)
(DEC-027). Auftrag aus dem Chat (14.11.2026):

> Das öffentliche Glossary soll der Master sein. Jeder, der ein eigenes Glossary verwendet, soll seines mit dem Master
> abgleichen können. Es soll möglich sein zu wählen, welche übernommen werden sollen. Bei Abwahl sollen diese als nicht
> sichtbar markiert werden. Diese dürfen dann nicht angezeigt werden. Es wird eine Verwaltung benötigt, um sie aus- und
> einzublenden.

## 1. Begriffe

| Begriff             | Bedeutung                                                                                                         |
| ------------------- | ----------------------------------------------------------------------------------------------------------------- |
| **Master**          | das öffentliche Glossary `https://glossary.eduksx.ch` (einstellbar); gibt Begriffe nur aus, gleicht selbst nie ab |
| **Instanz**         | ein selbst gehostetes Glossary (Schule, Gemeinde, Schulnetz), das mit dem Master abgleicht                        |
| **Master-Begriff**  | Begriff, der aus dem Master stammt (gleiche UUID und gleicher Permalink-Code wie im Master)                       |
| **eigener Begriff** | Begriff, den die Instanz selbst erfasst hat; der Abgleich berührt ihn nie                                         |
| **ausgeblendet**    | Master-Begriff, den die Instanz nicht zeigen will (Abwahl); bleibt gespeichert und wird weiter abgeglichen        |

## 2. Ablauf

1. **Einrichten** (Administration › Abgleich, Recht `settings`): Abgleich einschalten, Adresse des Masters (Vorgabe
   `https://glossary.eduksx.ch`), Art der Übernahme (A6), Zeitplan.
2. **Abholen** über die bestehende öffentliche API des Masters: beim ersten Mal der Vollstand `/api/v1/exports`, danach
   der Änderungs-Feed `/api/v1/changes?since=<cursor>` (veröffentlicht, zurückgezogen, zusammengeführt). Nur
   veröffentlichte Felder (DEC-018); keine Konten, Organisationen oder persönlichen Darstellungen.
3. **Auswählen** (Administration › Abgleich › «Neu und geändert»): neue und geänderte Master-Begriffe als Liste mit
   Kontrollkästchen; einzelne, mehrere oder alle übernehmen. Was abgewählt wird, ist **ausgeblendet**.
4. **Verwalten** (Administration › Abgleich › «Ausgeblendet»): Liste aller ausgeblendeten Master-Begriffe mit Suche;
   einzeln oder mehrere wieder **einblenden** bzw. sichtbare **ausblenden**.
5. **Anzeigen:** Ausgeblendete Begriffe erscheinen nirgends öffentlich – nicht auf der Website, nicht in Suche, API,
   Export, Index für die Einbindung, Permalink (`/g/{code}` → 404), GitHub Pages oder Plugins. In der Administration
   bleiben sie mit Hinweis «ausgeblendet» sichtbar.

## 3. Entscheide (Vorschlag)

| Nr. | Frage                                            | Vorschlag                                                                                                                                                                                                                                                                          | Status    |
| --- | ------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------- |
| A1  | Wer ist Master?                                  | das öffentliche Glossary; eine Instanz gleicht nur mit **einem** Master ab; der Master selbst gleicht nie ab (Einstellung dort gesperrt)                                                                                                                                           | VORSCHLAG |
| A2  | Kennung der Master-Begriffe                      | gleiche UUID und gleicher Permalink-Code wie im Master; Spalte `terms.origin` (`local` / `master`), dazu `master_revision` und `synced_at`                                                                                                                                         | VORSCHLAG |
| A3  | Master-Begriffe lokal ändern?                    | **nein** – sie sind in der Instanz schreibgeschützt (Änderungen gehören in den Master, z. B. als Vorschlag dort); anpassen lässt sich die Darstellung wie bisher über Organisation bzw. «Meine Darstellung»                                                                        | VORSCHLAG |
| A4  | Ausblenden                                       | eigenes Merkmal der Instanz `terms.is_hidden` (Vorgabe nein), nur für Master-Begriffe; ändert der Abgleich nie; jede Änderung mit wer/wann im Änderungsprotokoll                                                                                                                   | VORSCHLAG |
| A5  | Wirkung «ausgeblendet»                           | wie nicht veröffentlicht: überall ausser in der Administration unsichtbar; Permalink 404; Verweise und Beziehungen darauf werden nicht angezeigt                                                                                                                                   | VORSCHLAG |
| A6  | Art der Übernahme                                | wählbar je Instanz: **«alle neuen anzeigen»** (neue Master-Begriffe sofort sichtbar) oder **«neue zuerst prüfen»** (neue sind ausgeblendet, bis sie gewählt werden) – Vorgabe «neue zuerst prüfen»; Änderungen an bereits übernommenen Begriffen kommen immer direkt               | VORSCHLAG |
| A7  | Zurückgezogen / zusammengeführt im Master        | folgt dem Master: zurückgezogen → zurückgezogen, zusammengeführt → Weiterleitung auf das Ziel; ein ausgeblendetes Ziel bleibt ausgeblendet                                                                                                                                         | VORSCHLAG |
| A8  | Kategorien, Herkunft, Übersetzungen, Beziehungen | werden mit den Begriffen übernommen und sind wie diese schreibgeschützt – **mit Reihenfolge (`sort_order`) und Farbe der Labels (`color`)** wie im Master (Chat 14.11.2026); eigene Kategorien und Herkünfte behalten ihre Reihenfolge und Farbe und stehen nach denen des Masters | VORSCHLAG |
| A9  | Konflikte mit eigenen Begriffen                  | gleiche Abkürzung bzw. Bezeichnung: beide bleiben, Hinweis in «Doppelte»; gleicher Slug: der Master-Begriff erhält einen Zusatz (`-master`); gleiche UUID oder gleicher Code: nicht möglich (eigene Begriffe haben eigene)                                                         | VORSCHLAG |
| A10 | Zeitplan                                         | stündlich über den Dienst «scheduler», dazu «Jetzt abgleichen» in der Administration und `php artisan glossary:abgleich`                                                                                                                                                           | VORSCHLAG |
| A11 | Fehler beim Abgleich                             | nichts teilweise übernehmen (Transaktion je Lauf); Meldung in der Administration und im Protokoll; nächster Lauf setzt beim letzten Cursor fort                                                                                                                                    | VORSCHLAG |

## 4. Datenmodell (Vorschlag)

- `terms`: `origin` (`local`/`master`, CHECK), `master_revision` (Zahl, leer bei eigenen), `synced_at`, `is_hidden`
  (Wahrheitswert, Vorgabe falsch). Nur ergänzt, nichts umbenannt (U18).
- `categories`, `sources`: ebenfalls `origin`.
- `settings`: `sync.enabled`, `sync.master_url`, `sync.mode` (`show_new` / `review_new`), `sync.cursor`, `sync.last_run_at`,
  `sync.last_result`.
- Datenklassen: alle neuen Spalten «intern»; der Abgleich überträgt keine Personendaten (DB B2).

## 4a. Entwicklungs-Sync dev ↔ Master (Vorschlag)

Auftrag aus dem Chat (14.11.2026): «Alles ohne Konten; es soll ein Sync in beide Richtungen sein, dann kann ich neue
Einträge im dev entwickeln und hochladen.» Gilt nur zwischen **einer** Entwicklungsinstanz (dev) und dem Master – nicht
für Kunden-Instanzen (dort gilt der Abgleich oben).

| Nr. | Frage             | Vorschlag                                                                                                                                                                                                                                                                                                                                                                                                   | Status    |
| --- | ----------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------- |
| A12 | Rolle der Instanz | Einstellung `sync.role`: `master`, `instance` (Abgleich oben) oder `development` (dieser Abschnitt); `development` nur mit `APP_ENV` ≠ production erlaubt                                                                                                                                                                                                                                                   | VORSCHLAG |
| A13 | Master → dev: was | **alles ausser Konten**: Begriffe mit Revisionen und Entwürfen, Kategorien, Herkunft (mit Reihenfolge und Farben), Übersetzungen, Beziehungen, Vorschläge, Organisationen, Einstellungen ohne Geheimnisse, Protokolle. Personendaten in diesen Tabellen werden dabei **anonymisiert** (wie `glossary:dump --anonymisiert`, DB-Standard B12); Verweise auf Konten, die es auf dev nicht gibt, werden geleert | VORSCHLAG |
| A14 | Master → dev: wie | dev holt beim Master einen anonymisierten Dump über einen geschützten Endpunkt (Servicekonto mit Scope `glossary:sync.read`) und spielt ihn ein wie «Wiederherstellen – Konten behalten» (#355): Konten, Rollen und Sitzungen auf dev bleiben, alles andere wird ersetzt; vorher automatische Sicherung auf dev. Auslösen von Hand («Vom Master holen») oder nach Zeitplan                                  | VORSCHLAG |
| A15 | dev → Master: was | **Inhalte**: neue und geänderte Begriffe (mit Übersetzungen, Suchbegriffen, Beziehungen, Kategorien- und Herkunftszuordnung), neue und geänderte Kategorien und Herkünfte (mit Reihenfolge und Farbe). Keine Konten, Organisationen, Vorschläge, Einstellungen oder Protokolle                                                                                                                              | VORSCHLAG |
| A16 | dev → Master: wie | Liste «Bereit zum Hochladen» (seit dem letzten Holen geändert) mit Kontrollkästchen; dev sendet die Auswahl an den Master (Servicekonto mit Scope `glossary:sync.write`). Auf dem Master entsteht je Begriff eine **neue Revision als Entwurf** mit Herkunft «dev» – veröffentlicht wird dort wie gewohnt (Recht `publish`); optional «direkt veröffentlichen», wenn das Servicekonto das Recht hat         | VORSCHLAG |
| A17 | Konflikte         | jeder Begriff trägt die Revision, auf der die dev-Änderung beruht; hat sich der Begriff auf dem Master seither geändert, lehnt der Master ihn ab (409) und dev zeigt ihn als «Konflikt» (Vergleich Master ↔ dev, dann neu holen oder bewusst überschreiben)                                                                                                                                                 | VORSCHLAG |
| A18 | Kennungen         | UUID und Permalink-Code bleiben in beiden Richtungen gleich; neue Begriffe von dev behalten ihre UUID, der Code wird auf dem Master geprüft (bei Kollision neu vergeben und an dev zurückgemeldet)                                                                                                                                                                                                          | VORSCHLAG |

Sicherheit: Beide Endpunkte nur mit Servicekonto-Token, eigenem Scope, Abruflimit und Protokoll; der Dump für dev
enthält nie Passwörter, zweite Faktoren, Tokens oder Geheimnisse der Einstellungen.

## 5. Sicherheit

- Abholen nur über HTTPS, nur von der eingestellten Adresse, mit Zeitlimit und Grössenbegrenzung (SSRF-Schutz:
  keine internen Adressen, keine Weiterleitungen auf andere Hosts).
- Inhalte werden wie Eingaben geprüft (Feldlängen, erlaubte Werte); HTML wird nicht übernommen.
- Recht `settings` für Einrichten, Recht `edit-terms` für Auswählen und Ein-/Ausblenden.

## 6. Tests

- Erster Abgleich: alle veröffentlichten Master-Begriffe kommen an; bei «neue zuerst prüfen» ausgeblendet.
- Änderungs-Feed: geänderter, zurückgezogener und zusammengeführter Begriff werden nachgeführt.
- Kategorien und Herkunft: Reihenfolge und Labelfarbe wie im Master, auch nach einer Änderung im Master.
- Ausgeblendet: nicht auf Website, Suche, API, Export, Index, Permalink; in der Administration sichtbar.
- Eigene Begriffe, Organisation und «Meine Darstellung» bleiben unverändert.
- Fehler beim Abholen: Datenbank unverändert, Meldung sichtbar.
- Master-Instanz: Abgleich lässt sich nicht einschalten.
- Entwicklungs-Sync: «Vom Master holen» übernimmt alles ausser Konten, Personendaten anonymisiert; Konten auf dev
  bleiben.
- Hochladen: neuer und geänderter Begriff landen auf dem Master als Entwurf; geänderter Begriff mit neuerer
  Master-Revision ergibt «Konflikt».
- `development` lässt sich in production nicht einstellen.

## 7. Issues

| Issue | Inhalt                                                                       |
| ----- | ---------------------------------------------------------------------------- |
| #180  | Abgleich mit dem Master, Auswahl, Ausblenden und Verwaltung (dieses Konzept) |

## 8. Verlauf

- 14.11.2026: Entwurf mit Vorschlag DEC-041 nach Auftrag im Chat; ergänzt: Reihenfolge und Labelfarben abgleichen; Entwicklungs-Sync dev ↔ Master in beide Richtungen (A12–A18).
