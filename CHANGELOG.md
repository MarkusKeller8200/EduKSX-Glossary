# Changelog – EduKSX-Glossary

Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/), Versionen nach
[Semantic Versioning](https://semver.org/lang/de/) (MAJOR.MINOR.PATCH). Neue Einträge kommen laufend unter
**Unveröffentlicht**; `python3 .github/blueprint/release.py set X.Y.Z --commit` macht daraus eine Version.

## [Unveröffentlicht]

### Neu

- **Passwort-Link (#147):** In der Benutzerverwaltung kann ein Admin einer Person einen Link zum Zurücksetzen des
  Passworts senden (ohne das Passwort zu sehen, protokolliert); auch für Konten, die sich bisher nur mit Microsoft
  anmelden.

### Geändert

- **Kopfzeile (#146):** auf Website und Administration gleich – Titel, Ansicht, Farbschema, angemeldete Person mit
  Rolle bzw. «Gast», Anmelden/Abmelden.
- **Ansicht (#142, #144):** Mobil, Darstellungsoptimiert, Breit und Voll als Symbole; «Voll» und «System» mit eigenen
  Symbolen. Formulare und Detailseiten bleiben bei «Breit» und «Voll» optimiert breit und zentriert.
- **Filter (#145):** Kategorie und Herkunft als farbige Labels in der Listenfarbe, Mehrfachauswahl, Zurücksetzen –
  auf der Startseite und in der Begriffstabelle.
- **Bedienung (#139):** Bestätigungen als Dialog statt Browser-Fenster, Meldungen auf der Website einheitlich oben,
  erste Spalte der Vergleichstabellen fixiert; Prüfprotokoll `docs/BARRIEREFREIHEIT.md`.
- **Begriffstabelle (#143):** Darstellung (Tabelle/Kacheln), Gruppierung und Sortierung in einem Menü «Ansicht» mit
  «Ansicht zurücksetzen».

## [0.7.0] – 2026-11-08

### Neu

- **Hilfe (#135):** Seite «Hilfe» mit allen Feldern eines Begriffs – Bedeutung, Format und Beispiel, Pflicht,
  Sichtbarkeit (öffentlich, Suche, intern), Excel-Spalte und API-Name; Link in der Fusszeile.

- **Export-Menü (#131):** Auf der Startseite und bei den Begriffen der Administration ein Knopf «Export» wie im
  Intranet: Excel und CSV (sichtbare Spalten), Excel vollständig, PDF und Markdown – jeweils für die sichtbaren
  Zeilen in angezeigter Reihenfolge. Website: veröffentlichte Daten ab Rolle Benutzer; ohne Recht ist der Knopf
  ausgegraut mit Hinweis zur Anmeldung. Administration: Arbeitsstand.

- **Tabellen (#103, #108):** Alle Listen der Administration (Begriffe, Benutzer, Listen, Prüfung, Altwerte,
  Import-Vorschau, Vorschläge, Servicekonten) mit verstellbaren Spaltenbreiten (Splitter, auch mit Pfeiltasten) und
  Sortierung per Klick auf den Spaltenkopf; Breiten und Sortierung bleiben im Browser. Beim horizontalen Scrollen
  bleiben Schlüsselspalte links und Aktionen rechts sichtbar; Aktionen als Symbole mit Tooltip.

- **Listen per Excel (#101):** Kategorien und Herkunft je Liste exportieren und mit Vorschau importieren (neu,
  geändert, Fehler; nie löschen). Spalten wie das Formular, Format wie der Listen-Export des Intranets
  (sek-intranet#157); Zuordnung über Schlüssel, sonst Kürzel oder Label.
- **Allgemeine Einstellungen (#106):** neue Seite für Löschfristen, Wiedervorlage der Quellenprüfung,
  Vier-Augen-Prinzip bei der Freigabe (DEC-022, Vorgabe aus) sowie Hinweise und Links; neues Recht «Allgemeine
  Einstellungen» (systemkritisch), jede Änderung im Protokoll.

- **Vorschläge und Moderation (#113, DEC-024):** Ab Benutzer neuen Begriff oder Korrektur einreichen («Begriff
  vorschlagen», «Korrektur vorschlagen»); Stand, Rückfragen und Zurückziehen unter «Meine Vorschläge». Die Moderation
  vergleicht feldweise Basis, Arbeitsstand und Vorschlag (Konflikte markiert), nimmt gewählte Felder als Entwurf an,
  lehnt mit Begründung ab oder fragt nach. Servicekonten reichen per `POST /api/v1/proposals` ein. Nie öffentlich;
  Personenbezug 24 Monate nach Abschluss bzw. bei Kontolöschung entfernt. GitHub nur noch für Software-Meldungen.

- **Revisionen wiederherstellen (DEC-022, #95):** Im Verlauf eine frühere Revision als neue Revision wiederherstellen,
  wahlweise gleich veröffentlichen (fehlerhafte Freigabe rückgängig machen); «Entwurf verwerfen» stellt den
  veröffentlichten Stand wieder her. Als Entwurf gilt nur noch eine inhaltliche Abweichung vom öffentlichen Stand.
- **Rollen Gast, Benutzer, Organisations-Admin (#110):** neue Rechte «Exportieren», «Persönliche Darstellung» und
  «Servicekonten und Token». Vorschläge einreichen kommt aus der Rolle statt fest für jedes Konto; neue Konten und
  bisherige Konten ohne Rolle sind Benutzer.
- **Persönliche Darstellung (#114):** nur noch mit dem Recht «Persönliche Darstellung» (ab Benutzer); Gäste sehen die
  gemeinsame Form, gespeicherte Anpassungen bleiben erhalten.
- **Export für Benutzer (#111):** Excel und CSV der veröffentlichten Begriffe unter «Mein Konto» und auf der
  Startseite (Recht «Exportieren», ohne Entwürfe und Notizen). Die Redaktion exportiert den Arbeitsstand weiterhin in
  der Administration; der Import ist ein eigenes Recht.
- **PDF-Export (#112):** wie im Intranet über die Druckansicht des Browsers – A4 hoch/quer, Umfang kompakt bis
  vollständig, gruppiert, farbige Labels, Link-Spalte, Auswahl alle/gefiltert/ausgewählt, mit Lizenzhinweis
  CC BY 4.0. Auf der Website (veröffentlicht, ab Benutzer) und in der Redaktion (Arbeitsstand mit Notiz).
- **QR-Code für den zweiten Faktor (#70):** beim Einrichten scannen statt den Schlüssel abtippen (Paket `qrcode`, MIT).
- **Servicekonten mit Token (#47):** ab Organisations-Admin unter «Servicekonten»: Name, Zweck, verantwortliche Person,
  Scopes; Token einmal angezeigt, nur als Hash gespeichert, höchstens ein Jahr gültig, widerrufbar, Konto sperrbar;
  eigenes Protokoll. Kein Login. Prüfung über `GET /api/v1/service/whoami`; Vorschläge per Token folgen mit #113.
- **Hierarchie der Rollen (#115):** «Rollen und Rechte» zeigt die Stufen Gast bis Developer mit den Rechten, die jede
  Stufe dazubekommt, farbig nach Schutzklasse.

### Geändert

- **Seitenbreite (#127):** Versionen, Datenschutz und Rechtliches nutzen die ganze Seitenbreite wie die übrigen Seiten.
- **Tabellen (#128):** Spaltenbreiten richten sich nach dem Inhalt und werden nach Grössenänderungen sowie bei
  geändertem Inhalt (Suche, Filter, Gruppen) neu berechnet; Doppelklick auf den Splitter (oder Enter) passt eine von
  Hand gezogene Spalte wieder an.

- **Fusszeile (#125):** kürzere Links «Fehler melden» und «GitHub Pages».

- **Farbwahl (#107):** Farben in «Listen» als farbige Labels wählbar, neu mit den CI-Farben Violett, Orange und Blau.
- **Fusszeile (#102):** auch in der Administration immer sichtbar.
- **Kategorien (#105):** werden überall untereinander angezeigt.
- **Begriffstabelle (#104):** gruppieren zusätzlich nach Erstelldatum und letzter Änderung.
- **Seitenbreite (#99, DEC-023):** Website und Administration einheitlich 1152 px; lange Texte auf Lesebreite.

### Behoben

- **Korrektur vorschlagen (#133):** Die Seite bricht bei älteren Begriffen (Abbild vor DEC-020) nicht mehr ab.

- **Tabellenbreite (#123):** Tabellen der Administration füllen bei «Ganze Breite» den ganzen Platz; die breiteste
  Textspalte nimmt den freien Platz auf.

- **Auswahllisten im Dunkelmodus (#107):** Einträge sind lesbar (heller Text auf dunklem Grund).

## [0.6.0] – 2026-11-07

### Neu

- **CSV-Export (#71):** Begriffe zusätzlich als CSV (UTF-8, Semikolon); Formeln werden als Text geschützt.
- **Öffentlicher Verlauf (#68):** Die Detailseite zeigt die veröffentlichten Revisionen mit Datum, ohne Personen.
- **Quellenprüfung (#69):** Prüfdatum und Belegstatus je Herkunft; Seite «Prüfung» mit jährlicher Wiedervorlage.
- **Import-Protokoll (#64):** Jeder übernommene Import mit Datei, Person, Zeitpunkt und Ergebnis.

- **Softwarelizenz (#65, DEC-021):** Der eigene Code steht unter der MIT-Lizenz (Datei `LICENSE`, Hinweis auf
  `/legals`).

- **Farbige Labels (#77, #91, #92):** Kategorien und Herkunft in der Farbe, die unter «Listen» gewählt wird (Palette
  aus dem Intranet, hell und dunkel mit Kontrast AA). Status der Begriffe als Label (aktuell grün, zu prüfen orange,
  historisch grau), Veröffentlichung als «V1», «V2» … und «Entwurf V…», inaktiv und zusammengeführt eigens markiert.
- **Redaktionstabelle (#67):** Begriffe nach Kategorie, Status oder Veröffentlichung gruppieren; Abkürzung,
  Bezeichnung und Kurzbeschreibung direkt in der Zelle ändern (neue Revision, offizielle Felder nur mit Recht).
- **Ansicht wählen (#76):** Neben Hell/Dunkel die Ansicht Mobil, Desktop schmal oder ganze Breite – für Website und
  Administration, gespeichert im Browser.
- **Cookie-Hinweis (#78):** beim ersten Besuch, mit Liste der Cookies in der Datenschutzerklärung.
- **API (#62):** `updated_at` je Begriff (Zeitpunkt der veröffentlichten Revision).
- **Lizenzbericht (#73):** Die CI listet alle Pakete mit Lizenz und warnt bei bedenklichen Lizenzen; jedes Release
  erhält den Bericht als Anhang.
- **Kontrastprüfung (#63):** Ein Test prüft die Farbwerte hell und dunkel; die PR-Vorlage enthält die
  Gestaltungspunkte des CI-Manuals.

### Geändert

- **Microsoft-Anmeldung (#61):** einmaliger nonce je Anmeldung, im id_token geprüft.
- **Log-Rotation (#60):** Docker rotiert die Logs je Dienst; Frist 14 Tage über den Host (DEPLOY.md).

- **Administration (#89, #90):** Kopf in zwei Zeilen – Titel, Person und Abmelden oben, alle Menüpunkte darunter;
  Beschreibungen unter den Seitentiteln in voller Breite.

### Behoben

- **Verknüpfung lösen (#59):** Das Passwort wird in einem Passwortfeld abgefragt statt im Klartext-Dialog.

## [0.5.0] – 2026-11-07

### Geändert

- **Gemeinsame und persönliche Daten (DEC-020, #80–#83):** Begriffe enthalten nur noch gemeinsame Fachdaten.
  «Abkürzung eigene», «Bezeichnung eigene», «Alias eigene» und «Intern genutzt in» entfallen; vorhandene Werte
  werden bei der Migration vollständig als **Altwerte** gesichert und auf der neuen Seite «Altwerte» von der
  Redaktion zugeordnet (offiziell, alternative Schreibweise, Suchbegriff) oder verworfen – nichts geschieht
  automatisch. Bestand: `php artisan glossary:bestand-eigene`. Neu **alternative Schreibweisen** (mehrere,
  öffentlich).
- **API v1 (Vorschau):** `own_abbreviation` und `own_name` entfallen; `abbreviation`/`name` sind die gemeinsamen
  Werte. Neu `official_aliases`, `alternative_aliases`, `display_label`, `is_full_term`, in Liste, Einzelbegriff und
  Vollstand zusätzlich `is_ambiguous` und `meaning_count`.
- **API v1 ergänzt (#87):** Suchbegriffe (`search_terms`) im Begriff; Kategorien und Herkunft mit Kürzel,
  Beschreibung und Farbe.
- **Excel-Format Version 2:** Spalte «Alternative Schreibweisen» statt der eigenen Spalten. Dateien im alten
  Intranet-Format werden erkannt; die alten Spalten werden gemeldet und als Altwerte gesichert.

### Neu

- **Meine Darstellung:** Angemeldete Konten legen auf der Begriffsseite eine eigene Abkürzung, Bezeichnung,
  Beschreibung und Schreibweisen fest – nur für sich sichtbar, nie öffentlich, mit dem Konto gelöscht.
- **Änderungsjournal:** Der Verlauf zeigt je Revision die Felder mit altem und neuem Wert, Rolle und Quelle
  (Excel, Altwerte, Zusammenführung). Beim Löschen eines Kontos bleibt der Verlauf, der Name wird ersetzt.

## [0.4.0] – 2026-11-07

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

- **Keine fremden Server auf öffentlichen Seiten (#44):** Die API-Doku (Scalar) wird selbst ausgeliefert statt von
  jsDelivr geladen; die Schrift Inter wird beim Build eingebunden. Datenschutzerklärung und Rechtliches nachgeführt.

- **Anmelden mit Microsoft (#45):** Knopf «Mit Microsoft anmelden» für Geschäfts-, Schul- und private
  Microsoft-Konten (OpenID Connect mit PKCE, geprüftes id_token). Neue Konten haben nur Community-Rechte. Eine
  gleiche E-Mail verknüpft nie automatisch: Bestehende Konten verknüpfen Microsoft unter «Mein Konto» nach
  Passwortbestätigung. Mit eingerichtetem zweitem Faktor folgt die Code-Abfrage. Aktiv, sobald `MICROSOFT_CLIENT_ID`
  und `MICROSOFT_CLIENT_SECRET` gesetzt sind.

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
