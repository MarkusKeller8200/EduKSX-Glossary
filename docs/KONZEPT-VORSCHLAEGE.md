# Konzept: Vorschläge – Kanal und Umgang mit GitHub-Issues

Stand: Entwurf 08.11.2026 zur Besprechung (#75, offener Entscheid 7 in [KONZEPT.md](KONZEPT.md)). Vorschlag
**DEC-024**. Grundlage für die Vorschlagsfunktion #113 (Release 0.7.0).

## 1. Ausgangslage

- Bis 0.6.0 war vorgesehen, dass Vorschläge über **GitHub-Issue-Formulare** laufen, bis die Vorschlagsfunktion da
  ist ([KONZEPT.md](KONZEPT.md) «Community»).
- Tatsächlich gibt es im Repository **keine Formulare für Begriffe**: `.github/ISSUE_TEMPLATE/` enthält nur «Fehler
  melden», «Neue Funktion / Idee» und «Verbesserung vorschlagen» (Software). Bisher ist **kein Begriffsvorschlag** als
  Issue eingegangen (Stand 08.11.2026).
- Die Startseite verlinkt «Begriff vorschlagen oder Fehler melden» auf `issues/new/choose`.
- Mit 0.7.0 können Konten mit Recht `propose` (ab Rolle Benutzer, DEC-017/#110) in der Anwendung Vorschläge
  einreichen; die Moderation (Recht `moderate`) prüft sie, angenommene Vorschläge werden eine neue Revision als
  Entwurf (DEC-022).

## 2. Varianten

| Variante                             | Beschreibung                                                                                                                 | Vorteile                                                                                              | Nachteile                                                                                                        |
| ------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| **A – nur im Glossary** (Empfehlung) | Begriffsvorschläge ausschliesslich in der Anwendung. GitHub bleibt für Software (Fehler, Funktionen, Verbesserungen).        | ein Kanal mit Status, Moderation, Basisrevision und Konfliktprüfung; Datenschutz nach eigenem Konzept | ohne Konto kein Vorschlag (Registrierung nötig, mit Double-Opt-in rasch erledigt)                                |
| B – parallel                         | Angemeldete im Glossary, Personen ohne Konto weiterhin über ein GitHub-Formular «Begriff»; die Redaktion überträgt von Hand. | niederschwellig ohne Konto                                                                            | zwei Kanäle, doppelte Arbeit, kein Status für Einreichende; GitHub-Datenschutz; externe Texte unvertrauenswürdig |
| C – Übernahme aus GitHub             | Issues mit Label «Begriff» automatisch als Vorschlag anlegen.                                                                | –                                                                                                     | keine bestätigte Identität, Texte unvertrauenswürdig (AGENTS.md), Personenbezug ausserhalb des Löschkonzepts     |

## 3. Vorschlag (Variante A)

1. **Kanal:** Begriffe werden nur im Glossary vorgeschlagen (neuer Begriff oder Korrektur). Ohne Anmeldung führt
   «Vorschlag einreichen» zur Anmeldung bzw. Registrierung und danach zurück zum Formular.
2. **Website:** Der Link auf der Startseite heisst neu «Begriff vorschlagen» und führt ins Vorschlagsformular; auf der
   Begriffsseite «Korrektur vorschlagen». Software-Meldungen («Fehler auf der Website melden») bleiben als kleiner
   Link zu GitHub in der Fusszeile.
3. **GitHub:** In `.github/ISSUE_TEMPLATE/config.yml` ein Kontakt-Link «Begriff vorschlagen oder korrigieren» auf
   `https://glossary.eduksx.ch/vorschlaege/neu`; die Software-Formulare erhalten den Hinweis «Für Begriffe bitte das
   Formular im Glossary verwenden».
4. **Eingehende Begriffs-Issues** (falls doch): freundlicher Kommentar mit Link aufs Formular, Label `begriff`,
   schliessen. Kein automatischer Import (Variante C). Die Redaktion kann einen wertvollen Hinweis selbst als
   Vorschlag erfassen – ohne Personenbezug der GitHub-Person.
5. **Bestehende Issues:** keine vorhanden; nichts zu übernehmen.
6. **Lesefassung auf GitHub Pages:** verlinkt «Begriff vorschlagen» ebenfalls auf das Formular im Glossary.
7. **Servicekonten:** Integrationen (Intranet, MCP) reichen Vorschläge mit Token und Scope `propose` ein (#47); sie
   landen in derselben Moderation.

## 4. Datenschutz und Löschfrist (für #113)

- Vorschläge sind nie öffentlich; öffentlich wird nur der freigegebene Inhalt als Revision.
- Gespeichert wird die einreichende Person als Bezug und die Bezeichnung als Text zum Zeitpunkt der Einreichung
  (Community-Name, sonst Name).
- **Löschfrist (Entscheid 08.11.2026):** Abgeschlossene Vorschläge (angenommen, abgelehnt, zurückgezogen) bleiben
  **24 Monate** mit Personenbezug, danach ohne Personenbezug («Community»); der Inhalt bleibt zur Nachvollziehbarkeit.
  Bei Kontolöschung sofort ohne Personenbezug (wie `Journal::forget`). Eintrag in [LOESCHKONZEPT.md](LOESCHKONZEPT.md)
  mit #113.

## 5. Zu entscheiden

- Variante A, B oder C (Empfehlung A).
- Wortlaut der Links auf Website und GitHub.
