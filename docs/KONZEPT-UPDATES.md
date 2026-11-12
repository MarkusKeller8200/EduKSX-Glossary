# Konzept: Selbst hosten und Updates (DEC-027, Entwurf)

Stand: Entwurf 08.11.2026, offene Fragen entschieden 12.11.2026, zur Abnahme. Umsetzung erst nach Freigabe (Meilenstein «0.10.0 – Selbst hosten und
Updates»).

## Ziel

Schulen und Gemeinden können das Glossary selbst betreiben und neue Versionen einfach einspielen: auf Knopfdruck in
der Administration oder automatisch, sobald ein Release erscheint – mit Sicherung und Rücksprung, ohne SSH und ohne
selbst zu bauen.

## Ausgangslage

- Heute baut GitHub Actions bei jeder Änderung auf `main` den Code direkt auf unserem Server (`docker compose up
  --build` per SSH). Für fremde Server ist das ungeeignet: Zugang per SSH, Bauen auf dem Server, kein Versionsstand.
- Der Container sichert beim Start die Datenbank (`vor-update-*.sqlite`, letzte 10) und führt die Migrationen aus.
  Daten, Sicherungen und Schlüssel liegen im Volume `storage/`.

## Grundsätze

| Nr. | Vorschlag                                                                                                                                                                                                                                                                                                                              |
| --- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| U1  | **Fertige Images statt Bauen auf dem Server.** Jedes Release erzeugt ein Docker-Image in der GitHub Container Registry (`ghcr.io/markuskeller8200/eduksx-glossary`) mit den Tags `0.10.0`, `0.10`, `latest`; Code, PHP-Pakete und gebaute Oberfläche sind enthalten. Auf dem Server wird nichts gebaut und nichts nachgeladen.         |
| U2  | **Herkunft prüfbar.** Images signiert (Sigstore/cosign, Herkunftsnachweis aus GitHub Actions); aktualisiert wird nur auf signierte Images aus der offiziellen Registry, mit festem Digest.                                                                                                                                             |
| U3  | **Der Container ändert sich nicht selbst.** Die App schreibt keinen eigenen Code und hat keinen Zugriff auf Docker. Ein kleiner **Update-Dienst** (eigener Container neben der App) führt Updates aus: neues Image holen, Sicherung, App ersetzen, Prüfung, notfalls zurück.                                                           |
| U4  | **Update aus der Administration.** Seite «System → Updates» (Recht `system`, Developer bzw. Betreiber, zweiter Faktor): installierte und verfügbare Version, Änderungen (Changelog), Knopf «Jetzt aktualisieren». Die App legt nur einen Auftrag ab; der Update-Dienst führt ihn aus und meldet den Stand zurück.                      |
| U5  | **Automatisch nach Richtlinie.** Einstellung: aus / nur Korrekturen (PATCH, Vorgabe) / auch neue Funktionen (MINOR); nie automatisch über eine Hauptversion (MAJOR). Optional Zeitfenster (z. B. nachts).                                                                                                                              |
| U6  | **Sicherung bleibt Pflicht.** Auch wenn «nur der Code» wechselt: Migrationen können die Datenbank ändern. Vor jedem Update eine konsistente Sicherung (wie heute), erst danach starten. Sie kostet Sekunden und ist die Voraussetzung für den Rücksprung.                                                                              |
| U7  | **Prüfung und Rücksprung.** Nach dem Start Gesundheitsprüfung (`/up`, Migrationen ok, Version stimmt). Schlägt sie fehl: altes Image wieder starten und die Sicherung von unmittelbar davor einspielen; Meldung in der Administration und per Mail.                                                                                    |
| U8  | **Hinweis auf neue Versionen** auch ohne Update-Dienst: Die App fragt höchstens einmal täglich die öffentlichen Releases ab (nur Versionsnummer, keine Daten der Instanz) und zeigt «Neue Version verfügbar». Abschaltbar.                                                                                                             |
| U9  | **Protokoll.** Jeder Auftrag (wer, wann, von → nach, Ergebnis, Dauer) im Audit-Protokoll und unter «System → Updates».                                                                                                                                                                                                                 |
| U10 | **Regeln für Releases.** Semantische Versionen; Migrationen nur vorwärts und ohne manuelle Schritte; Brüche nur in Hauptversionen und im Changelog markiert; jede Version mindestens einmal auf unserem Server gelaufen, bevor sie `latest` wird.                                                                                      |
| U11 | **Inhalte getrennt von Software.** Selbst gehostete Instanzen können die gemeinsamen Begriffe aus dem zentralen Glossary abgleichen (Vollstand und Change-Feed der API, geplant z. B. stündlich). Organisation, Meine Darstellung und Konten bleiben lokal.                                                                            |
| U12 | **Variante ohne Docker (später, optional).** Release-Paket (ZIP mit `vendor/` und gebauter Oberfläche, Prüfsumme und Signatur). Updater wie bei Envoyer/Deployer: neues Verzeichnis entpacken, Sicherung, Migrationen, Umschalten per Symlink, alter Stand bleibt für den Rücksprung. Nur, wenn Schulen Webhosting ohne Docker nutzen. |

## Ablauf eines Updates

```text
Administration                App (web)                   Update-Dienst                 Registry
     │  «Jetzt aktualisieren»     │                              │                            │
     ├───────────────────────────►│ Auftrag in storage/updates/  │                            │
     │                            │ (Zielversion, Digest, wer)   │                            │
     │                            │                              │◄── Auftrag gefunden ──     │
     │                            │                              ├── Image holen, Signatur ──►│
     │                            │                              │   prüfen                    │
     │                            │◄── Wartungsmodus, Sicherung ─┤                            │
     │                            │                              ├── App-Container ersetzen   │
     │                            │◄── Start: Migrationen ───────┤                            │
     │                            │                              ├── Prüfung /up              │
     │                            │                              │   ok → Stand «fertig»       │
     │                            │                              │   Fehler → altes Image +    │
     │                            │                              │   Sicherung zurück          │
     │◄── Stand und Protokoll ────┤◄── Ergebnis in storage/ ─────┤                            │
```

- **Kommunikation** nur über Dateien im gemeinsamen Volume (`storage/updates/`): Auftrag, Stand, Protokoll. Kein
  Netzwerkzugang vom Update-Dienst zur App nötig, keine offenen Ports.
- **Rechte:** Nur der Update-Dienst hat Zugriff auf Docker (Socket bzw. eingeschränkter Proxy, der nur `pull`,
  `create`, `start`, `stop` für die eigenen Container erlaubt). Die Web-App bleibt ohne diese Rechte – ein Fehler in
  der App kann den Server nicht übernehmen.
- **Ohne Update-Dienst** (z. B. wer selbst verwaltet): Die Administration zeigt die neue Version und den Befehl
  `docker compose pull && docker compose up -d`.

## Warum nicht «neuen Code in den laufenden Container laden»?

- Container sind unveränderlich: Änderungen im Container gehen beim nächsten Neustart verloren.
- Die App bräuchte Schreibrechte auf ihren eigenen Code und für PHP-Pakete Composer im Betrieb – ein Einfallstor:
  Wer die App angreift, könnte dauerhaft fremden Code einschleusen.
- Ein halb eingespieltes Update (Code neu, Pakete alt) lässt sich schwer zurücknehmen. Ein ganzes Image wird
  atomar gewechselt und ist mit einem Schritt wieder das alte.

## Betrieb bei uns

Unser Server folgt demselben Weg: `main` wird wie bisher geprüft und ausgeliefert (Vorschau), ein Release erzeugt
das signierte Image. So läuft jede Version zuerst bei uns, bevor Schulen sie automatisch erhalten (U10).

## Viele Instanzen: einfach, schnell, kurze Ausfallzeit (Chat 08.11.2026)

Annahme: später rund 200 Kunden. Ziel: Updates für alle in Stunden statt Tagen, ohne Handarbeit je Kunde, mit
Ausfallzeiten im Sekundenbereich.

**Zuerst die Grundsatzfrage – braucht jeder Kunde eine eigene Instanz?** Der grösste Hebel ist, die meisten Kunden
im **zentralen Glossary als Organisation** zu führen (DEC-025): ein Update gilt dann für alle auf einmal, ohne
Aufwand je Kunde. Eigene Instanzen nur für Kunden, die sie wirklich brauchen (eigene Daten im eigenen Netz,
Vorgaben der Gemeinde). Für diese gilt:

| Nr. | Vorschlag                                                                                                                                                                                                                                                                                                                                                                          |
| --- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| U13 | **Alle gleich.** Eine Image-Version für alle, Unterschiede nur über `.env` und Einstellungen in der Datenbank – kein kundenspezifischer Code. Eine compose-Datei, ein Update-Dienst.                                                                                                                                                                                               |
| U14 | **Leitstand.** Zentrale Übersicht aller Instanzen: Kunde, Version, Zustand, letztes Update, Wartungsfenster, Ring. Die Instanzen melden sich **nur ausgehend** (Heartbeat alle 15 Minuten: Version, Zustand, Update-Ergebnis – keine Inhalte, keine Personendaten) und holen Aufträge ab. Kein Zugriff von aussen ins Netz der Schule, keine offenen Ports, freiwillig vereinbart. |
| U15 | **Update-Ringe.** Jedes Release geht zuerst auf unseren Server (Ring 0), dann auf wenige Pilotinstanzen (Ring 1, z. B. 5 %), nach einer Beobachtungszeit auf alle (Ring 2). Schlägt die Prüfung in einem Ring fehl oder springen Instanzen zurück, stoppt die Verteilung automatisch.                                                                                              |
| U16 | **Wartungsfenster und Massenauftrag.** Je Kunde ein Fenster (Vorgabe: nachts); der Leitstand plant das Update für eine Gruppe oder alle; jede Instanz führt es in ihrem Fenster aus und meldet das Ergebnis. Notfall-Updates (Sicherheit) mit kürzerer Frist.                                                                                                                      |
| U17 | **Fast ohne Ausfall (blau/grün).** Neue Version neben der alten starten, Image vorher geladen, Gesundheitsprüfung, dann leitet Traefik den Verkehr um und die alte Version stoppt. Lesen bleibt durchgehend möglich; nur während einer Migration, die schreibt, kurz Schreibsperre (Hinweis «Wartung, gleich zurück»). Ziel: Unterbruch unter 5 Sekunden.                          |
| U18 | **Abwärtskompatible Migrationen.** Damit alte und neue Version kurz gleichzeitig mit derselben Datenbank laufen: Änderungen in zwei Schritten über zwei Versionen (erst erweitern, später aufräumen); nie Spalten in derselben Version umbenennen oder löschen, in der sie noch gelesen werden. Die CI prüft das.                                                                  |
| U19 | **Beobachten statt nachfragen.** Zustand, Fehlerraten und Versionen im Leitstand; Meldung an uns, wenn eine Instanz nicht mehr meldet oder zurückgesprungen ist. Betreiber vor Ort erhalten dieselben Meldungen per Mail.                                                                                                                                                          |

**Grössenordnung:** Mit vorab geladenen Images dauert ein Update je Instanz rund eine Minute, der sichtbare
Unterbruch wenige Sekunden. 200 Instanzen in zwei Ringen sind in einer Nacht aktualisiert – ohne Handarbeit.

## Entscheide zu den offenen Fragen (12.11.2026)

1. **Wer darf aktualisieren:** nur mit dem Recht `system`. Das Recht wird nicht automatisch vergeben, sondern
   beantragt; die Freigabe kann zusätzlich an einen Schlüssel gebunden werden (Ausgestaltung mit #177).
2. **Automatische Updates:** Vorgabe «nur Korrekturen» (PATCH); in den Einstellungen abschaltbar.
3. **Ausgelieferte Stände:** test und prod sowie selbst gehostete Instanzen erhalten nur Releases; jeder Merge
   geht nur auf dev (Hostinger, DEC-028).
4. **Variante ohne Docker (U12):** vorerst nicht umsetzen; nur Docker wird unterstützt. #181 bleibt zurückgestellt
   und wird erst bei einer konkreten Anfrage einer Schule geprüft (Empfehlung, Bestätigung ausstehend).
5. **Abgleich der Inhalte (U11):** wählbar je Instanz – automatisch übernehmen oder manuell. Manuell zeigt eine
   Liste der Änderungen mit Kontrollkästchen; einzelne, mehrere oder alle lassen sich auf einmal übernehmen.
6. **Viele Kunden:** zuerst zentral mit Organisationen. Eigene Instanzen werden vorbereitet, aber nur auf
   ausdrücklichen Wunsch ausgeliefert (Stand heute).
7. **Leitstand (U14):** eigene Anwendung, nicht Teil des Glossary; Zugriff nur mit Recht `system` (bzw. dem
   entsprechenden Recht der Plattform).

## Issues (Meilenstein «0.10.0 – Selbst hosten und Updates»)

| Issue | Inhalt                                                                              |
| ----- | ----------------------------------------------------------------------------------- |
| #174  | Konzept (dieses Dokument)                                                           |
| #175  | Docker-Image je Release, signiert (U1, U2, U10)                                     |
| #176  | Hinweis auf neue Versionen (U8)                                                     |
| #177  | Update aus der Administration mit Update-Dienst und Rücksprung (U3, U4, U6, U7, U9) |
| #178  | Automatische Updates nach Richtlinie (U5)                                           |
| #179  | Anleitung «Selbst hosten»                                                           |
| #180  | Abgleich der Inhalte mit dem zentralen Glossary (U11)                               |
| #181  | Variante ohne Docker (U12, optional)                                                |

Deployment und Installationsarten (eigener VPS, Schulnetz, verteilt, Infomaniak, Anbindung an die Plattform): [KONZEPT-DEPLOYMENT.md](KONZEPT-DEPLOYMENT.md), #194–#202.
