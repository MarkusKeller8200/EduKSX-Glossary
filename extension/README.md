# Browser-Erweiterung EduKSX-Glossary (#153, DEC-026)

Erklärt Abkürzungen und Begriffe des Glossary auf beliebigen Webseiten: feine, gepunktete Unterstreichung, Erklärbox
mit Abkürzung, Bezeichnung, Kurztext und Link (Permalink) – per Maus und Tastatur. Manifest V3 für Chrome, Edge und
Firefox (ab 140).

## Datenschutz und Berechtigungen

- **Kein Seiteninhalt verlässt den Browser.** Die Erweiterung lädt nur den öffentlichen Index
  (`https://glossary.eduksx.ch/api/v1/index`, höchstens einmal täglich, ETag) und sucht lokal.
- Keine besuchten Adressen, keine Cookies, kein Tracking.
- Dauerhaft nur `storage`, `activeTab`, `scripting` und der Zugriff auf `glossary.eduksx.ch`.
- **Auf dieser Seite hervorheben:** nur mit `activeTab` (Klick im Menü), ohne dauernde Berechtigung; nochmals
  klicken schaltet aus.
- **Auf dieser Website immer:** Berechtigung nur für diese Website, jederzeit im Menü oder in den Einstellungen des
  Browsers widerrufbar.

## Bauen und testen

```bash
node scripts/build-extension.mjs   # → extension/dist und extension/eduksx-glossary-extension-<Version>.zip
```

Die Version kommt aus `VERSION` (App-Version, nur X.Y.Z; der Zusatz einer Beta steht in `version_name`). Die Version in
`extension/manifest.json` ist nur ein Platzhalter. Die Icons kommen beim Bauen aus `@eduksx/ui`
(`icons/png/<grösse>/eduksx-glossary-tile-dark.png`, Repo EduKSX-UI); das Repo hält keine eigenen Kopien.

**Herunterladen (#385):** Der Workflow `extension.yml` hängt die ZIP an jedes neue GitHub-Release
(`eduksx-glossary-extension-<Version>.zip` und `eduksx-glossary-extension.zip`); die Seite «Für Entwickler» verlinkt
`…/releases/latest/download/eduksx-glossary-extension.zip` mit einer Anleitung zum Installieren von Hand.

- **Chrome/Edge:** `chrome://extensions` → Entwicklermodus → «Entpackte Erweiterung laden» → `extension/dist`.
- **Firefox:** `about:debugging#/runtime/this-firefox` → «Temporäres Add-on laden» → `extension/dist/manifest.json`.

## Veröffentlichen (Projektverantwortliche Person)

Automatisch mit jedem Release, sobald die Store-Konten und Secrets eingerichtet sind (#386). Texte, Bilder,
Datenschutzangaben und die Einrichtung stehen in [`store/README.md`](store/README.md). Von Hand:

1. Version kommt mit dem Release (App-Version), bauen.
2. ZIP hochladen: [Chrome Web Store](https://chrome.google.com/webstore/devconsole),
   [Edge-Add-ons](https://partner.microsoft.com/dashboard/microsoftedge),
   [Firefox Add-ons](https://addons.mozilla.org/developers/) (ID `glossary@eduksx.ch`).
3. Angaben für die Prüfung: Zweck, Berechtigungen wie oben, Datenschutzerklärung `https://glossary.eduksx.ch/datenschutz`.

## Verteilung in Schulen

Über die Geräteverwaltung (z. B. Intune, Jamf, Google-Admin) mit der Store-ID installieren
(Chrome/Edge: Richtlinie `ExtensionInstallForcelist`). Erlaubte Websites können über
`ExtensionSettings` → `runtime_allowed_hosts` vorgegeben werden.

## Aufbau

| Datei               | Aufgabe                                                                |
| ------------------- | ---------------------------------------------------------------------- |
| `src/background.ts` | Index laden und zwischenspeichern, Inhaltsscript für Websites anmelden |
| `src/content.ts`    | Hervorheben auf der Seite (Kern `packages/highlighter`)                |
| `src/popup.ts`      | Menü: hervorheben, «immer», Einstellungen                              |
| `src/settings.ts`   | Einstellungen in `chrome.storage.sync`                                 |
| `test/`             | Tests (Manifest, Ursprünge), laufen mit `npm run test:js`              |

Später: Darstellung der eigenen Organisation mit Token (Scope `read-org`).
