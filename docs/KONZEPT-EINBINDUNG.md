# Konzept Einbindung: Permalink, Renderer, Browser-Erweiterung (DEC-026, Entwurf)

Stand: Entwurf 08.11.2026 zur Abnahme (#153, #154, #155). Umsetzung erst nach Freigabe.

## Ziel

Begriffe des Glossary dort erklären, wo sie gelesen werden: auf beliebigen Webseiten (Browser-Erweiterung) und auf
Webseiten Dritter, die das Glossary bewusst einbinden (Renderer für Entwickler). Beide nutzen denselben Kern und
verlinken über einen kurzen, unveränderlichen Permalink.

```text
            ┌──────────────────────────────┐
            │ Glossary: Permalink /b/{code}│
            │ API /api/v1/index (kompakt)  │
            └──────────────┬───────────────┘
                           │ öffentliche Daten (CC BY 4.0), ETag
            ┌──────────────┴───────────────┐
            │ Kern «glossary-highlighter»  │  suchen, hervorheben, Erklärbox
            └───────┬──────────────┬───────┘
                    │              │
     Browser-Erweiterung      Einbindung für Entwickler
     (Chrome, Edge, Firefox)  (<script>, <glossary-term>)
```

## 1. Permalink je Begriff (#154)

**Heute:** `/begriffe/{slug}`. Der Slug entsteht beim Anlegen aus der Bezeichnung und bleibt danach gleich, auch
wenn die Bezeichnung korrigiert wird (dann passt er nicht mehr). Er ist oft lang
(`/begriffe/zwei-faktor-authentifizierung`); die UUID ist noch länger.

**Vorschlag:** Jeder Begriff erhält einen **Code mit 6 Zeichen** (Crockford-Base32 ohne verwechselbare Zeichen,
zufällig, eindeutig, nie wiederverwendet), z. B. `https://glossary.eduksx.ch/b/7KQ2MX`.

- Unveränderlich, unabhängig von Bezeichnung und Slug; die Slug-Adresse bleibt als lesbare Adresse erhalten.
- Zusammengeführte Begriffe: Code leitet (301) auf den verbleibenden Begriff; zurückgezogene: 404 wie heute.
- In API (`permalink`), Export, Detailseite («Link kopieren»), PDF und GitHub Pages.
- Vorhandene Begriffe erhalten den Code per Migration.

**Bringt es Sicherheit?** Teilweise – vor allem **Verlässlichkeit**, kaum Vertraulichkeit (die Inhalte sind ohnehin
öffentlich):

| Wirkung                       | Bewertung                                                                                                  |
| ----------------------------- | ---------------------------------------------------------------------------------------------------------- |
| Links bleiben gültig          | ja: Umbenennen, Korrektur der Bezeichnung oder Zusammenführen brechen keine Links                          |
| Keine Benutzereingabe im Pfad | ja: Erweiterung und Renderer bauen Links nur aus `https://glossary.eduksx.ch/b/` + Code nach festem Muster |
| Prüfbarkeit                   | ja: `^[0-9A-HJKMNP-TV-Z]{6}$` – alles andere wird verworfen (kein offener Redirect, keine Injektion)       |
| Erraten/Aufzählen             | ohne Bedeutung: nur veröffentlichte Begriffe sind erreichbar                                               |
| Kürzer                        | ja: 33 statt oft 50–70 Zeichen; gut für QR-Codes, Druck und die Erklärbox                                  |

Alternative: fortlaufende Nummer (`/b/123`) – noch kürzer, aber verrät die Reihenfolge und Anzahl; nicht empfohlen.

## 2. Kern «glossary-highlighter»

Eine TypeScript-Bibliothek, gemeinsam für Erweiterung und Einbindung (eigener Ordner `packages/highlighter/`).

- **Daten:** kompakter Index `GET /api/v1/index` (neu): je Begriff Code, Abkürzung, Bezeichnung, alternative
  Schreibweisen, Kurztext, mehrdeutig, Kategorie-Labels. Mit ETag; Stand im Browser zwischengespeichert,
  höchstens einmal täglich neu geladen (bzw. nach `X-Glossary-Release`).
- **Suche lokal im Browser:** Textknoten per `TreeWalker`; übersprungen werden `script`, `style`, `code`, `pre`,
  Formularfelder, `contenteditable`, Links auf das Glossary und bereits markierte Stellen. Abkürzungen nur mit
  exakter Gross-/Kleinschreibung und an Wortgrenzen (`SL`, nicht «sl» in «Slogan»), Bezeichnungen ohne
  Gross-/Kleinschreibung; längster Treffer zuerst (Aho-Corasick bzw. ein sortierter regulärer Ausdruck).
- **Leistung:** in Abschnitten über `requestIdleCallback`, `MutationObserver` mit Verzögerung für nachgeladene
  Inhalte, Obergrenze je Seite (z. B. 300 Treffer), Option «nur erste Fundstelle je Begriff».
- **Hervorhebung:** fein gepunktete Unterstreichung in der Textfarbe, kein Hintergrund, keine Änderung des Layouts;
  `<span>` um den Treffer, Stil in einem Shadow DOM bzw. mit eindeutigem Präfix.
- **Erklärbox:** bei Hover **und** Tastaturfokus (WCAG 2.1: auch per Tab erreichbar, mit Esc schliessbar, bleibt
  offen, solange die Maus darüber ist): Abkürzung, Bezeichnung, Kurztext, Link «Im Glossary öffnen» (Permalink),
  Quellenhinweis «EduKSX-Glossary, CC BY 4.0». Mehrdeutig: alle Bedeutungen untereinander.
- **Sicherheit:** Inhalte nur über `textContent`, nie `innerHTML`; Links nur zum festen Ursprung des Glossary mit
  geprüftem Code; keine Cookies, kein Tracking.

## 3. Browser-Erweiterung (#153)

- **Plattform:** WebExtension mit Manifest V3 – ein Quellcode für Chrome, Edge und Firefox (Safari später).
- **Datenschutz:** **Kein Seiteninhalt verlässt den Browser.** Die Erweiterung lädt nur den öffentlichen Index; die
  Suche läuft lokal. Keine Protokollierung besuchter Seiten.
- **Berechtigungen:** standardmässig nur `activeTab` und `storage`: Hervorheben per Klick auf das Symbol bzw.
  «Auf dieser Website immer aktiv» (optionale Host-Berechtigung je Website). «Auf allen Websites» nur auf
  ausdrücklichen Wunsch. Das erleichtert die Prüfung in den Stores und das Vertrauen der Schulen.
- **Einstellungen:** ein/aus, Websites erlauben/ausschliessen, nur Abkürzungen oder auch Bezeichnungen, Kategorien,
  nur erste Fundstelle.
- **Verteilung:** Chrome Web Store, Edge-Add-ons, Firefox Add-ons. Schulen können die Erweiterung über ihre
  Geräteverwaltung (z. B. Intune, Jamf, Google-Admin) verteilen und vorkonfigurieren (Richtlinie mit
  erlaubten Websites).
- **Später (Phase 4):** Anmeldung mit Token der Organisation (Scope `read-org`), damit die Erweiterung die
  Darstellung der eigenen Organisation zeigt (DEC-025).
- **Grenzen:** Seiten mit sehr dynamischem Inhalt, PDFs im Browser-Viewer und `iframe` fremder Herkunft werden
  nicht oder nur teilweise erfasst.

## 4. Einbindung für Entwickler (#155)

Drei Wege, von einfach bis kontrolliert:

1. **Automatisch** mit einer Zeile:

    ```html
    <script
        src="https://glossary.eduksx.ch/embed/v1/glossary.js"
        integrity="sha384-…"
        crossorigin="anonymous"
        defer
        data-scope="#inhalt"
        data-first-only="true"
    ></script>
    ```

    Sucht nur im angegebenen Bereich, sonst wie die Erweiterung.

2. **Ausdrücklich markiert** (Web Component, ohne automatische Suche):

    ```html
    <glossary-term code="7KQ2MX">SL</glossary-term>
    ```

    Gut für Redaktionssysteme: Die Autorin entscheidet, was erklärt wird.

3. **Nur Daten:** die bestehende API (`/api/v1/terms`, neu `/api/v1/index`) – wer eine eigene Darstellung baut.

- **Versioniert und unveränderlich:** `/embed/v1/glossary.js` ändert sich nur kompatibel; jede Version zusätzlich
  unter `/embed/v1.2.0/glossary.js` mit SRI-Hash (Anleitung zeigt ihn an). Keine Cookies, kein Tracking.
- **Technik bei uns:** CORS für `api/*` ist bereits offen (nur lesen); für `/embed/*` statische Dateien mit langer
  Zwischenspeicherung. `frame-ancestors` bleibt `'self'` (kein `iframe` nötig).
- **Dokumentation:** Seite «Für Entwickler» mit Beispiel, Optionen, Gestaltung per CSS-Variablen, CSP-Hinweis
  (`script-src https://glossary.eduksx.ch`, `connect-src https://glossary.eduksx.ch`) und Lizenzhinweis.
- **Kein Server-Renderer:** Ein Endpunkt, der fremde Texte entgegennimmt und markiert zurückgibt, würde Inhalte
  Dritter an uns senden (Datenschutz, Last). Nicht vorgesehen; bei Bedarf ein eigenes Paket für Node/PHP mit
  demselben Kern.

## Entscheide (zur Abnahme)

| Nr. | Vorschlag                                                                                            |
| --- | ---------------------------------------------------------------------------------------------------- |
| E1  | Permalink `/b/{code}`: 6 Zeichen Crockford-Base32, zufällig, unveränderlich; Slug-Adresse bleibt     |
| E2  | Neuer kompakter Index `GET /api/v1/index` mit ETag für Erweiterung und Renderer                      |
| E3  | Gemeinsamer Kern in TypeScript (`packages/highlighter/`), MIT-Lizenz                                 |
| E4  | Suche nur lokal im Browser; kein Seiteninhalt an das Glossary                                        |
| E5  | Erweiterung mit Manifest V3, Standard `activeTab` + Freigabe je Website; Chrome, Edge, Firefox       |
| E6  | Erklärbox per Hover und Tastatur, mit Permalink und Quellenhinweis CC BY 4.0                         |
| E7  | Einbindung per Script (automatisch im Bereich) und `<glossary-term>`; versioniert, SRI, ohne Cookies |
| E8  | Darstellung der Organisation in der Erweiterung erst in einer späteren Phase (Token `read-org`)      |

## Reihenfolge

1. Permalink und kompakter Index (Glossary, klein) – #154
2. Kern und Einbindung für Entwickler mit Testseite – #155
3. Browser-Erweiterung, Veröffentlichung in den Stores – #153
4. Organisation in der Erweiterung (optional)
