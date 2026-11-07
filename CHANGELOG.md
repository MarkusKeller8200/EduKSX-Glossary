# Changelog – EduKSX-Glossary

Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/), Versionen nach
[Semantic Versioning](https://semver.org/lang/de/) (MAJOR.MINOR.PATCH). Neue Einträge kommen laufend unter
**Unveröffentlicht**; `python3 .github/blueprint/release.py set X.Y.Z --commit` macht daraus eine Version.

## [Unveröffentlicht]

### Neu

- **Rollen und Rechte (#36):** Rollen Moderator, Admin und Developer mit einstellbaren Rechten (Vorschläge
  einreichen, prüfen, Begriffe bearbeiten, veröffentlichen, offizielle Felder, Benutzer verwalten, Import, Listen,
  System). Jede Aktion der Administration prüft ihr Recht; Navigation und Knöpfe zeigen nur, was erlaubt ist.
  Moderatoren bearbeiten Begriffe, veröffentlichen aber nicht. Schutzregeln: niemand erhöht eigene Rechte,
  Developer nur per `php artisan glossary:developer E-MAIL`, das letzte Developer-Konto und die letzte
  Verwaltungsberechtigung bleiben erhalten. Jede Änderung steht im Protokoll.

- **Anmeldung gehärtet (#37):** Privilegierte Rechte gelten nur mit bestätigtem zweitem Faktor, auch wenn die Rolle
  sie hat. Gesperrte Konten verlieren den Zugang sofort, auch in offenen Sitzungen; Entsperren im Notfall mit
  `php artisan glossary:admin E-MAIL --entsperren`. Rollenänderungen wirken ohne neue Anmeldung. Einrichten,
  Zurücksetzen und Wiederherstellungscodes des zweiten Faktors stehen im Protokoll. Konten ohne Verwaltungsrechte
  landen nach der Anmeldung auf der Startseite. Sitzungs-Cookie im Betrieb nur über HTTPS.

- **Veröffentlichungsgrenze (#43):** Öffentliche Ausgaben (Website, API, Vollstand, Change-Feed, GitHub Pages)
  bauen nur aus einer Positivliste von Feldern. Ein automatischer Test prüft, dass Notizen, «Intern genutzt in»,
  Entwürfe, unveröffentlichte Begriffe und Kontodaten nirgends erscheinen.

- **Benutzer und Rollen verwalten (#38):** Neue Seiten «Benutzer» (Liste mit Suche und Filter, Konto anlegen,
  Detail mit Rollen, Sperren, zweiten Faktor zurücksetzen und Protokoll) und «Rollen und Rechte» (Rechte je Rolle mit
  Schutzklassen, eigene Rollen wie «Redaktion Schule» anlegen). Was ausserhalb der eigenen Befugnis liegt, ist
  ausgegraut und wird serverseitig abgewiesen.

- **Mein Konto (#39):** Seite `/konto` für alle angemeldeten Konten: Vor- und Nachname, freiwillig Community-Name
  (öffentlich nur bei Vorschlägen), Funktion, Art der Einrichtung und Gemeinde/Schule; Passwort ändern (andere
  Sitzungen werden abgemeldet), zweiter Faktor (für Community freiwillig) und Konto löschen. Gespeichert wird von
  der letzten Anmeldung nur das Datum.

- **E-Mail-Versand (#40):** an keinen Anbieter gebunden (Microsoft 365, Google, SMTP von Infomaniak/Hostinger oder
  Versanddienste), eingestellt nur in der Umgebung des Servers (Anleitung in DEPLOY.md). Neue Seite «System» für
  Developer mit Stand des Versands und Testmail. Mails laufen im Betrieb über eine Warteschlange mit Wiederholung;
  dafür kommen die Dienste `worker` und `scheduler` dazu.

- **Konto anlegen und Passwort vergessen (#41):** Selbstregistrierung mit Bestätigungslink per E-Mail (60 Minuten,
  Double-Opt-in), Spam-Schutz mit Cloudflare Turnstile (wenn eingerichtet) und Kenntnisnahme der
  Datenschutzerklärung (Version und Zeitpunkt gespeichert). Neue Konten haben nur Community-Rechte; unbestätigte
  werden nach 7 Tagen gelöscht (`glossary:loeschfristen`, täglich). «Passwort vergessen» schickt einen einmaligen
  Link; die Antworten verraten nicht, ob eine Adresse ein Konto hat. Beides nur mit eingerichtetem Mailversand.

- **Datenschutz (#42):** Seite `/datenschutz` mit Version und Hinweis «Entwurf» (Link im Fuss). Angemeldete Konten
  nehmen jede neue Version einmal zur Kenntnis (gespeichert mit Zeitpunkt, getrennt von Einwilligungen).
  Löschkonzept in `docs/LOESCHKONZEPT.md`; `glossary:loeschfristen` löscht täglich unbestätigte Registrierungen,
  inaktive Community-Konten nach 24 Monaten (Hinweis per Mail 30 Tage vorher) und Protokolleinträge nach der Frist.

### Geändert

- Bestehende Admin-Konten haben jetzt die Rolle Admin (nicht Developer); `glossary:admin` vergibt Admin.
- Der zweite Faktor wird unter «Mein Konto» eingerichtet (`/konto/zwei-faktor` statt `/admin/zwei-faktor`).
  Bisherige Namen wurden in Vor- und Nachname aufgeteilt (letztes Wort = Nachname).

## [0.3.0] – 2026-11-07

### Neu

- **Nutzung und Stabilität der API (#30, DEC-016):** Die API ist bis zum öffentlichen Betrieb eine Vorschau
  (`stability: preview` auf `/api/v1`, Hinweis auf `/api/docs`). Danach bleibt v1 rückwärtskompatibel. Die Daten
  stehen vorerst unter CC BY 4.0 (Feld `license` in `/api/v1` und im Vollstand, Hinweis im Fuss der Website und der
  Lesefassung).
- **Sync für Abnehmer (#29):** Vollstand und Datenstand der API enthalten den `cursor` für den Change-Feed. Neuer
  Befehl `php artisan glossary:sync-check URL` synchronisiert wie ein Abnehmer (z. B. das Intranet) und vergleicht
  mit dem Vollstand. Ein automatischer Test spielt Veröffentlichen, Ändern, Zurückziehen, Wiederaufnahme und
  Zusammenführen durch. Anleitung für das Intranet in `docs/SYNC-INTRANET.md`.

## [0.2.0] – 2026-11-07

### Neu

- **Doppelte zusammenführen (#16):** neue Seite «Doppelte» in der Administration (auch über «Doppelte prüfen» bei
  den Begriffen) mit Gruppen gleicher Abkürzung oder Bezeichnung. Je Gruppe «Zusammenführen» (verbleibenden Begriff
  und Texte wählen, Kategorien, Herkunft und Aliase werden vereinigt) oder «Mehrdeutig belassen». Alte Adressen
  und IDs leiten auf den verbleibenden Begriff weiter, im Change-Feed der API als «merged».
- **GitHub Pages (#27):** reduzierte Lesefassung mit Suche, Index A–Z und Kategorienfilter; jeder Begriff verweist
  auf seine Detailseite auf glossary.eduksx.ch. Daten aus dem veröffentlichten Stand mit
  `php artisan glossary:export-pages` (schreibt `site/begriffe.json`, ohne interne Felder).
- **Zweiter Faktor (#11):** Anmeldung in der Administration mit Passwort und sechsstelligem Code aus einer
  Authenticator-App (TOTP). Pflicht: ohne eingerichteten zweiten Faktor führt jede Seite der Administration zur
  Einrichtung (Schlüssel zum Abtippen oder als Link fürs Handy). Acht Wiederherstellungscodes, jeder einmal gültig;
  Zurücksetzen auf dem Server mit `php artisan glossary:admin E-MAIL --reset-2fa`.
- **Redaktion (#14):** Begriffe in der Administration anlegen und bearbeiten (Formular mit allen Feldern, offizielle
  Felder nur mit Recht), Detailseite mit Arbeitsstand, Stand der Veröffentlichung und Verlauf (wer, wann, welche
  Felder geändert), «Veröffentlichen» und «Zurückziehen» je Begriff. Neue Seite «Listen» zum Pflegen von
  Kategorien und Herkunft (Schlüssel bleiben fest, Löschen nur ohne Verwendung).
- **Öffentliche API v1 (#25):** `/api/v1/terms` (Suche, Kategorie, Status, Seiten), `/api/v1/terms/{id-oder-slug}`,
  `/api/v1/categories`, `/api/v1/sources`, `/api/v1/releases/latest`, `/api/v1/changes?since=` (Change-Feed mit
  Cursor inkl. Rückzügen, für den Sync des Intranets) und `/api/v1/exports?format=json`. Nur veröffentlichte
  Daten, ETag/304, Fehler als problem+json, CORS für Lesezugriffe.
- **API-Doku (#26):** interaktive Seite `/api/docs` zum Ausprobieren, Beschreibung als OpenAPI 3.1 unter
  `/api/openapi.json`; Link «API» im Fuss der Website; ohne die Entwickler-Leiste von Scalar.
- **Begriffe auf der Website (#24):** Die Startseite zeigt alle veröffentlichten Begriffe mit Suche (auch in
  Aliasen und Suchbegriffen), Index A–Z und Filter nach Kategorie; jeder Begriff hat eine Detailseite
  `/begriffe/…` mit Kurzbeschreibung, Beschreibung, Kategorien, Herkunft, Quelle und Verweis auf weitere Bedeutungen
  derselben Abkürzung. Öffentlich ist immer der freigegebene Stand; redaktionelle Notizen bleiben intern.
  Administration auch über den Fuss der Website erreichbar.
- **Excel-Import und -Export (#13, #15):** gleiches Format wie der Excel-Export des Intranets (19 Spalten,
  Mehrfachwerte mit «;»). Import immer zuerst als Vorschau (neu / geändert / Fehler), übernommen wird erst mit
  «Übernehmen»; Zeilen ohne ID werden neu angelegt, eine ID aus dem Intranet bleibt erhalten, nie wird gelöscht.
  Unbekannte Kategorien und Herkünfte auf Wunsch neu anlegen. Importierte Begriffe sind Entwürfe. Export aller
  Begriffe und leere Vorlage. Neue Seite «Begriffe» in der Administration (Liste mit Filter und Stand der
  Veröffentlichung).
- **Ausgewählte veröffentlichen (#23):** in der Begriffsliste Einträge auswählen (einzeln, alle gefilterten oder
  «Alle Entwürfe auswählen») und in einem Schritt veröffentlichen – ein gemeinsamer Datenstand; Begriffe ohne
  Änderung seit der letzten Freigabe werden übersprungen.
- **Weg in die Administration:** Knopf «Anmelden» bzw. «Administration» oben auf der Website; in der Übersicht der
  Administration Knöpfe zu Excel-Import, Begriffen und Excel-Export.
- **Datenmodell (#12):** Begriffe, Kategorien, Herkunft, Schreibweisen und Revisionen in SQLite. Jede Änderung
  ergibt eine unveränderliche Revision; öffentlich ist nur die freigegebene, Änderungen danach bleiben Entwurf.
  Jede Veröffentlichung erzeugt einen neuen Datenstand mit Prüfsumme (für den Sync). Offizielle Felder nur mit
  Recht änderbar, mehrdeutige Abkürzungen erlaubt, IDs aus dem Intranet bleiben erhalten. Administration zeigt
  Anzahl Begriffe, Listen und Datenstand.
- **Startdatei `start.bat` (#20):** lokaler Start unter Windows per Doppelklick – holt zuerst den neuesten Stand
  des Branches (`start.bat BRANCH` checkt einen anderen Branch aus, `--ohne-pull` startet ohne GitHub), bricht bei
  lokalen Änderungen ab, richtet beim ersten Start alles ein und fragt nach dem Admin, sonst Pakete und Datenbank
  nachführen.

### Behoben

- **Versionen auf dem Server (#20):** `CHANGELOG.md` wurde beim Deployment (`git archive`) ausgelassen; die Seite
  «Versionen» wäre leer geblieben. Ausserdem löst eine Änderung am CHANGELOG jetzt ein Deployment aus.

### Geändert

- **Kopf und Fuss fixiert (#28):** Bei langen Begriffslisten bleiben Kopf und Fusszeile der Website sowie der Kopf
  der Administration beim Scrollen sichtbar.

## [0.1.0] – 2026-11-07

### Neu

- **Programmiergerüst (#7):** Laravel 13, Inertia 3, React 19, Tailwind 4 wie im Intranet, SQLite als Datenbank.
  Öffentliche Startseite und Seite «Versionen» (aus diesem CHANGELOG, mit Developer Release), Anmeldung lokaler
  Konten und Administration – ohne Anmeldung lassen sich keine Daten ändern; erster Admin per
  `php artisan glossary:admin`. API-Einstieg `/api/v1` mit OpenAPI 3.1. Auftritt nach CI MarKusSXCH mit Wortmarke
  EduKSX-Glossary; Bausteine aus dem Intranet übernommen. Docker-Image, Prüfung (`ci`) und Deployment auf den
  Hostinger-VPS (`deploy`, eingeschaltet mit `DEPLOY_ENABLED`).
- **Projektkonzept** (`docs/KONZEPT*.md`, Register `docs/ENTSCHEIDE.md`): aus Konzept V1.2 (ChatGPT) und dem
  Glossar des Intranets zusammengeführt – Datenmodell, API und Sync, Stack (SQLite), Auftritt nach CI MarKusSXCH,
  Versionierung, Confluence und MCP (später).
- **Konzept Hosting (#4):** `docs/KONZEPT-HOSTING.md` – zuerst Container auf dem Hostinger-VPS hinter Traefik,
  Infomaniak später prüfen; Domain `glossary.eduksx.ch` über Cloudflare; GitHub Pages bleibt als reduzierte,
  statische Lesefassung.
- **Projekt-Setup** nach my-github-blueprint: Issue-Vorlagen, Labels, Meilensteine, Release-Ablauf, statische
  Übergangsseite über GitHub Pages.
