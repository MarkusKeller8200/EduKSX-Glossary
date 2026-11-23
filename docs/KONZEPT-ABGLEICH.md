# Konzept: Abgleich mit dem öffentlichen Glossary (Master)

Stand: **Entwurf 14.11.2026, Abschnitt 4a beschlossen 21.11.2026** (**DEC-041**, #180, #478; Abschnitte 1–4 bleiben
Vorschlag bis zur Umsetzung von #180). Konkretisiert U11 aus [KONZEPT-UPDATES.md](KONZEPT-UPDATES.md)
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

## 4a. Entwicklungs-Sync zwischen dev, test und prod (beschlossen 21.11.2026, #478)

Auftrag aus dem Chat (14.11.2026, erweitert 21.11.2026): «Alles ohne Konten; es soll ein Sync in beide Richtungen
sein, dann kann ich neue Einträge im dev entwickeln und hochladen» – für alle Paare **dev ↔ prod**, **test ↔ prod** und
**dev ↔ test**. Inhalte werden **auf dev gepflegt** und nach test und prod **hochgeladen**; **Holen** bringt dev (bzw.
test) auf den Stand der Gegenstelle. Gilt nur zwischen den eigenen Umgebungen, nicht für Kunden-Instanzen (dort gilt der
Abgleich oben).

| Nr. | Frage                      | Entscheid                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| --- | -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| A12 | Rolle der Instanz          | Einstellung `sync.role`: `master` (prod), `test` (test), `development` (dev) oder `instance` (Abgleich oben; Vorgabe). `development` nur mit `APP_ENV` ≠ production – dev läuft darum mit `APP_ENV=staging` (Entscheid 23.11.2026); `test` auch mit `APP_ENV=production` (test läuft mit dem Image `:beta`). `master` gibt es betrieblich genau einmal (glossary.eduksx.ch); das lässt sich technisch nicht prüfen und steht im Betriebshandbuch |
| A13 | Holen: was                 | **alles ausser Konten**: Begriffe mit Revisionen und Entwürfen, Kategorien, Herkunft, Übersetzungen, Beziehungen, Vorschläge, Organisationen, Protokolle – **anonymisiert** wie `glossary:dump --anonymisiert` (B12). Lokal bleiben: Konten, Rollen, Sitzungen, **Einstellungen**, **Servicekonten mit Token** und die **Gegenstellen** (jede Umgebung behält Mail, Registrierung, Impressum und Zugänge)                                        |
| A14 | Holen: wie                 | Die holende Instanz lädt bei der Gegenstelle einen anonymisierten Dump (`GET /api/v1/sync/dump`, Scope `glossary:sync.read`) und spielt ihn ein wie «Wiederherstellen – Konten behalten» (#355), vorher **automatische Sicherung**. Erlaubt: `development` holt von `test` und `master`, `test` holt von `master`; `master` und `instance` holen nie (Entwicklungs-Sync)                                                                         |
| A15 | Hochladen: was             | **Inhalte**: neue und geänderte Begriffe (Arbeitsstand mit Übersetzungen, Suchbegriffen, Beziehungen, Kategorien und Herkunft, Recherche), neue und geänderte Kategorien und Herkünfte (mit Reihenfolge und Farbe). Keine Konten, Organisationen, Vorschläge, Einstellungen oder Protokolle                                                                                                                                                      |
| A16 | Hochladen: wie             | «Bereit zum Hochladen» je Gegenstelle: Vergleich der Prüfsummen je Begriff bzw. Listeneintrag (`GET /api/v1/sync/state`), Auswahl mit Kontrollkästchen, Senden an `POST /api/v1/sync/upload` (Scope `glossary:sync.write`). Beim Hochladen wählbar **«als Entwurf» (Vorgabe)** oder **«direkt veröffentlichen»** – nur, wenn das Servicekonto zusätzlich `glossary:sync.publish` hat                                                             |
| A17 | Konflikte                  | **Eine Logik für alle Paare.** Die Instanz merkt sich je Gegenstelle die Prüfsummen beim letzten Holen bzw. Hochladen. Hat sich auf der Gegenstelle seither etwas geändert, verlangt sie **zuerst «Holen»** und lädt nichts hoch. Zusätzlich prüft die Gegenstelle je Begriff die Basisrevision; passt sie nicht, lehnt sie den Begriff ab (409, «Konflikt»), nichts wird überschrieben                                                          |
| A18 | Kennungen                  | UUID bleibt in beiden Richtungen gleich; der Permalink-Code wird mitgegeben und auf der Gegenstelle übernommen, wenn er dort frei ist (sonst neuer Code, Hinweis in der Antwort); der Slug wird dort wie üblich eindeutig gemacht                                                                                                                                                                                                                |
| A19 | Richtung                   | Hochladen nur **`development` → `test`/`master`** und **`test` → `master`**. `master` lädt nie hoch; `development` und `instance` nehmen keine Uploads an. Beide Seiten prüfen ihre Rolle                                                                                                                                                                                                                                                        |
| A20 | Gegenstellen               | Administration › System › **Sync** (Recht `system`): Name, Adresse und Token des Servicekontos der Gegenstelle; der Token wird verschlüsselt gespeichert und nie wieder angezeigt. Auf der Gegenstelle legt man dafür ein Servicekonto mit den Sync-Scopes an (Token nur einmal sichtbar)                                                                                                                                                        |
| A21 | Holen auf test (ZU PRÜFEN) | erlaubt, mit Warnung und ausdrücklicher Bestätigung («ersetzt den Stand der Beta-Prüfung»); keine Sperre während eines Beta-Tests                                                                                                                                                                                                                                                                                                                |
| A22 | Protokoll und Limits       | jedes Holen und Hochladen im Sicherheitsprotokoll (wer, Gegenstelle, Anzahl) und im Protokoll des Servicekontos der Gegenstelle; Abruflimit der Sync-Endpunkte 10 je Minute; Dump höchstens 100 MB                                                                                                                                                                                                                                               |

Sicherheit: Sync-Endpunkte nur mit Servicekonto-Token und eigenem Scope; Scopes `glossary:sync.read` (Recht `system`),
`glossary:sync.write` (Recht `edit-terms`) und `glossary:sync.publish` (Recht `publish`) der anlegenden Person. Der
Dump enthält nie Passwörter, zweite Faktoren, Tokens oder Einstellungen. Abrufe nur über HTTPS (lokal auch http),
ohne Weiterleitungen, mit Zeit- und Grössenlimit.

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
- Entwicklungs-Sync: «Holen» übernimmt alles ausser Konten, Personendaten anonymisiert; Konten, Einstellungen,
  Servicekonten und Gegenstellen bleiben.
- Hochladen: neuer und geänderter Begriff landen auf der Gegenstelle als Entwurf; geänderter Begriff mit neuerer
  Revision auf der Gegenstelle ergibt «Konflikt»; geändertes Ziel verlangt zuerst «Holen».
- `development` lässt sich in production nicht einstellen; `master` nimmt keine Uploads von sich selbst an und lädt
  nie hoch.

## 7. Issues

| Issue | Inhalt                                                                       |
| ----- | ---------------------------------------------------------------------------- |
| #180  | Abgleich mit dem Master, Auswahl, Ausblenden und Verwaltung (dieses Konzept) |
| #478  | Entwicklungs-Sync dev ↔ test ↔ prod (Abschnitt 4a)                           |

## 8. Verlauf

- 21.11.2026: Abschnitt 4a für alle Paare dev/test/prod erweitert (#478, #381 zusammengeführt), Rolle `test`,
  Entscheide A12–A22 festgelegt (Auftrag «nach bestem Ermessen entscheiden», zur Abnahme vorgelegt).
- 14.11.2026: Entwurf mit Vorschlag DEC-041 nach Auftrag im Chat; ergänzt: Reihenfolge und Labelfarben abgleichen; Entwicklungs-Sync dev ↔ Master in beide Richtungen (A12–A18).
