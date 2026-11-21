# Changelog – EduKSX-Glossary

Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/), Versionen nach
[Semantic Versioning](https://semver.org/lang/de/) (MAJOR.MINOR.PATCH). Neue Einträge kommen laufend unter
**Unveröffentlicht**; `python3 .github/blueprint/release.py set X.Y.Z --commit` macht daraus eine Version.

## [Unveröffentlicht]

### Neu

- **Badge zur Recherche (#479):** Begriffe, die mit KI-Unterstützung recherchiert wurden, zeigen «KI-unterstützt
  recherchiert» auf der Begriffsseite, in der Liste, in der Einbindung und in der Browser-Erweiterung. Moderator/innen
  bestätigen mit «Als geprüft bestätigen»; danach steht «Von Mensch geprüft» mit Datum. Neue Spalte «Recherche» im
  Excel (der Import setzt höchstens «KI-unterstützt»), Filter «KI-unterstützt, noch nicht geprüft» in der Redaktion,
  API mit `research_basis` und `reviewed_at`. Einbindung 1.1.0.

## [0.17.1] – 2026-11-16

### Neu

- **Mail-Protokoll (#463):** Administration › System zeigt die Warteschlange (wartend, fehlgeschlagen, erneut versuchen
  oder verwerfen) und die zuletzt versendeten Mails – mit Zeit, Art und gekürzter Adresse, ohne Inhalt. Fehlgeschlagene
  Aufträge werden jetzt festgehalten. Einträge werden nach 90 Tagen gelöscht.
- **Einladung automatisch angenommen (#461):** Wer mit einer eingeladenen Adresse ein Konto anlegt, ist nach dem
  Bestätigen der Adresse Mitglied der Organisation; der einladende Organisations-Admin erhält einen Hinweis. Offene
  Einladungen, die so schon erledigt sind, werden auch nachträglich beim Anmelden bzw. beim Öffnen der Organisation
  bereinigt.
- **Domain der Organisation (#462):** Organisations-Admins übernehmen die Domain ihrer bestätigten Adresse (eine je
  Organisation, keine öffentlichen Mail-Anbieter) und können Konten dieser Domain automatisch aufnehmen. Die Personen
  und die Organisations-Admins erhalten eine Mail, austreten ist jederzeit möglich; die Administration kann eine Domain
  lösen.
- **Als Moderator/in bewerben (#460):** Nach der Anmeldung erscheint einmal ein Hinweis; bewerben geht unter «Mein Konto»
  mit kurzer Begründung (Voraussetzung: zweiter Faktor und bestätigte E-Mail-Adresse). Admins sehen alle Anfragen unter
  Administration › Benutzer › Anfragen, erhalten bei einer neuen Bewerbung eine Mail und nehmen an oder lehnen mit
  Begründung ab; die Person erhält eine Mail.

### Geändert

- **Hinweise wieder anzeigen (#469):** «Mein Konto» listet weggeklickte und einmalige Hinweise und zeigt sie auf Wunsch
  wieder an; «Ansichten und Cookies löschen» setzt alles zurück, was der Browser für das Glossary gemerkt hat.
- **Benutzerverwaltung (#458):** Die Liste zeigt zuerst nur aktive Konten, gesperrte über «Gesperrt» bzw. «Alle». Auf
  Wunsch sind die Konten nach Organisation gruppiert, die Gruppen anfangs eingeklappt.

### Behoben

- **Administration › Begriffe schneller (#454):** Die Liste lädt nur noch die nötigen Revisionen, misst die
  Spaltenbreiten an den ersten Zeilen und zeigt die übrigen schrittweise an.
- **Hinweis «Handy verloren?» (#456):** Die Seite «Zweiter Faktor» beschreibt den Weg über die Anmeldung bzw. die
  Administration statt eines Serverbefehls.

## [0.17.0] – 2026-11-15

### Neu

- **Organisationen in der Administration (#412, #413, #414):** Unter «Benutzer» gibt es die Reiter Benutzer und
  Organisationen. Die Liste der Konten zeigt die Organisation mit Rolle, die letzte Anmeldung und das Datum der
  Anlage. Organisationen erscheinen mit Mitgliedern, Organisations-Admins und offenen Einladungen. Die Detailseiten von
  Konto und Organisation zeigen alle Angaben lesend, ohne Passwörter, Codes oder Token.
- **Zweiter Faktor durch Organisations-Admins (#404):** In «Meine Organisation» löschen Organisations-Admins den
  zweiten Faktor eines Mitglieds, etwa nach Verlust des Handys. Das Mitglied wird abgemeldet und erhält eine Mail.
  Andere Organisations-Admins und Konten mit weiteren Rechten bleiben der Administration vorbehalten.
- **Zweiten Faktor selbst zurücksetzen (#403):** Wer keinen Zugriff mehr auf die Authenticator-App hat, fordert im
  Schritt «Code» einen Link per Mail an und bestätigt dort mit E-Mail und Passwort. Danach ist der zweite Faktor
  entfernt, und nach der nächsten Anmeldung wird das Einrichten angeboten. Gilt für Konten ohne erhöhte Rechte.
- **E-Mail-Adresse ändern (#416):** In «Mein Konto» lässt sich die Adresse ändern. Ein Link geht an die neue Adresse,
  die bisherige erhält einen Hinweis; erst nach der Bestätigung gilt die neue.
- **Verlauf (#417):** «Mein Konto», «Meine Organisation» (für Organisations-Admins) und die Detailseiten der
  Administration zeigen ganz unten einen aufklappbaren Verlauf: wer wann was geändert hat, auch Änderungen durch
  Administration und Organisations-Admins. Personenbezogene Werte erscheinen nur als «geändert».
- **Impressum (#411):** Neue Seite `/impressum`, verlinkt in der Fusszeile. Die Angaben trägt jede Instanz unter
  Administration › Einstellungen › Impressum ein (oder in der Umgebung). Die Datenschutzerklärung nennt die
  verantwortliche Stelle.

### Geändert

- **Lesefassung im breiten Format (#441):** Auf breiten Bildschirmen zeigt die Lesefassung auf GitHub Pages je Begriff
  eine Zeile mit Abkürzung, Begriff, Kurzbeschreibung und Kategorien, mit Zebrastreifen.
- **Zweiter Faktor bei der Anmeldung (#440):** «Code eingeben» zeigt das Konto, mit dem man sich anmeldet, und
  bietet «Mit anderem Konto anmelden» an.
- **GitHub und Lesefassung (#409, #410):** README und Hinweise zum Betrieb beschreiben den Stand der öffentlichen Beta.
  Die Lesefassung auf GitHub Pages holt die Begriffe täglich aus der öffentlichen API und hat ein Produkt-Intro.
- **Rechtliches, Datenschutz und Hilfe (#406, #407, #408):** «Rechtliches» erklärt kurz, was CC BY 4.0 für die
  Begriffe und AGPL-3.0 für den Code heisst, und listet die Lizenzen aller ausgelieferten Teile und der wichtigsten
  Drittkomponenten mit Link auf den vollständigen Lizenzbericht. Die Datenschutzerklärung (Entwurf) und die Hilfe
  beschreiben den aktuellen Stand (Verlauf, E-Mail ändern, zweiter Faktor, Browsererweiterung, Cookies).
- **Liste der Begriffe (#425):** Jeder Begriff zeigt seine Version als kurzen Tag (z. B. «V2») ganz rechts, in den
  Kacheln unten rechts. In der Balken-Ansicht ist jede zweite Zeile leicht hinterlegt, Begriffe ohne Abkürzung tragen
  den fetten Hinweis «k. Abk.», Kategorien stehen direkt nach dem Begriff und die Herkunft rechtsbündig in derselben
  Zeile, beide mit kleiner Beschriftung.
- **Mehr Details in der Liste (#428):** Gültigkeit und der Hinweis «angepasst» (eigene Darstellung bzw. die der
  Organisation) stehen immer unter dem Begriff, wenn gesetzt. Der Schalter «Mehr Details» blendet Aliase,
  Beschreibung, Quelle, Permalink und das Datum der Version ein; die Wahl bleibt im Browser gemerkt.
- **Öffentliche Beta (#401):** Der Hinweis oben auf jeder Seite sagt jetzt, dass das Glossary als öffentliche Beta
  nutzbar ist, und lädt zu Rückmeldungen ein.
- **Anmeldung (#402):** «Passwort vergessen?» steht unter dem Anmelde-Button, sobald der Mailversand eingerichtet
  ist, auch wenn die Registrierung zu ist. «Konto erstellen» steht daneben, wenn die Registrierung offen ist.
- **Mailserver einrichten (#405):** In Administration › System führt ein Button neben «Testmail senden» direkt zum
  Formular für den Versand.
- **Mein Konto (#418):** «Meine Organisation» kommt direkt nach den eigenen Angaben.
- **Art der Einrichtung (#415):** In «Mein Konto» stehen neu Persönlich, Primarschule, Sekundarschule, Gesamtschule,
  Firma – Dienstleister, Volksschulamt, Gemeindeamt, Kanton, Datenschutz und andere zur Wahl; bei den Schulen zusätzlich
  «Einheitsgemeinde». Das Feld daneben heisst «Schule / Gemeinde / Organisation / Behörde».

### Behoben

- **Sicherheits-Review (#445, #446, #447, #448):** Konten ohne eigenes Passwort löschen sich nur noch über einen Link an
  die eigene Adresse oder mit dem Code des zweiten Faktors. Neue Wiederherstellungscodes verlangen den aktuellen Code
  und lösen eine Hinweismail aus. Die API reserviert einen Idempotency-Key vor der Aktion, gleichzeitige Anfragen
  erhalten 409. Der Excel-Import weist Dateien ab, die entpackt grösser als 50 MB sind.
- **Lizenz der Browsererweiterung (#408):** Der ZIP liegt jetzt die MIT-Lizenz bei, unter der die Erweiterung steht.

## [0.16.0] – 2026-11-15

### Neu

- **Browsererweiterung zum Herunterladen (#385, #386):** Jedes Release enthält die Erweiterung für Chrome, Edge und
  Firefox als ZIP. Die Seite «Für Entwickler» verlinkt sie und erklärt, wie man sie von Hand installiert. Die Erweiterung
  trägt jetzt die Version der App und das aktuelle Logo. Sobald die Store-Konten eingerichtet sind, kommt jede neue
  Version automatisch in den Chrome Web Store, zu Edge Add-ons und zu Firefox Add-ons. Für Firefox erklärt die
  Erweiterung, dass sie keine Daten erhebt, und braucht deshalb Firefox 140 oder neuer.
- **Sitemap und Suchmaschinen (#393):** Die öffentliche Instanz liefert `/sitemap.xml` mit allen veröffentlichten
  Begriffen und eine `robots.txt`, die auf die Sitemap zeigt. Andere Instanzen wie Test- und Entwicklungsumgebungen
  oder selbst gehostete Kopien erscheinen nicht in Suchmaschinen; einschalten mit `GLOSSARY_SUCHMASCHINEN=true`.

### Behoben

- **Wiederherstellen mit Schlüssel der Quelle (#387):** `glossary:wiederherstellen` entfernt Leerzeichen und
  Zeilenumbrüche aus dem eingegebenen Schlüssel und liest ihn mit `--schluessel-datei=` auch aus einer Datei. Passt die
  Form nicht, nennt die Meldung die erwartete Form und Länge, ohne den Schlüssel anzuzeigen.
- **Sicherheitskopfzeilen (#396):** `X-Content-Type-Options` und `Referrer-Policy` kommen nur noch einmal (vorher
  doppelt), der Server nennt keine Version mehr, und das Sitzungs-Cookie trägt über HTTPS das Präfix `__Host-`. Nach dem
  Update ist man einmal abgemeldet.

## [0.15.0] – 2026-11-14

### Neu

- **Datenmodell-Diagramm bedienbar (#371, #376, #378):** Auf der Seite «Für Entwickler» lässt sich das Diagramm einpassen,
  vergrössern, verkleinern, verschieben (Maus oder Finger) und im Vollbild ansehen; zoomen auch mit Ctrl/⌘ + Mausrad
  oder zwei Fingern. «SVG» lädt das ganze Diagramm in Originalgrösse und hell herunter, «PDF …» öffnet es als eine Seite
  in der Druckansicht zum Speichern als PDF.
- **Alle Einstellungen auf einer Seite (#366):** Administration › Einstellungen zeigt neben den einstellbaren Werten
  auch die Umgebung (`.env`) zum Ansehen – mit Herkunft je Wert, Geheimnisse nur als «gesetzt». Neu einstellbar:
  Token für die Metriken (erzeugen, erneuern, entfernen), Adresse des Quellcodes und die Passwortpflicht für Dumps.
  Werte in der Administration gelten sofort; ist ein Wert in der Umgebung gesetzt, hat er Vorrang und ist gesperrt.
- **Sicherung und Wiederherstellung auf dev (#355):** Administration › System › «Vollständige Sicherung (Restore)»:
  vor einem Versuch eine Sicherung auf dem Server anlegen und sie später wieder einspielen – oder einen Dump
  hochladen. Konten mit Passwort und zweitem Faktor, Anmeldungen über Microsoft, Rollen und die laufenden Sitzungen
  bleiben; alles andere kommt aus der Sicherung. Der Stand davor wird automatisch gesichert. Nur mit
  `GLOSSARY_DEV_BACKUPS=true` (dev).
- **PostgreSQL im Stack (#357):** Zusatz `compose.postgres.yaml` mit PostgreSQL 18 nur im internen Netz; die App
  erhält einen Zugang nur auf das Schema `glossary`, die Passwörter liegen als Dateien in `secrets/`. Das
  Installationsskript fragt «SQLite oder PostgreSQL» und bietet bei einer bestehenden Installation die Umstellung an.
  Beim ersten Start mit PostgreSQL übernimmt die App eine vorhandene SQLite-Datenbank automatisch (Kopie vorher,
  Zählung je Tabelle; bei Abweichung startet sie nicht). Auf PostgreSQL sichert der Container mit einem Dump vor jedem
  Update und täglich; die Sicherung ausser Haus nimmt ihn mit. Das Image enthält jetzt den Treiber für PostgreSQL.
- **API Stufe 2 (#347, #348, #349, #350):** Servicekonten können einen eigenen offenen Vorschlag mit
  `DELETE /api/v1/proposals/{id}` zurückziehen; nötig ist `If-Match` mit dem ETag aus GET bzw. POST (fehlt: 428,
  veraltet: 412). `POST /api/v1/proposals` nimmt `Idempotency-Key` an: Wiederholungen liefern dieselbe Antwort ohne
  zweiten Vorschlag. Die OpenAPI nennt je Operation Zweck und Datenklassen; die CI vergleicht sie mit dem letzten
  Release und schlägt bei brechenden Änderungen fehl.
- **Protokolle und Metriken (#192):** Im Container schreibt die Anwendung ihre Protokolle als JSON, eine Zeile je
  Eintrag mit Anfrage-ID, Modul und Version (`LOG_FORMAT=json`). Mit `GLOSSARY_METRICS_TOKEN` gibt es unter `/metrics`
  Metriken für Prometheus: Anfragen und Antwortzeiten je Bereich, Anmeldungen, veröffentlichte Begriffe, offene
  Vorschläge, Warteschlange – ohne Personendaten, nur mit Token.
- **Plugins für Gastgeber (#338):** Der Steckbrief nennt unter `ui` die Web-Komponenten (`/ui/v1/glossary.js`), die
  Kachel «Glossary-Suche» (`<glossary-search>`, öffentlich) und die Suche für Gastgeber. Mit einer Script-Zeile lässt
  sich die Suche in eine beliebige Seite einbinden – ohne Anmeldung, nur öffentliche Daten, ohne Cookies.
- **Einsammeln (Prototyp, #360):** Administration › «Einsammeln» mit «Text prüfen» (unbekannte Kürzel samt vermuteter
  Langform aus einem eingefügten Text, als Entwurf übernehmen), «Gesucht» (häufigste Suchen ohne Treffer) und
  «Prüfbedarf» (Regeln für veröffentlichte Begriffe). Der geprüfte Text wird nicht gespeichert. Das Konzept dazu steht zur Abnahme (`docs/KONZEPT-EINSAMMELN.md`).

### Geändert

- **Seitenbreite (#369, #382):** Einsammeln, Suchen ohne Treffer, Servicekonten, Einstellungen, Papierkorb, System und
  die Seite «Für Entwickler» folgen der gewählten Breite (Darstellungsoptimiert, Breit, Voll), ebenso die Leisten mit
  Mitteilungen (News, Cookies, neue Version, App installieren); Fliesstext bleibt auf Lesebreite.
- **«Für Entwickler» im Menü der Administration (#383).**
- **Vorschläge mit UUID (#344):** Vorschläge haben intern eine UUID als Schlüssel; Adressen wie `/vorschlaege/…` und
  die API verwenden sie. Die gewohnte «Vorschlag Nr. …» bleibt als Anzeige. Bestehende Vorschläge und ihr Verlauf
  werden beim Update vollständig übernommen.
- **Lizenz AGPL-3.0 (#336):** Der Code steht ab dieser Version unter der GNU Affero General Public License 3.0 oder
  später (bisher MIT; frühere Versionen bleiben MIT). Die Daten bleiben unter CC BY 4.0. Die Fusszeile verweist auf
  den Quellcode der laufenden Version (`GLOSSARY_SOURCE_URL`).

### Behoben

- **`COMPOSE_FILE` im Container:** Der Wert aus der `.env` gilt nicht mehr als Datei mit einem Geheimnis (Meldung beim
  Start).
- **Tabellen ohne unnötigen Scrollbalken (#370):** Die Spalte, die den freien Platz aufnimmt, zählt für die
  Mindestbreite der Tabelle nur noch mit ihrer Mindestbreite; ein Scrollbalken erscheint erst, wenn die übrigen
  Spalten wirklich nicht mehr hineinpassen.


## [0.14.0] – 2026-11-14

### Neu

- **API nach Plattform-Standard (#320, #321, #322, #323, #324, #326, #327, #337):** Fehler mit `type`, `code`,
  `request_id` und Titel in der Sprache der Anfrage, auch 401 und 429. Sprache mit `?locale=` oder `Accept-Language`
  (`?lang=` gilt weiter), Antwort mit `Content-Language` und `locale` je Objekt. Begriffsliste mit Cursor (`cursor`,
  `limit`, `meta.next_cursor`, `has_more`), Gesamtzahl nur mit `with_total=1`; `page`/`per_page` übergangsweise.
  Ungültige und unbekannte Parameter ergeben 422. Vorschläge über UUID. Scopes `glossary:proposals.create`,
  `glossary:proposals.read`, `glossary:terms.read` und Tokens `glossary_sa_…`; bestehende Servicekonten und
  `gls_…`-Tokens gelten weiter. OpenAPI ohne Warnungen nach dem Regelsatz der Plattform, geprüft in der CI.
- **Datenbank nach Plattform-Standard (#191, #311, #330, #331, #332, #333):** Einheitliche Namen in Datenbank, API und
  Export (u. a. `terms.status`, `term_aliases.locale`, `term_organization_overrides`, `proposals.author_id`);
  Plattform-IDs für Organisationen und Konten (`glossary:plattform-id`); Ereignis-IDs für Feed und Protokolle. Auf
  PostgreSQL liegen alle Tabellen im Schema `glossary`; Umzug einer SQLite-Datenbank mit `glossary:umzug-postgres`.
  Das Update auf 0.14 läuft mit kurzem Unterbruch; ein Rücksprung auf 0.13 geht nur mit der Sicherung.
- **Datenschutz nach Plattform-Standard (#312, #313, #314):** «Mein Konto» → «Meine Daten» lädt alle Daten des
  Kontos als JSON herunter, die Verwaltung exportiert sie für eine Anfrage per Brief (jeder Aufruf im
  Sicherheitsprotokoll). Bearbeitung eines Kontos einschränken (`glossary:einschraenken`). Zweck, Rechtsgrundlage und
  Löschfrist je Tabelle im Code; daraus das Verzeichnis der Bearbeitungen (`docs/DATENSCHUTZ-VERZEICHNIS.md`).
  Kopien für dev und test nur anonymisiert (`glossary:dump --anonymisiert`, `glossary:dump-einspielen`).
- **Vollständige Sicherung wiederherstellen (#353):** `php artisan glossary:wiederherstellen <datei>` spielt einen Dump
  aus `glossary:dump` ein – als Rückweg oder um die Daten auf eine neue Instanz zu bringen (z. B. von dev nach prod),
  auf SQLite oder PostgreSQL. Vorher Prüfung von Prüfsummen, Version und Schlüssel (`--trocken` prüft nur), danach
  Zählung je Tabelle; der bisherige Stand wird zuerst gesichert, ein Abbruch ändert nichts. Stammt der Dump von einer
  Instanz mit anderem `APP_KEY`, werden zweiter Faktor und Mailpasswort mit dem Schlüssel der Quelle neu verschlüsselt.
  Alle melden sich danach neu an. Nur in production; dev und test erhalten weiter nur anonymisierte Dumps.
- **Steckbrief und Bereitschaft (#189, #325):** `/.well-known/eduksx-module.json` mit Modulschlüssel, Version,
  API-Version, Datenlizenz, Verweisen auf OpenAPI und Dokumentation sowie den Ereignissen, die das Glossary von Ident
  annimmt; `/health` meldet Datenbank, Migrationen, Warteschlange und Speicher (503, wenn Datenbank oder Migrationen
  nicht stimmen). `/up` bleibt. Beides aus `eduksx/core` 0.2.
- **Ereignisse von Ident (#187):** Signierte Ereignisse unter `/api/v1/platform-events` (aktiv mit
  `EDUKSX_EVENTS_KEYS`): «Konto gelöscht» löscht das Konto wie heute, «Konto gesperrt» sperrt es. Unsignierte, falsche
  oder über 5 Minuten alte Ereignisse werden abgewiesen, Wiederholungen nur einmal verarbeitet. Abmelden über die
  Plattform (Back-Channel-Logout) folgt mit der Anmeldung über Ident (#185).

### Geändert

- **Gemeinsame Bausteine der Plattform (#334):** Fehler der API, Request-ID, Sicherheitskopfzeilen, Abruflimit,
  STRICT-Tabellen, optimistisches Sperren und ETag der Lese-API kommen aus `eduksx/core`. Fehler der API enthalten
  zusätzlich `code` und `request_id`, Antworten die Kopfzeilen `X-Request-Id` und `EduKSX-Release`; ein
  Konformitätstest der Plattform läuft mit den Tests; die bisherigen eigenen Umsetzungen sind entfernt.

## [0.13.0] – 2026-11-13

### Neu

- **Umgebungsvariablen (#306):** Übersicht aller Variablen mit Standardwerten, Werten für dev, test und prod sowie
  den Einstellungen in GitHub (`docs/UMGEBUNGSVARIABLEN.md`).

### Geändert

- **DB-Guideline (#310):** Version 1.2 nach dem DB-Standard 1.2 der Plattform: Schema je Modul, Plattform-IDs für
  Organisationen und Konten, Auskunft und Datenherausgabe, Zweck je Tabelle, anonymisierte Kopien für dev und test.
- **Versionsbezeichnung (#308):** Entwicklungsstände heissen jetzt z. B. `0.13.0-dev.5` (geplante Version und Zahl der
  Änderungen seit dem letzten Release), Betas `0.13.0-beta.1`, Releases `0.13.0`. Test und Produktion zeigen nach einem
  Release bzw. einer Beta genau diese Version.
- **Umgebungen (#304):** dev unter `glossary-dev.markussx.ch` (`glossary.markussx.ch` leitet weiter); Beta-Versionen
  gehen auf test, Releases auf prod (Image-Tags `beta` und `stable`).
- **Mail-Versand und Registrierung (#300, #302):** Der Betrieb versendet über den Mail-Service von Infomaniak mit
  Absender aus `eduksx.ch`; neue Server lassen sich direkt in der Administration einrichten. Neue Konten (E-Mail oder
  Microsoft) gibt es nur, wenn «Registrierung offen» in den Einstellungen an ist (Vorgabe aus).

## [0.12.0] – 2026-11-13

### Neu

- **DB-Entwickler-Guideline (#270, #272):** Regeln für Datenbank, Migrationen und Modelle (`docs/KONZEPT-DB-GUIDELINE.md`), verlinkt aus AGENTS.md und dem Datenbank-Konzept; seit Version 1.1 Anwendung des gemeinsamen DB-Standards der Plattform mit Geltungsbereichen, Datenklassen und Löschregister; offene Punkte entschieden, Löschkonzept 1.1 mit Kontolöschung nach Frist und Papierkorb (#274).
- **Vollständiger Dump (#268):** Administration › System und `php artisan glossary:dump`: ZIP mit Kopie der Datenbank,
  allen Tabellen als JSON und Manifest; mit Passwort AES-256-verschlüsselt, ohne Passwort nur bis zur Einstellung
  `GLOSSARY_DUMP_REQUIRE_PASSWORD`.
- **Übersetzungen (#282, #291):** Begriffe, Kategorien und Herkünfte lassen sich auf Französisch, Italienisch und Englisch
  erfassen. Übersetzungen von Begriffen gehören zur Revision und werden mit dem Begriff veröffentlicht. Die API liefert
  sie in `translations`; mit `?lang=fr|it|en` ersetzen sie die deutschen Felder, fehlende bleiben deutsch und
  `language` zeigt je Feld die gelieferte Sprache. Die Website bleibt deutsch. Im Excel-Export und -Import stehen sie in
  Spalten wie «Bezeichnung (FR)»; fehlt eine Spalte in der Datei, bleibt der Wert.
- **Beziehungen zwischen Begriffen (#283):** In der Redaktion lassen sich Nachfolger, «siehe auch», Ober- und
  Unterbegriffe erfassen; sie werden mit dem Begriff veröffentlicht. Die Seite eines Begriffs zeigt sie unter
  «Verwandte Begriffe», auch in Gegenrichtung (z. B. Vorgänger). Die API liefert sie in `relations`.
- **Organisation mit Microsoft-Tenant (#284):** Organisations-Admins können den Microsoft-Tenant ihrer Organisation
  hinterlegen, sofern sie selbst damit verknüpft sind. Wer sich aus diesem Tenant mit Microsoft anmeldet und noch
  keiner Organisation angehört, erhält eine Einladung; Rechte entstehen dadurch keine.
- **Suchen ohne Treffer (#285):** Findet eine Suche auf der Website oder in der API nichts, zählt die Anwendung den
  Suchtext ohne Personenbezug. Die Redaktion sieht die Liste unter «Ohne Treffer»; Einträge werden 90 Tage nach der
  letzten gleichen Suche gelöscht.
- **Papierkorb (#286):** Gelöschte Kategorien, Herkünfte und Organisationen kommen zuerst in den Papierkorb und werden
  nach 30 Tagen endgültig gelöscht. Developer können sie unter «Papierkorb» wiederherstellen.
- **Änderungsprotokoll (#276, #286):** Änderungen an Listen, Organisationen, Konten, Rollen und Servicekonten werden mit
  Zeitpunkt und handelnder Person festgehalten; personenbezogene und geheime Werte nur als «geändert». Jede Spalte hat
  eine Datenklasse, die Übersicht steht in `docs/DATENKLASSEN.md`.
- **Gleichzeitiges Bearbeiten (#286):** Haben zwei Personen denselben Begriff oder Listeneintrag offen, überschreibt das
  spätere Speichern nichts mehr; es erscheint ein Hinweis, die Seite neu zu laden.

### Geändert

- **Datenbank bereinigt (#266, #275):** Neuer Anfangsstand mit strikten Tabellen, Prüfregeln für alle Status und Arten,
  Fremdschlüsseln und Indizes; Namen nach dem DB-Standard. Bestehende Daten werden beim Update überführt und gezählt.
  Suchbegriffe nur noch als Aliase. Altwerte und der Import des alten Excel-Formats des Intranets entfallen; die Werte
  sind verschlüsselt archiviert.
- **Rollen-Schlüssel englisch (#288):** Die Rollen Gast und Benutzer heissen intern `guest` und `user`. Die Anzeige bleibt
  gleich, und bestehende Zuordnungen bleiben erhalten.
- **API: Werte englisch (#266):** Status eines Begriffs `current`, `historical`, `under_review`; Status eines Vorschlags
  `organization_review`, `submitted`, `query`, `accepted`, `rejected`, `withdrawn`. Feldnamen bleiben; der Filter
  `status` nimmt auch die bisherigen Werte an.
- **Microsoft-Anmeldung (#277):** Ein verknüpftes Konto wird über Aussteller und Kennung erkannt, unabhängig vom
  Anbieter; bestehende Verknüpfungen bleiben erhalten.
- **PostgreSQL (#287):** Migrationen und alle Tests laufen in der CI zusätzlich gegen PostgreSQL; Abfragen nach ID oder
  Slug und die neueste Revision funktionieren auf beiden Datenbanken.

## [0.11.0] – 2026-11-12

### Neu

- **WebApp und Icon (#239, #240, #251):** Glossary-Symbol aus dem CI für Browser-Tab, Home-Bildschirm und vor dem
  Titel; das Glossar lässt sich als App installieren, auf dem Handy mit Hinweis «App installieren» (iPhone mit
  Anleitung).
- **Hinweis nach einem Update (#255):** «Aktualisiert auf Version …» mit Link zu den Neuerungen, bis er weggeklickt
  wird.
- **Scroll-Leiste A–Z (#254):** Auf dem Handy rechts, zeigt beim Scrollen den aktuellen Buchstaben und springt beim
  Antippen.
- **Labels mit Erklärung (#258):** Antippen eines Labels zeigt Bezeichnung, Beschreibung und Link.
- **Flyout-Menü auf dem Handy (#259, #263):** links mit Person, Ab-/Anmelden, Wechsel Website/Administration,
  Menüpunkten mit Icons, «App installieren» und Farbschema.

### Geändert

- **Handy-Ansicht (#225, #226, #227, #233, #235, #236, #237, #241):** Kacheln passen auf den Bildschirm; Kopfzeile auf
  einer Zeile mit Titel, Person und Ab-/Anmelde-Icon (Farbschema und Wechsel im Flyout-Menü, ohne Ansichtsbreite);
  Fusszeile eingeklappt mit Version und Ausklapp-Button; Suchfeld ohne Autofokus; Index A–Z und Filter einklappbar;
  PDF-Dialog passt auf den Bildschirm.
- **Kacheln (#229):** Kopf zweizeilig – Abkürzung mit Herkunft und Status, Bezeichnung mit Kategorien (auf dem Handy
  untereinander, #232).
- **Startseite (#238):** Anzahl und Ansicht bleiben beim Scrollen unter der Kopfzeile.
- **Export (#234, #247):** Icons in den Farben der Formate.
- **Kacheln öffnen die Details (#249, #250):** Symbol unten links und Tippen auf die Kachel; Detailansicht mit
  «Schliessen».
- **Handy-Startseite (#244, #245, #246, #248, #253):** Suchfeld mit Leiste (Filter, Anzahl, Export) fixiert, immer
  Kacheln, Text in Kacheln nicht markierbar; auch quer, dort zwei Kacheln nebeneinander.

## [0.10.0] – 2026-11-12

### Neu

- **Selbst hosten (#179, #197, #198, #199):** Installationsskript `docker/install.sh` für Ubuntu 24.04/26.04 und Debian
  12/13 mit Bausteinen für Caddy (Standard), vorhandenen Traefik, Schulnetz (Proxy, eigene Zertifizierungsstelle),
  Update-Dienst und Sicherung ausser Haus (restic, verschlüsselt, 30 Tage, monatlicher Wiederherstellungstest);
  Anleitung `docs/SELBST-HOSTEN.md`.
- **Updates (#176, #177, #178):** Hinweis auf neue Versionen in der Administration (höchstens täglich, abschaltbar);
  «Jetzt aktualisieren» auf der Systemseite mit Update-Dienst (`docker/compose.updater.yaml`): Image holen, Datenbank
  sichern, Neustart, Prüfung, bei Fehler zurück; Protokoll auf der Systemseite. Automatische Updates nach Richtlinie
  (Vorgabe «nur Korrekturen», abschaltbar, mit Zeitfenster); optionaler Update-Schlüssel.
- **Mail-Versand in der Administration (#219):** Systemseite «Versand einrichten» mit Vorlagen (Microsoft 365, Google,
  Infomaniak, Hostinger); Speichern sendet sofort eine Testmail, erst danach gilt der Versand als eingerichtet. Passwort
  verschlüsselt gespeichert und nie angezeigt; Einstellungen in der Umgebung des Servers haben Vorrang.

## [0.9.1] – 2026-11-12

### Neu

- **Prüfung der Konfiguration (#196):** `php artisan glossary:pruefen` und Systemseite zeigen fehlende, unsichere oder
  widersprüchliche Einstellungen (Schlüssel, Adresse, sichere Cookies, Datenbank, Sicherung, Mail, Dienste), ohne Werte
  von Geheimnissen. Geheimnisse auch als Datei (`NAME_FILE`); Vorlage für den Server in `.env.example`.
- **Docker-Image in der Registry (#175, #195):** Jeder geprüfte Stand und jedes Release als fertiges, signiertes Image
  (`ghcr.io/markuskeller8200/eduksx-glossary`) mit Herkunftsnachweis; `docker/compose.yaml` startet es ohne Bauen,
  Zusätze für Traefik und Caddy. Der Entwicklungsserver holt jeden Stand als Image statt zu bauen, prüft ihn und
  springt bei Fehlern zurück; der Deploy-Schlüssel kann nur noch ausrollen.

### Geändert

- **Ansichten der Begriffe (#205, #213):** drei Darstellungen in der Administration und auf der Startseite: Tabelle,
  Balken (eine Zeile je Begriff) und Kacheln mit allen Werten (Herkunft und Kategorien, Kurzbeschreibung, Beschreibung
  und Notiz gekürzt, Aliase, Schreibweisen, Suchbegriffe, Gültigkeit, Daten, Permalink und Quelle), bis drei
  nebeneinander. Auf der Startseite umschaltbar mit Symbolen, im Browser gemerkt, ohne Funktionen der Administration
  und nur mit öffentlichen Werten.
- **Datenschutzerklärung und Löschkonzept (#207):** Betrieb bei Infomaniak in der Schweiz (Server, verschlüsselte
  Sicherungen, Mail-Versand); Cloudflare nur für DNS, ohne Cloudflare-Cookies; Fristen festgelegt: Serverlogs 14 Tage,
  Sicherungen 30 Tage.

## [0.9.0] – 2026-11-09

### Neu

- **Datenmodell (#170):** «Für Entwickler» zeigt alle Tabellen mit Spalten, Schlüsseln und Verweisen, ein
  ER-Diagramm (Mermaid, nachgeladen) und ein SQL-Script der Struktur zum Herunterladen (ohne Daten).
- **News-Einwilligung (#169):** freiwillig, keine Werbung: Kästchen bei der Registrierung (nicht vorausgewählt),
  einmalige Frage nach der Anmeldung, änderbar unter «Mein Konto»; Datenschutzerklärung ergänzt.
- **Übernehmen in die Organisation (#165):** Organisations-Admins übertragen «Meine Darstellung» eines
  Begriffs mit «Übernehmen» in die Darstellung der Organisation (mit Warnung; «Meine Darstellung danach löschen»
  standardmässig an). Hinweis: persönliche Texte sind für die Organisation nicht sichtbar.
- **Permalink (#154):** Jeder Begriff hat eine kurze, unveränderliche Adresse `/g/{Code}` («g» für
  Glossary), z. B. `/g/7KQ2MX`; leitet auch nach dem Zusammenführen weiter. Auf der Seite des Begriffs mit «Link
  kopieren».
- **API (#159):** Felder `code` und `permalink`, Abruf über den Code, kompakter Index `/api/v1/index` mit ETag für
  Renderer und Browser-Erweiterung; CORS erlaubt `Authorization`.
- **Einbindung für Entwickler (#155):** Eine Script-Zeile (`/embed/v1/glossary.js`, versioniert mit
  SRI-Prüfsumme) markiert Abkürzungen und Begriffe auf der eigenen Webseite fein und erklärt sie per Maus und
  Tastatur; `<glossary-term code="…">` für ausdrücklich markierte Begriffe. Suche nur im Browser, keine Cookies.
  Anleitung unter «Für Entwickler» (`/entwickler`), Testseite `/embed/demo.html`. Gemeinsamer Kern
  `packages/highlighter` mit Tests.
- **Browser-Erweiterung (#153):** Manifest V3 für Chrome, Edge und Firefox: auf der aktuellen Seite
  hervorheben (nur `activeTab`) oder für eine Website immer; Einstellungen für Bezeichnungen und erste Fundstelle.
  Kein Seiteninhalt verlässt den Browser. Bauen mit `node scripts/build-extension.mjs`, Anleitung in
  `extension/README.md`; Veröffentlichung in den Stores folgt.
- **Swagger-Seite (#157):** `/api/swagger` mit «Try it out» und «Authorize» für Token, selbst ausgeliefert.
- **Postman-Sammlung (#158):** `/api/postman.json` zum Import mit Variablen `baseUrl` und `token`; aus der
  OpenAPI-Beschreibung erzeugt (`scripts/postman.py`), die CI prüft den Stand.

### Geändert

- **Anzeigetexte (#172):** ohne interne Entscheidnummern in Hilfe, Einstellungen, Versionsverlauf und API-Beschreibung.
- **Anmeldeseite (#168):** Funktionen mit Konto, Hinweis zum zweiten Faktor ab Organisations-Admin und «Noch kein
  Konto? Hier ein Konto erstellen».
- **Detailseite eines Begriffs (#163):** optimale Breite; alle öffentlichen Felder einzeln (offizielle Aliase,
  alternative Schreibweisen, Suchbegriffe, Status, Gültig ab/bis, Revision, Permalink, Code), Herkunft, Kategorien
  und Status als farbige Labels.
- **Herkunft vor Kategorie (#162):** auf der Startseite (Filter und Liste), in der Begriffstabelle (neue Spalte
  Herkunft) und auf der Detailseite.
- **Hilfe (#164, #166):** Reiter je Rolle (Gast / Benutzer, Organisations-Admin, Moderator; Admin und Developer nur
  mit dieser Rolle) mit den passenden Feldern und Abläufen; Gültig ab/bis, Revision, Veröffentlicht und Stand je eine
  Zeile; neue Gruppen «Meine Darstellung» und «Organisation».

## [0.8.0] – 2026-11-08

### Neu

- **Organisation (#148):** Benutzer gründen unter «Meine Organisation» eine Organisation und werden
  Organisations-Admin (globale Rolle, zweiter Faktor). Einladen per E-Mail (mehrere, mit `;` oder `,` getrennt, 14 Tage
  gültig); die eingeladene Person nimmt die Einladung unter «Meine Organisation» an. Vorschläge von Mitgliedern
  bestätigt zuerst ein Organisations-Admin, dann prüft die Redaktion. Organisations-Admins legen die
  Darstellung eines Begriffs für alle Mitglieder fest (persönlich vor Organisation vor gemeinsam), sperren oder
  entfernen Mitglieder und ernennen weitere Admins; es bleibt immer mindestens einer. Servicekonto mit Scope
  `read-org` liefert die Begriffe in der Darstellung der Organisation (`/api/v1/org/terms`). Neues Grundrecht
  «Organisation gründen».
- **Meine Darstellung nur mit Organisation (#148):** persönliche Alternativtexte nur noch für Mitglieder einer
  Organisation und erst nach Freigabe durch einen Organisations-Admin (eigene Texte der Organisations-Admins sofort).
  Bisherige Anpassungen bleiben gespeichert und gelten nach Freigabe.
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
- **Leistung (#137):** Öffentliche Sicht (Startseite, API, Exporte) je Datenstand zwischengespeichert – nach
  Veröffentlichung und Änderungen an den Listen sofort aktuell; PDF-Dialog wird erst beim Öffnen geladen; weniger
  Abfragen bei Einstellungen und im Protokoll der Servicekonten.
- **Aufgeräumt (#138):** Konto der Anfrage im Basis-Controller statt 13 Kopien, ein Baustein für Datei-Downloads,
  gemeinsamer Titel aus Abbildern; «Mein Konto» nutzt das Export-Menü.
- **Dokumentation (#141):** Changelog je Thema zusammengefasst (Regel in AGENTS.md), README mit Funktionsübersicht,
  Datenmodell um Servicekonten und Einstellungen ergänzt, Hilfe mit Rollen und Rechten, Export, zweitem Faktor und API.
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
  Vier-Augen-Prinzip bei der Freigabe (Vorgabe aus) sowie Hinweise und Links; neues Recht «Allgemeine
  Einstellungen» (systemkritisch), jede Änderung im Protokoll.
- **Vorschläge und Moderation (#113):** Ab Benutzer neuen Begriff oder Korrektur einreichen («Begriff
  vorschlagen», «Korrektur vorschlagen»); Stand, Rückfragen und Zurückziehen unter «Meine Vorschläge». Die Moderation
  vergleicht feldweise Basis, Arbeitsstand und Vorschlag (Konflikte markiert), nimmt gewählte Felder als Entwurf an,
  lehnt mit Begründung ab oder fragt nach. Servicekonten reichen per `POST /api/v1/proposals` ein. Nie öffentlich;
  Personenbezug 24 Monate nach Abschluss bzw. bei Kontolöschung entfernt. GitHub nur noch für Software-Meldungen.
- **Revisionen wiederherstellen (#95):** Im Verlauf eine frühere Revision als neue Revision wiederherstellen,
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
  eigenes Protokoll. Kein Login. Prüfung über `GET /api/v1/service/whoami`; Vorschläge per Token siehe #113.
- **Hierarchie der Rollen (#115):** «Rollen und Rechte» zeigt die Stufen Gast bis Developer mit den Rechten, die jede
  Stufe dazubekommt, farbig nach Schutzklasse.

### Geändert

- **Seitenbreite (#99, #127):** Website und Administration einheitlich 1152 px, lange Texte auf Lesebreite;
  Versionen, Datenschutz und Rechtliches wie die übrigen Seiten.
- **Tabellen (#104, #128):** Spaltenbreiten nach Inhalt, neu berechnet nach Grössenänderungen und bei geändertem
  Inhalt (Suche, Filter, Gruppen); Doppelklick auf den Splitter (oder Enter) passt eine von Hand gezogene Spalte wieder
  an. Begriffstabelle gruppiert zusätzlich nach Erstelldatum und letzter Änderung.
- **Fusszeile (#102, #125):** auch in der Administration immer sichtbar, kürzere Links «Fehler melden» und «GitHub
  Pages».
- **Farbwahl (#107):** Farben in «Listen» als farbige Labels wählbar, neu mit den CI-Farben Violett, Orange und Blau.
- **Kategorien (#105):** werden überall untereinander angezeigt.

### Behoben

- **Korrektur vorschlagen (#133):** Die Seite bricht bei älteren Begriffen nicht mehr ab.
- **Tabellenbreite (#123):** Tabellen der Administration füllen bei «Ganze Breite» den ganzen Platz; die breiteste
  Textspalte nimmt den freien Platz auf.
- **Auswahllisten im Dunkelmodus (#107):** Einträge sind lesbar (heller Text auf dunklem Grund).

## [0.6.0] – 2026-11-07

### Neu

- **CSV-Export (#71):** Begriffe zusätzlich als CSV (UTF-8, Semikolon); Formeln werden als Text geschützt.
- **Öffentlicher Verlauf (#68):** Die Detailseite zeigt die veröffentlichten Revisionen mit Datum, ohne Personen.
- **Quellenprüfung (#69):** Prüfdatum und Belegstatus je Herkunft; Seite «Prüfung» mit jährlicher Wiedervorlage.
- **Import-Protokoll (#64):** Jeder übernommene Import mit Datei, Person, Zeitpunkt und Ergebnis.
- **Softwarelizenz (#65):** Der eigene Code steht unter der MIT-Lizenz (Datei `LICENSE`, Hinweis auf
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

- **Gemeinsame und persönliche Daten (#80–#83):** Begriffe enthalten nur noch gemeinsame Fachdaten.
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

- **Nutzung und Stabilität der API (#30):** Die API ist bis zum öffentlichen Betrieb eine Vorschau
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
