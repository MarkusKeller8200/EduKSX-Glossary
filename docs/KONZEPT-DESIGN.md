# Konzept: Auftritt (CI) und GUI-Bausteine

**Abgenommen** am 07.11.2026 (#9; DEC-015). Grundsatz des Projektverantwortlichen: Der **Auftritt** kommt anders als
im Intranet aus dem CI von MarKusSXCH; die **GUI-Bausteine** werden aus dem bestehenden Intranet übernommen und
nicht neu entwickelt.

## Zwei Quellen

| Was                                                       | Quelle (verbindlich)                                                                                                                               |
| --------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| Wortmarke, Markenfarben, Schrift, Signatur, Schreibweisen | [markussxch/docs](https://github.com/MarkusKeller8200/markussxch/tree/main/docs): CI-Manual v1.0, Markenfortschreibung v1.1, EduKSX-Produktfamilie |
| Bausteine, Verhalten, Barrierefreiheit, Theme-Technik     | sek-intranet: `resources/js/components` (40 GUI-Bausteine, `docs/GUI-KOMPONENTEN.md`), `resources/css/app.css`, `docs/KONZEPT-THEMES.md`           |

Das CI bestimmt **wie es aussieht**, das Intranet **was es ist und wie es sich bedient**. Bei Widerspruch gilt für
Farben und Marke das CI, für Bedienung und Barrierefreiheit das Intranet; Barrierefreiheit geht in beiden vor.

## GUI-Bausteine aus dem Intranet

- Übernommen werden die shadcn/Radix-Bausteine unter `components/ui/` (Button, Formular, Dialog, Tabs, Badge,
  Karte, Tooltip, Hover-Karte, Befehlspalette …), die **Tabelle** `data-grid` (Suche, Filter, Gruppierung,
  Bearbeiten in der Zelle), der Baustein **«Begriff»** (`term.tsx`, `term-text.tsx`), `theme-scope.tsx`, die
  Layouts (Seitenleiste, Kopf) und die Glossar-Seiten (`pages/glossary/*`) als Ausgangspunkt.
- Die Bausteine bleiben **farbneutral**: Farben nur über Farbwerte (`bg-primary`, `text-muted-foreground` …), nie
  fest im Code – so wie im Intranet vorgeschrieben. Darum genügt für das neue Aussehen ein eigener Satz Farbwerte.
- **Übernahme als Kopie mit Herkunft** (abgenommen): Jede übernommene Datei bekommt einen Kopfkommentar
  `Übernommen aus sek-intranet <Pfad> @ <Commit>`; eine Liste in `docs/UEBERNAHME-INTRANET.md` hält Datei,
  Version und Abweichung fest. Verbesserungen fliessen als Issue in beide Projekte. Später, wenn mehrere EduKSX-
  Projekte dieselben Bausteine nutzen (Codex), ein gemeinsames Paket prüfen (eigenes Repository als npm-Paket).
- **Vor der Übernahme:** Rechte- und Lizenzprüfung (Konzept, offener Entscheid 3), weil das Intranet privat ist
  und das Glossar öffentlich. Keine Namen, Texte oder Daten der Schule, kein SekBülach-Branding, keine
  Intranet-spezifischen Pfade oder Rechte in den übernommenen Dateien.

## Auftritt nach CI MarKusSXCH

### Wortmarke

- **EduKSX-Glossary** genau so geschrieben: `E` violett, `du` neutral, `K` orange, `SX` blau, `-Glossary` neutral
  (Markenfortschreibung v1.1).
- Neuer Baustein **`BrandWordmark`** nach dem Komponentenkontrakt des CI-Manuals (Abschnitt 10): Schlüssel
  `education` plus Produktzusatz, Segmente aus der YAML-Spezifikation, `aria-label` mit dem ganzen Namen, Segmente
  dekorativ, Farben nur über Tokens. Der einzige neue Baustein; er gehört in die GUI-Prototypen des Intranets
  zurückgemeldet.
- Fusszeile: «Developed by MarKusSXCH», Link auf markussx.ch/eduksx. Kein Arbeitgeber im öffentlichen Auftritt.
- Kein selbst erfundenes Favicon/Signet; bis zur Freigabe eines Signets ein neutrales Platzhalter-Favicon.

### Farben

Markenfarben gemäss CI; für Bedienelemente kontrastgeprüfte **funktionale** Töne (CI-Manual Abschnitt 2 und 5:
EduKSX hat Orange als primären UI-Akzent, Blau und Violett ergänzend). Kontraste nach WCAG berechnet.

| Farbwert (wie Intranet)            | Hell                              | Dunkel                            | Prüfung                                                                                   |
| ---------------------------------- | --------------------------------- | --------------------------------- | ----------------------------------------------------------------------------------------- |
| `--brand-violet/-orange/-blue`     | `#7952B3` / `#FF8A00` / `#007EBB` | `#B59AFF` / `#FF8A00` / `#32B8F2` | nur Wortmarke und Akzente                                                                 |
| `--background`, `--card`           | `#FFFFFF`                         | `#151B24`                         |                                                                                           |
| `--foreground`                     | `#222222`                         | `#F2F5F8`                         | 15.9 : 1                                                                                  |
| `--muted-foreground`               | `#5A6678`                         | `#AAB6C7`                         | 5.37 : 1 auf `--muted` / 6.96 : 1 – CI-Wert `#64748B` erreicht auf `--muted` nur 4.39 : 1 |
| `--primary` (Hauptknopf)           | `#FF8A00`                         | `#FF8A00`                         | Orange als Fläche                                                                         |
| `--primary-foreground`             | `#222222`                         | `#151B24`                         | 6.73 : 1 / 7.32 : 1 – **dunkle Schrift auf Orange**, nie weiss (2.36 : 1)                 |
| Links (`--link`), `--ring` (Fokus) | `#0272AD`                         | `#32B8F2`                         | 5.23 : 1 / 7.64 : 1                                                                       |
| `--term-underline`                 | `#7952B3`                         | `#B59AFF`                         | Linie, mind. 3 : 1                                                                        |
| Status (Erfolg, Warnung, Fehler)   | wie Intranet                      | wie Intranet                      | nicht durch Markenfarben ersetzen                                                         |

Befunde aus der Prüfung:

- **Orange `#FF8A00` auf Weiss** hat nur 2.36 : 1 – nie als Schrift oder als einziger Hinweis; als Knopffläche mit
  dunkler Schrift (6.73 : 1).
- **Blau `#007EBB` auf Weiss** liegt mit 4.47 : 1 knapp unter AA (4.5 : 1) für normalen Text. Für Links und
  Fliesstext darum der funktionale Ton `#0272AD` (5.23 : 1, gleicher Ton wie die Hauptfarbe im Intranet); `#007EBB`
  bleibt für die Wortmarke und grosse Schrift.
- **Sekundärtext `#64748B`** (CI: «ggf. prüfen») hat auf Weiss 4.76 : 1, auf der grauen Fläche `--muted` aber nur
  4.39 : 1; darum `#5A6678`.
- Shadcn-Variante «Link» des Buttons nutzt im Intranet `text-primary`; im Glossary `text-link` (Orange als Schrift
  wäre zu schwach).
- Die Warnfarbe des Intranets (`#a35a00`) liegt nahe am Marken-Orange; Warnungen immer mit Symbol und Text.

### Schrift und Formen

- Schrift **Inter** (CI), Fallback `system-ui, sans-serif`; selbst gehostet, kein Google-Fonts-Abruf. Wortmarke
  600–700, Fliesstext 400–500. Das Intranet nutzt Open Sans – nur dieser Farbwert (`--font-sans`) weicht ab.
- Radius, Abstände, Schatten und Grössen wie im Intranet (`--radius` usw.).
- Hell/Dunkel wie im Intranet (Klasse `dark`); die Wortmarke wechselt nur zwischen den zwei CI-Varianten.

### Seiten

- Kopf: Wortmarke EduKSX-Glossary, Suche, Hell/Dunkel; öffentlich ohne Seitenleiste, Redaktion mit Seitenleiste
  wie im Intranet.
- Hinweis «In Entwicklung», solange 0.x (Markenfortschreibung v1.1: keine erfundenen Releases oder Versprechen).

## Prüfung

- Abnahmecheckliste des CI-Manuals (Abschnitt 13) als Punkte in der PR-Vorlage bzw. als Test: exakte Wortmarke,
  Segmentfarben, hell/dunkel, Kontrast.
- Kontrastprüfung der Farbwerte automatisch in der CI (gleiche Paare wie im Farbkonzept des Intranets).
- **Umgesetzt (#63):** `tests/Unit/ContrastTest.php` prüft die Farbpaare aus `resources/css/app.css` hell und dunkel
  (Text ≥ 4.5 : 1, Linien und Fokus ≥ 3 : 1); die PR-Vorlage enthält die Punkte Wortmarke, Segmentfarben,
  hell/dunkel, Kontrast und Palette.
- **Farbkonzept (#77, #91, #92):** Palette aus dem Intranet (`resources/js/lib/colors.ts`). Abweichung: Der Eintrag
  `brand` nutzt hier `--info` (Blau), weil `--brand` im Glossary Orange ist und als Schrift nur 2.36 : 1 erreicht.
  Status: aktuell grün, zu prüfen orange (Warnung), historisch grau; veröffentlicht «V<Revision>» blau, Entwurf
  orange, zusammengeführt violett.

## Offen

- Gemeinsames Paket der Bausteine, sobald ein weiteres EduKSX-Projekt sie braucht (Codex).
- Signet/Favicon (CI: separat zu entwickeln und freizugeben).
- Welche Intranet-Bausteine als Nächstes übernommen werden (Tabelle, Formular, Import und Begriff) – mit den
  Issues der Etappe 0.2.
