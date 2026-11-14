# Konzept: Begriffe einsammeln und bestehende prüfen

Stand: **Entwurf 15.11.2026**, Vorschlag **DEC-039**. Auftrag aus dem Chat (14.11.2026): «Konzept, wie wir neue
Glossary-Einträge einsammeln oder bestehende überprüfen können. Alles ist erlaubt; Umsetzung erst nach Freigabe.»
Ein Prototyp liegt im Branch `prototyp-einsammeln` (Abschnitt 8).

## 1. Ausgangslage

Was es heute gibt (0.14.0):

| Baustein                                        | Einsammeln                                                   | Prüfen                                     |
| ----------------------------------------------- | ------------------------------------------------------------ | ------------------------------------------ |
| Vorschläge im Glossary (DEC-024, #113)          | neue Begriffe und Korrekturen von Konten mit Recht `propose` | Moderation, Basisrevision, Konfliktprüfung |
| Vorschläge über die API (#47, #348)             | Intranet und andere Systeme mit Servicekonto                 | wie oben                                   |
| Suchen ohne Treffer (#285)                      | was gesucht und nicht gefunden wurde (90 Tage)               | –                                          |
| Excel-Import (#13, #111)                        | Bestand aus dem Intranet                                     | Vorschau vor der Übernahme                 |
| Prüfung der Herkunft (Administration › Prüfung) | –                                                            | Herkunft je Begriff mit «abgerufen am»     |
| Doppelte (Administration › Doppelte)            | –                                                            | ähnliche Begriffe zusammenführen           |
| Browser-Erweiterung und Einbindung (DEC-026)    | – (zeigt nur an)                                             | –                                          |
| Revisionen (DEC-022)                            | –                                                            | wer hat wann was geändert                  |

Es fehlt:

- ein **Kanal, der Begriffe aus Texten findet**, die Schulen täglich lesen (Weisungen, Elternbriefe, Lehrplan,
  Webseiten des Volksschulamts), ohne dass jemand jeden Begriff einzeln erfassen muss;
- eine **Sicht für die Redaktion**, die Kandidaten aus allen Kanälen zusammenführt, statt sie in mehreren Listen zu
  verteilen;
- ein **regelmässiger Prüfzyklus** für bestehende Begriffe: Heute fällt ein veralteter Begriff nur auf, wenn ihn
  jemand meldet.

## 2. Ziele

1. Fehlende Begriffe früh finden – dort, wo sie auftauchen.
2. Die Redaktion entscheidet; nichts wird ohne Menschen veröffentlicht (DEC-004, DEC-022 bleiben).
3. Bestehende Begriffe werden planbar geprüft (Fälligkeit, Zuständigkeit), nicht nur auf Zuruf.
4. Wenig zusätzlicher Aufwand für Lehrpersonen: Melden mit einem Klick, ohne Formularwüste.
5. Datenschutz: Texte, die jemand prüfen lässt, werden nicht gespeichert; keine Weitergabe an externe Dienste ohne
   Freigabe.

## 3. Einsammeln – Kanäle

| Nr. | Kanal                                             | So funktioniert es                                                                                                                                                                      | Aufwand | Nutzen | Datenschutz                                                   |
| --- | ------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------- | ------ | ------------------------------------------------------------- |
| E1  | **Text prüfen** (Redaktion)                       | Text einfügen oder Datei hochladen → Abkürzungen und Fachwörter werden erkannt, mit dem Bestand verglichen; unbekannte erscheinen als Kandidaten mit Fundstelle und vermuteter Langform | klein   | hoch   | Text nur im Speicher verarbeitet, nicht gespeichert           |
| E2  | **Text prüfen** (öffentlich)                      | gleiche Funktion für alle: «Welche Begriffe in meinem Text kennt das Glossary?» – erklärt bekannte Begriffe, meldet unbekannte (anonym gezählt) als Kandidaten                          | klein   | hoch   | wie E1; gemeldet wird nur das Kürzel, nicht der Text          |
| E3  | **Suchen ohne Treffer** als Wunschliste           | häufig gesuchte, fehlende Begriffe erscheinen als Kandidaten; öffentlich als «Gesucht» mit Möglichkeit, eine Erklärung vorzuschlagen                                                    | klein   | mittel | heute schon ohne Personenbezug                                |
| E4  | **«Begriff fehlt»** in Erweiterung und Einbindung | in der Browser-Erweiterung und in `<glossary-term>`/`<glossary-search>`: markiertes Wort → «Fehlt im Glossary» bzw. «Erklärung falsch»; landet als Kandidat bzw. Vorschlag              | mittel  | hoch   | nur Wort und Adresse der Seite (ohne Abfrageparameter)        |
| E5  | **Quellen beobachten**                            | Liste von Seiten (Volksschulamt, Lehrplan 21, Schulordnungen) wird wöchentlich abgerufen; neue Abkürzungen gegenüber dem letzten Abruf werden Kandidaten (E1 auf die geänderte Seite)   | mittel  | mittel | nur öffentliche Seiten                                        |
| E6  | **Intranet** über die API                         | «Begriff fehlt» im Intranet ruft `POST /api/v1/proposals` (mit Idempotency-Key, #348)                                                                                                   | klein   | mittel | Servicekonto, wie heute                                       |
| E7  | **Mail an glossary@**                             | Weiterleiten eines Textes an eine Adresse → E1 und Kandidaten an die Redaktion                                                                                                          | mittel  | klein  | Mail enthält oft Personendaten → nach Verarbeitung löschen    |
| E8  | **KI-Entwurf** (optional)                         | für einen Kandidaten mit Quelle einen Entwurf für Langform und Kurzbeschreibung erzeugen; immer nur Vorschlag, Quelle Pflicht                                                           | mittel  | hoch   | nur mit Freigabe (Anbieter, Vertrag); nur öffentliche Quellen |
| E9  | **Kampagnen**                                     | «Begriff der Woche», Sammelwoche je Schule, QR-Code auf Plakaten im Lehrerzimmer → Vorschlagsformular                                                                                   | klein   | mittel | –                                                             |

Alle Kanäle münden in **einen Eingang: Kandidaten** (Abschnitt 5).

## 4. Prüfen – Bausteine

| Nr. | Baustein                     | So funktioniert es                                                                                                                                                                              | Aufwand |
| --- | ---------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------- |
| P1  | **Prüfbedarf (Regeln)**      | feste Regeln je Begriff: keine Kurzbeschreibung, keine Herkunft, Herkunft älter als 24 Monate, gültig bis überschritten, seit 24 Monaten unverändert, keine Kategorie, Kurzbeschreibung zu lang | klein   |
| P2  | **Prüfintervall**            | je Begriff «geprüft am» und «nächste Prüfung» (Vorgabe 24 Monate, je Kategorie einstellbar); fällige Begriffe in einer Warteschlange; «geprüft, unverändert» ist eine Aktion ohne neue Revision | mittel  |
| P3  | **Zuständigkeit**            | Pate je Kategorie bzw. Herkunft (Konto mit Recht `edit-terms`); Fälligkeiten gehen an den Paten; Hinweis per Mail einmal im Monat                                                               | mittel  |
| P4  | **Quellen überwachen**       | Adressen der Herkunft regelmässig abrufen: nicht erreichbar → Prüfbedarf; Inhalt geändert (Prüfsumme) → Begriffe mit dieser Herkunft fällig                                                     | mittel  |
| P5  | **Rückmeldung der Lesenden** | auf der Begriffsseite «Hilfreich? ja/nein» und «Fehler melden» (→ Korrekturvorschlag); viele «nein» → Prüfbedarf                                                                                | klein   |
| P6  | **Nutzung**                  | Aufrufe je Begriff (ohne Personenbezug, wie Suchen ohne Treffer); häufig genutzte zuerst prüfen                                                                                                 | klein   |
| P7  | **Organisationen**           | Organisationen sehen ihre eigenen Darstellungen (Meine Darstellung) mit Prüfbedarf, wenn sich der zentrale Begriff geändert hat                                                                 | mittel  |
| P8  | **KI-Prüfung** (optional)    | Konsistenz (Langform passt zur Abkürzung, Widersprüche zwischen Sprachen), Vergleich mit der Quelle; nur Hinweis                                                                                | mittel  |

## 5. Ablauf

```
 Kanäle E1–E9 ──► Kandidaten ──► Sichtung (Redaktion) ──┬─► Entwurf eines Begriffs ──► Freigabe ──► veröffentlicht
                                                       ├─► Alias eines bestehenden Begriffs
                                                       └─► verworfen (mit Grund, wird nicht mehr vorgeschlagen)

 Prüfung P1–P8 ──► Prüfbedarf je Begriff ──► Pate prüft ──┬─► «geprüft, unverändert» (Datum)
                                                          └─► neue Revision als Entwurf ──► Freigabe
```

- **Kandidat** (neue Tabelle `term_candidates`): Wort bzw. Kürzel, vermutete Langform, Kanal, Anzahl Funde, erste
  und letzte Fundstelle (nur Adresse bzw. Kanal, nie der Text), Status `neu | übernommen | alias | verworfen`,
  Verweis auf den entstandenen Begriff. Verworfene bleiben gespeichert, damit sie nicht wieder auftauchen.
- **Prüfbedarf** wird nicht gespeichert, sondern aus den Regeln berechnet (P1); gespeichert werden nur
  `terms.reviewed_at` und `terms.review_due_at` (P2).
- Vorschläge von Konten (DEC-024) bleiben, wie sie sind; ein Kandidat ist die Vorstufe davor.

## 6. Stufen

| Stufe | Inhalt                                                                                  | Wann                     |
| ----- | --------------------------------------------------------------------------------------- | ------------------------ |
| 1     | E1 Text prüfen (Redaktion), E3 Wunschliste, P1 Prüfbedarf – **eine Seite «Einsammeln»** | nach Freigabe (Prototyp) |
| 2     | Kandidaten als Tabelle (verwerfen merken), P2 Prüfintervall mit «geprüft, unverändert»  | danach                   |
| 3     | E2 öffentlich, E4 Erweiterung und Einbindung, P5 Rückmeldung, P6 Nutzung                | mit #338 (Plugins)       |
| 4     | E5/P4 Quellen beobachten, P3 Paten mit Mail                                             | nach Stufe 2             |
| 5     | E8/P8 KI, E7 Mail                                                                       | nur mit Freigabe (O5)    |

## 7. Offene Entscheide

| Nr. | Frage                                                                        | Vorschlag                                                          |
| --- | ---------------------------------------------------------------------------- | ------------------------------------------------------------------ |
| O1  | Wer sieht «Einsammeln»: Recht `edit-terms` (Redaktion) oder auch `moderate`? | `edit-terms`                                                       |
| O2  | E2 öffentlich: ohne Anmeldung, mit Abrufbegrenzung?                          | ja, 10 Texte je Minute, höchstens 20 000 Zeichen                   |
| O3  | Prüfintervall: Vorgabe 24 Monate? je Kategorie anders?                       | 24 Monate, je Kategorie einstellbar                                |
| O4  | Paten je Kategorie oder je Herkunft?                                         | je Kategorie                                                       |
| O5  | KI-Unterstützung (E8/P8): ja? Anbieter (Vertrag, Standort Schweiz/EU)?       | erst nach Stufe 2 entscheiden                                      |
| O6  | Quellen beobachten (E5): welche Seiten am Anfang?                            | Volksschulamt ZH, Lehrplan 21 ZH, Volksschulgesetz und -verordnung |
| O7  | «Hilfreich? ja/nein» (P5): öffentlich sichtbar oder nur für die Redaktion?   | nur Redaktion                                                      |

## 8. Prototyp (Branch `prototyp-einsammeln`)

Zum Ausprobieren von Stufe 1, **ohne Datenbankänderung**:

- Administration › **Einsammeln** (Recht `edit-terms`) mit drei Bereichen:
    - **Text prüfen** (E1): Text einfügen → bekannte Begriffe (mit Verweis), unbekannte Kürzel als Kandidaten mit
      Anzahl, Fundstelle und vermuteter Langform aus Mustern wie «Schulische Heilpädagogik (SHP)» oder «SHP (Schulische
      Heilpädagogik)»; «Als Entwurf anlegen» öffnet das Begriffsformular vorausgefüllt.
    - **Gesucht** (E3): häufigste Suchen ohne Treffer, ebenfalls mit «Als Entwurf anlegen».
    - **Prüfbedarf** (P1): Regeln mit Anzahl und betroffenen Begriffen, Verweis zum Bearbeiten.
- Der Text wird nicht gespeichert und nicht protokolliert.

Testen: Branch lokal starten (DEPLOY.md, Abschnitt 1) und als Konto mit Recht `edit-terms` unter
`/admin/einsammeln` einen Elternbrief oder eine Seite des Volksschulamts einfügen.
