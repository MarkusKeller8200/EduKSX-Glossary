# Mit KI recherchieren – Begriffe importkompatibel aufbereiten

Diese Anleitung hilft, mit einem KI-Assistenten (z. B. Claude, ChatGPT, Copilot) neue Begriffe für das
EduKSX-Glossary zu recherchieren und so aufzubereiten, dass die Datei **ohne Nacharbeit durch den Excel-Import**
läuft. Sie richtet sich an alle, die Begriffe beitragen – mit oder ohne Recht für den Import.

Die Spalten, Werte und Regeln unten im Abschnitt «Format» werden aus dem Code erzeugt
(`php artisan glossary:ki-anleitung`) und sind deshalb immer aktuell.

## 1. Ablauf in Kürze

1. **Bestand holen:** Die KI (oder du) lädt die bestehenden Begriffe, Kategorien und Herkünfte über die öffentliche
   API (Abschnitt 2). So entstehen keine Doppelungen und keine unbekannten Kategorien.
2. **Recherchieren und aufbereiten:** Mit dem Prompt aus Abschnitt 5. Ergebnis ist eine Tabelle im Format unten
   (Excel `.xlsx` oder CSV).
3. **Vorschau:** In der Administration «Excel-Import» die Datei hochladen und die Vorschau prüfen. Fehler in der Datei
   beheben und erneut hochladen.
4. **Übernehmen:** Importierte Begriffe sind **Entwürfe** mit dem Badge «KI-unterstützt recherchiert».
5. **Prüfen und veröffentlichen:** Eine Moderatorin bzw. ein Moderator prüft Inhalt und Quellen, bestätigt mit
   «Als geprüft bestätigen» und veröffentlicht. Erst dann sind die Begriffe öffentlich.

Ohne Recht für den Import: Begriffe einzeln über «Begriff vorschlagen» einreichen (gleiche Regeln für die Inhalte).

## 2. Vorher prüfen: was es schon gibt

| Was                 | Abfrage                                              | Wozu                                                                |
| ------------------- | ---------------------------------------------------- | ------------------------------------------------------------------- |
| Bestehende Begriffe | `GET https://glossary.eduksx.ch/api/v1/index`        | Doppelungen erkennen: Abkürzung, Bezeichnung und Aliase vergleichen |
| Kategorien          | `GET https://glossary.eduksx.ch/api/v1/categories`   | nur vorhandene Labels verwenden (`label`)                           |
| Herkunft            | `GET https://glossary.eduksx.ch/api/v1/sources`      | vorhandene Herkunft verwenden; neue nur bewusst                     |
| Einzelner Begriff   | `GET https://glossary.eduksx.ch/api/v1/terms/<Code>` | bestehenden Begriff ändern: ID übernehmen                           |

- **Doppelung:** Gibt es die Abkürzung oder die Bezeichnung (ohne Gross-/Kleinschreibung) schon, keinen neuen Begriff
  anlegen. Stattdessen die Zeile mit der **ID** des bestehenden Begriffs füllen (ändern) oder weglassen.
- **Gleiche Abkürzung, andere Bedeutung** (z. B. «SL» für Schulleitung und Schulleiterin): ist erlaubt, die
  Bezeichnung muss sich dann klar unterscheiden.
- **Unbekannte Kategorie oder Herkunft:** Der Import lehnt die Zeile ab, ausser beim Import ist «Listen neu anlegen»
  eingeschaltet. Neue Labels deshalb nur, wenn wirklich keine bestehende passt, und in der Antwort der KI ausdrücklich
  aufführen.

## 3. Regeln für die Inhalte

- **Kurzbeschreibung:** ein Satz, rund 110 Zeichen (höchstens 160).
- **Beschreibung:** ein bis drei Sätze, sachlich und neutral (kein «du», keine Werbung).
- **Schreibweise:** Schweizer Rechtschreibung, **kein ß** (immer «ss»), Anführungszeichen « ».
- **Offizielle Bezeichnung vor Abkürzung:** «Bezeichnung offiziell» ist die amtliche bzw. gebräuchliche Langform.
- **Englische oder amtliche Zweitnamen** in «Aliase offiziell», **Schreibvarianten** in «Alternative Schreibweisen»,
  **Suchwörter** (Tippfehler, frühere Namen) in «Suchbegriffe» – Mehrfachwerte immer mit «;» trennen.
- **Status:** «aktuell» für Begriffe in Gebrauch, «historisch» für abgelöste (mit «Gültig bis»), «zu prüfen», wenn die
  Recherche unsicher ist.
- **Recherche:** immer «KI-unterstützt». Nie «geprüft» – das setzt nur ein Mensch nach der Prüfung im Glossary.
- **Keine Personendaten:** keine Namen von Personen, keine E-Mail-Adressen, nur öffentliche Quellen.

## 4. Quellen und Urheberrecht

- Jede Zeile mit **Herkunft** (herausgebende Stelle) und, wenn vorhanden, **URL** der amtlichen Seite.
- Texte **selbst formulieren**, nie aus der Quelle kopieren. Kurze Fachbegriffe und amtliche Bezeichnungen dürfen
  übernommen werden.
- **Nutzungsbedingungen der Quelle prüfen.** Die Daten im Glossary stehen unter **CC BY 4.0**: Was importiert wird,
  muss sich unter dieser Lizenz weitergeben lassen. Bei Unklarheit die Quelle in der «Notiz» festhalten und den
  Begriff mit Status «zu prüfen» liefern.

## 5. Prompt zum Kopieren

Den folgenden Text in den KI-Assistenten kopieren und das Thema am Ende ergänzen. Assistenten mit Webzugriff laden den
Bestand selbst; sonst die Antworten der drei Abfragen aus Abschnitt 2 als Datei mitgeben.

<!-- prompt:anfang -->

```text
Du hilfst, Begriffe für das EduKSX-Glossary (Glossar für Schweizer Volksschulen, Schulgemeinden und Gemeinden) zu
recherchieren und importfertig aufzubereiten.

1. Lade den Bestand: https://glossary.eduksx.ch/api/v1/index (Begriffe), /api/v1/categories (Kategorien) und
   /api/v1/sources (Herkunft). Verwende nur vorhandene Kategorien und Herkünfte (Feld «label»). Schlage neue nur vor,
   wenn keine passt, und liste sie am Ende separat auf.
2. Prüfe jeden Begriff auf Doppelungen (Abkürzung, Bezeichnung, Aliase; ohne Gross-/Kleinschreibung). Doppelte nicht
   neu anlegen, sondern in einer separaten Liste mit dem vorhandenen Code nennen.
3. Erstelle eine Tabelle mit genau diesen Spalten in dieser Reihenfolge (erste Zeile = Spaltennamen):
   ID; Kategorien; Abkürzung offiziell; Aliase offiziell; Alternative Schreibweisen; Bezeichnung offiziell;
   Kurzbeschreibung; Beschreibung; URL; Notiz; Herkunft; Status; Suchbegriffe; Gültig ab; Gültig bis; Aktiv; Recherche
   - ID leer lassen (neuer Begriff).
   - Mehrfachwerte mit «; » trennen. Datum als JJJJ-MM-TT. Aktiv: ja. Status: aktuell, historisch oder zu prüfen.
   - Recherche: immer «KI-unterstützt».
   - Kurzbeschreibung ein Satz (rund 110, höchstens 160 Zeichen), Beschreibung 1–3 Sätze, neutral, kein «du».
   - Schweizer Rechtschreibung, kein ß, Anführungszeichen « ».
   - Offizielle Bezeichnung als «Bezeichnung offiziell», englische Namen als «Aliase offiziell», Schreibvarianten als
     «Alternative Schreibweisen».
   - Herkunft = herausgebende Stelle, URL = amtliche Seite. Texte selbst formulieren, nicht kopieren. In der Notiz die
     Quelle und das Abrufdatum angeben.
   - Keine Personendaten, nur öffentliche Quellen. Bei Unsicherheit Status «zu prüfen».
4. Gib die Tabelle als CSV (Trennzeichen Semikolon, UTF-8) oder als Excel-Datei aus, danach die Listen «Doppelungen»
   und «Neue Kategorien/Herkünfte».

Thema: <hier das Thema bzw. die Quelle eintragen>
```

<!-- prompt:ende -->

## 6. Format

<!-- generiert:anfang -->

Excel-Format Version 2 (`app/Glossary/ExcelFormat.php`). Erste Zeile = Spaltennamen genau wie unten;
Spalten dürfen fehlen (sie bleiben dann unverändert), die Reihenfolge ist frei. Zeilen **ohne ID** sind neue
Begriffe, Zeilen **mit ID** ändern den Begriff mit dieser ID. Gelöscht wird nie.

| Spalte                    | Format                                                                                | Beispiel                                 | Pflicht                    |
| ------------------------- | ------------------------------------------------------------------------------------- | ---------------------------------------- | -------------------------- |
| ID                        | UUID                                                                                  | 0195f6a2-7c1e-7a3b-9b2d-3f4e5a6b7c8d     | automatisch                |
| Kategorien                | Liste; Excel: Labels mit Strichpunkt                                                  | Personal; Schule                         | empfohlen                  |
| Abkürzung offiziell       | Text, höchstens 60 Zeichen                                                            | VSA                                      | Abkürzung oder Bezeichnung |
| Aliase offiziell          | Liste, mit Strichpunkt getrennt                                                       | VSA ZH; Volksschulamt Zürich             | nein                       |
| Alternative Schreibweisen | Liste, mit Strichpunkt getrennt                                                       | V.S.A.                                   | nein                       |
| Bezeichnung offiziell     | Text, höchstens 255 Zeichen                                                           | Volksschulamt                            | Abkürzung oder Bezeichnung |
| Kurzbeschreibung          | Text, höchstens 160 Zeichen                                                           | Kantonale Fachstelle für die Volksschule | empfohlen                  |
| Beschreibung              | Text, mehrzeilig                                                                      | Das Volksschulamt ist zuständig für …    | nein                       |
| URL                       | Adresse mit http:// oder https://                                                     | https://www.zh.ch/vsa                    | nein                       |
| Notiz                     | Text, mehrzeilig                                                                      | Bezeichnung mit VSA klären               | nein                       |
| Herkunft                  | Liste; Excel: Labels mit Strichpunkt                                                  | VSA                                      | empfohlen                  |
| Status                    | Excel: aktuell \| historisch \| zu prüfen; API: current \| historical \| under_review | aktuell                                  | ja (Vorgabe aktuell)       |
| Suchbegriffe              | Text, Wörter mit Strichpunkt getrennt                                                 | Schulamt; Volksschulbehörde              | nein                       |
| Gültig ab                 | Datum JJJJ-MM-TT (Excel auch TT.MM.JJJJ)                                              | 2026-08-01                               | nein                       |
| Gültig bis                | Datum JJJJ-MM-TT (Excel auch TT.MM.JJJJ)                                              | 2027-07-31                               | nein                       |
| Aktiv                     | ja \| nein                                                                            | ja                                       | ja (Vorgabe ja)            |
| Recherche                 | KI-unterstützt \| geprüft \| leer                                                     | KI-unterstützt                           | nein                       |

Übersetzungen (freiwillig): «Abkürzung (FR)», «Bezeichnung (FR)», «Kurzbeschreibung (FR)», «Beschreibung (FR)», «Abkürzung (IT)», «Bezeichnung (IT)», «Kurzbeschreibung (IT)», «Beschreibung (IT)», «Abkürzung (EN)», «Bezeichnung (EN)», «Kurzbeschreibung (EN)», «Beschreibung (EN)».

- **Mehrfachwerte** («;»): «Kategorien», «Aliase offiziell», «Alternative Schreibweisen», «Herkunft», dazu «Suchbegriffe».
- **Status:** aktuell | historisch | zu prüfen (leer = «aktuell»).
- **Aktiv:** ja | nein (leer = ja).
- **Recherche:** KI-unterstützt (der Import setzt nur diesen Wert; «geprüft» ändert nichts).
- **Datum:** JJJJ-MM-TT (auch TT.MM.JJJJ).
- **Offizielle Spalten** («Abkürzung offiziell», «Aliase offiziell», «Bezeichnung offiziell») bei bestehenden Begriffen nur mit dem Recht «Offizielle Felder ändern».
- CSV: UTF-8, Trennzeichen Semikolon, gleiche Spaltennamen.

<!-- generiert:ende -->

## 7. Vorlage und weitere Hilfe

- **Leere Vorlage:** https://glossary.eduksx.ch/mit-ki-recherchieren/vorlage.xlsx (alle Spalten, inkl. Übersetzungen).
- **Alle Felder erklärt:** Hilfe › Felder (https://glossary.eduksx.ch/hilfe).
- **API:** https://glossary.eduksx.ch/entwickler.
