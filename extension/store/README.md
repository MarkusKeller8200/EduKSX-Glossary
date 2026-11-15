# Browsererweiterung in den Stores (#386)

Texte, Bilder und Angaben für Chrome Web Store, Microsoft Edge Add-ons und Firefox Add-ons (AMO). Den Upload bei einem
neuen Release übernimmt `.github/workflows/extension.yml` (Job «stores», Skript `scripts/extension-stores.sh`). Einen
Store bedient der Workflow erst, wenn dessen Secrets eingetragen sind. Fehlen sie, überspringt er den Store mit einem
Hinweis.

## Einmalig durch Projektverantwortliche:n

| Store   | Konto                                                                                | Erste Version                                                                                                      |
| ------- | ------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------ |
| Chrome  | [Developer Dashboard](https://chrome.google.com/webstore/devconsole), einmalig 5 USD | ZIP von Hand hochladen, Angaben unten ausfüllen, zur Prüfung einreichen. Die API kann keine neuen Einträge anlegen |
| Edge    | [Partner Center](https://partner.microsoft.com/dashboard/microsoftedge), gratis      | ZIP von Hand hochladen, Angaben ausfüllen, einreichen                                                              |
| Firefox | [AMO Developer Hub](https://addons.mozilla.org/developers/), gratis                  | geht über den Workflow (Metadaten aus `amo-metadata.json`) oder von Hand                                           |

Danach Secrets bzw. Variablen im Repository eintragen (Settings › Secrets and variables › Actions). Am besten in der
Umgebung **`browser-stores`**: Dort lässt sich zusätzlich eine Freigabe vor jedem Upload verlangen.

| Store   | Secrets                                                            | Variablen (nicht geheim)                                                                 | Woher                                                                                                                                            |
| ------- | ------------------------------------------------------------------ | ---------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| Chrome  | `CHROME_CLIENT_ID`, `CHROME_CLIENT_SECRET`, `CHROME_REFRESH_TOKEN` | im Workflow eingetragen (übersteuerbar mit `CHROME_PUBLISHER_ID`, `CHROME_EXTENSION_ID`) | Google Cloud: «Chrome Web Store API» aktivieren, OAuth-Client, Refresh-Token über den OAuth Playground; Publisher-ID im Dashboard unter Settings |
| Edge    | `EDGE_CLIENT_ID`, `EDGE_API_KEY`                                   | `EDGE_PRODUCT_ID`                                                                        | Partner Center › Microsoft Edge › Publish API › «Create API credentials»                                                                         |
| Firefox | `AMO_JWT_ISSUER`, `AMO_JWT_SECRET`                                 | `AMO_LICENSE` (Lizenz der Version, **ZU PRÜFEN**)                                        | [API-Schlüssel](https://addons.mozilla.org/developers/addon/api/key/)                                                                            |

**ZU PRÜFEN – Lizenz für AMO:** AMO verlangt für jede gelistete Version eine Lizenz aus seiner Liste. Das Repository
steht unter `AGPL-3.0-or-later`, die Liste von AMO enthält aber nur `AGPL-3.0-only`. Mögliche Werte für `AMO_LICENSE`
sind `AGPL-3.0-only`, `MIT` (wie das Script zur Einbindung) oder eine eigene Lizenz von Hand im Developer Hub.

Ablauf bei jedem Release: Der Workflow hängt die ZIP an das Release (#385) und lädt sie danach in jeden eingerichteten
Store. Chrome und Edge reichen sie zur Prüfung ein und veröffentlichen sie danach. Firefox erhält zusätzlich den
Quellcode des Tags, weil der Code gebündelt und verkleinert ist. Betas erreichen die Stores nicht: Der Workflow nimmt
nur das neueste Release, und Betas sind Vorabversionen. Von Hand: Actions › extension › «Run workflow».

Chrome Web Store: Item `pglomjiinimdmmdfefchcghjahhkokmf`, öffentlich nach der ersten Prüfung unter
`https://chromewebstore.google.com/detail/pglomjiinimdmmdfefchcghjahhkokmf`.

## Angaben für die Store-Einträge (de)

**Name:** EduKSX-Glossary

**Kurzbeschreibung** (Chrome höchstens 132 Zeichen, AMO 250):

> Erklärt Abkürzungen und Begriffe aus Schule und Verwaltung direkt auf Webseiten. Die Suche läuft nur im Browser.

**Beschreibung:**

> Elternbrief, Lehrplan oder Verordnung voller Abkürzungen? EduKSX-Glossary erklärt Begriffe aus Schule, Bildung und
> Verwaltung direkt dort, wo sie stehen. Erkannte Abkürzungen werden fein gepunktet unterstrichen. Fährt man mit der Maus
> darüber oder springt mit der Tabulatortaste hin, erscheint eine kurze Erklärung mit Bezeichnung, Kurztext und Link ins
> Glossary.
>
> So funktioniert es:
> • Symbol der Erweiterung anklicken und «Auf dieser Seite hervorheben» wählen. Nochmals klicken schaltet aus.
> • Für Websites, die Sie oft besuchen, «Auf dieser Website immer» wählen.
> • In den Einstellungen lassen sich die erste Fundstelle, ausgeschriebene Bezeichnungen und Kategorien wählen.
>
> Datenschutz: Inhalte der besuchten Seiten verlassen den Browser nicht. Die Erweiterung lädt höchstens einmal täglich
> den öffentlichen Index von glossary.eduksx.ch und sucht lokal. Sie setzt keine Cookies, zeichnet nichts auf und
> braucht kein Konto.
>
> Die Begriffe stammen aus dem EduKSX-Glossary (glossary.eduksx.ch, Daten unter CC BY 4.0).

**Kategorie:** Chrome «Tools» bzw. «Bildung», Edge «Productivity», Firefox «Language Support» / «Other» – beim
Einreichen wählen.

**Sprache:** Deutsch (Schweiz). **Website:** `https://glossary.eduksx.ch/entwickler`. **Support:**
`https://glossary.eduksx.ch/hilfe`. **Datenschutzerklärung:** `https://glossary.eduksx.ch/datenschutz`.

**Bilder:** Store-Icon 128×128 aus `extension/icons/icon-128.png`. Screenshot 1280×800 in `screenshots/`. Er zeigt eine
Beispielseite mit Erklärbox; das Hervorheben sieht gleich aus wie in der Erweiterung. Neu aufnehmen: `beispielseite.html`
mit `public/embed/v1/glossary.js` und dem Index lokal ausliefern, dann `node screenshots/aufnahme.mjs <ziel.png>`
(braucht `puppeteer-core` und Chrome).

### Datenschutzangaben

**Einziger Zweck** (Chrome «Single purpose»): Begriffe und Abkürzungen auf Webseiten erkennen und erklären.

**Begründung der Berechtigungen:**

| Berechtigung                         | Begründung                                                                                                  |
| ------------------------------------ | ----------------------------------------------------------------------------------------------------------- |
| `storage`                            | Einstellungen und den zwischengespeicherten Index der Begriffe speichern                                    |
| `activeTab`                          | Nur nach einem Klick im Menü die aktuelle Seite durchsuchen, ohne dauernde Berechtigung                     |
| `scripting`                          | Das Script zum Hervorheben in die Seite laden, die der Nutzer gewählt hat                                   |
| Host `https://glossary.eduksx.ch/*`  | Öffentlichen Index der Begriffe laden (`/api/v1/index`)                                                     |
| optional `https://*/*`, `http://*/*` | Nur wenn der Nutzer «Auf dieser Website immer» wählt, und dann nur für diese Website; jederzeit widerrufbar |

**Remote Code:** nein, alles ist im Paket gebündelt (Manifest V3).

**Datennutzung** (Chrome «Data usage», Edge sinngemäss): Es werden **keine** Daten erhoben, also nichts ankreuzen. Die
Bestätigungen (kein Verkauf an Dritte, keine Nutzung ausserhalb des Zwecks, keine Kreditwürdigkeit) bestätigen.

**Firefox:** Das Manifest erklärt `data_collection_permissions: { required: ["none"] }`, gilt ab Firefox 140. Für die
Prüfung gilt: Quellcode im Repository, Build mit `npm ci && node scripts/build-extension.mjs` (Node 22). Dieser Hinweis
steht auch in `amo-metadata.json`.
