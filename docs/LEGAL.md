# Rechtliches, Drittlizenzen und Code-Herkunft – EduKSX-Glossary

**Stand:** 07.11.2026 · **Status:** laufendes Lizenz- und Herkunftsregister, **keine vollständige rechtliche Freigabe**  
**Öffentliche Kurzfassung:** `/legals` (nach Deployment) · **Geltung:** Quellcode, installierte Pakete, kopierte Dateien, Assets, Fonts, Build-/Containerbestandteile und Glossary-Daten.

> Diese Übersicht dokumentiert den überprüften Ausgangspunkt. Ein NPM-/Composer-Manifest allein beweist keine vollständige Lizenzkonformität. Bei jedem Release müssen die tatsächlich aufgelösten Versionen, transitiven Abhängigkeiten, übernommenen Dateien und vollständigen Lizenzhinweise überprüft werden. Marken und Projektbezeichnungen verbleiben bei ihren jeweiligen Inhabern. Die Auflistung ist keine Empfehlung der genannten Hersteller.

## 1. Eigenständige Rechtebereiche

| Bereich                                                                                                  | Status / Regel                                                                                                                                                                                                                                                                                                                    |
| -------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| EduKSX-Glossary: eigener Anwendungscode                                                                  | **MIT** (DEC-021, Datei `LICENSE`). Beiträge Dritter fallen unter dieselbe Lizenz (CONTRIBUTING); Urheber- und Mitwirkendenrechte prüfen.                                                                                                                                                                                         |
| Glossary-Daten                                                                                           | **CC BY 4.0 vorläufig beschlossen** (DEC-016); nur eigene oder rechtmässig entsprechend lizenzierbare Texte. Quellenhinweise und Lizenzangabe bei Nachnutzung.                                                                                                                                                                    |
| Übernommener eigener Code aus `MarkusKeller8200/sek-intranet` und `MarkusKeller8200/my-github-blueprint` | **Eigene Quell-Repositories des Projektverantwortlichen.** Für selbst entwickelte Bestandteile ist keine zusätzliche Fremdcode-Lizenzfreigabe nötig. Zu prüfen sind ausschliesslich enthaltene oder daraus weiter übernommene **fremde Codeanteile, Bibliotheken, Vorlagen, Schriften und Assets** sowie deren Lizenzbedingungen. |
| Drittbibliotheken und kopierte Komponenten                                                               | Unter den jeweiligen Originalbedingungen; nicht durch spätere eigene Softwarelizenz ersetzen oder Einschränkungen auf fremden Code ausweiten.                                                                                                                                                                                     |
| Marke MarKusSXCH / EduKSX                                                                                | Marken-/Kennzeichnungsrechte getrennt von Software- und Datenlizenz behandeln.                                                                                                                                                                                                                                                    |

**Besonders wichtig:** Sollte für eigene Bestandteile eine nicht-kommerzielle Lizenz gewählt werden, darf dies die durch MIT, ISC, OFL oder andere Drittbedingungen eingeräumten Rechte nicht einschränken. Eine Lizenz mit generellem Verbot kommerzieller Nutzung ist keine OSI-anerkannte Open-Source-Lizenz.

## 2. Direkt übernommener oder kopierter Quellcode

| Herkunft / Repository                                                                                                  | Verwendete Bestandteile                                                                                                                                       | Rechtebasis / Pflichten                                                                                                                                                                                    | Prüfstatus                                                                           |
| ---------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| [MarkusKeller8200/sek-intranet](https://github.com/MarkusKeller8200/sek-intranet)                                      | Eigener Quellcode: UI-Komponenten, Hooks, CSS-Konzept, Middleware, Konfiguration, Docker-/CI-Dateien; Liste: [UEBERNAHME-INTRANET.md](UEBERNAHME-INTRANET.md) | Keine zusätzliche Lizenzfreigabe für eigene Anteile; **nur enthaltene Fremdkomponenten und deren Weitergabe-/Hinweispflichten überprüfen**. Herkunftspfad und Commit zur Nachvollziehbarkeit dokumentieren | **Eigenes Repository; Drittanteile prüfen**                                          |
| [shadcn-ui/ui](https://github.com/shadcn-ui/ui)                                                                        | Kopierte und angepasste Komponenten unter `resources/js/components/ui/`                                                                                       | **MIT**: Copyright- und vollständigen Erlaubnishinweis in Kopien/wesentlichen Teilen erhalten; [Original-Lizenz](https://github.com/shadcn-ui/ui/blob/main/LICENSE.md)                                     | **Lizenztyp verifiziert; Hinweise in übernommenen Dateien/Distribution noch prüfen** |
| [Laravel Application Skeleton](https://github.com/laravel/laravel) / [Framework](https://github.com/laravel/framework) | Typische Bootstrap-Dateien und Laravel-Grundgerüst; genaue Provenienz zwischen Framework, Skeleton und Intranet prüfen                                        | Laravel unter **MIT**; originale Copyright-/Lizenztexte für kopierte Teile beibehalten                                                                                                                     | **Provenienz pro Datei offen**                                                       |
| [MarkusKeller8200/my-github-blueprint](https://github.com/MarkusKeller8200/my-github-blueprint)                        | Eigene GitHub-Projektvorlagen / Workflow-Konfiguration gemäss README                                                                                          | Keine erneute Lizenzprüfung selbst entwickelter Vorlagen; **nur gegebenenfalls enthaltenen fremden Code oder fremde Vorlagen samt deren Lizenz- und Copyrightpflichten prüfen**                            | **Eigenes Repository; Drittanteile prüfen**                                          |

Die bestehende [Übernahmeliste](UEBERNAHME-INTRANET.md) dient der **technischen Herkunftsdokumentation**, nicht der erneuten Lizenzierung eigenen Codes. Bei Dateien aus den beiden eigenen Repositories wird die Herkunftskette nur so weit geprüft, bis fremde Bestandteile erkennbar sind. Für diese gelten die Original-Lizenzen samt Copyright- und Notice-Pflichten. Der in [KONZEPT-DESIGN.md](KONZEPT-DESIGN.md) vorgesehene Herkunftskommentar kann zur Nachvollziehbarkeit erhalten bleiben; bei shadcn-Komponenten ersetzt er keinesfalls die Originalurheberhinweise.

## 3. Eingebundene Frameworks und Bibliotheken (Auswahl)

**Quelle der Versionszuordnung:** [composer.json](../composer.json), [package.json](../package.json), die zugehörigen Lockfiles. Die folgende Liste ist **keine** vollständige transitive Stückliste; die genannte Lizenz ist nur dort als verifiziert markiert, wo die Originalquelle geprüft wurde.

| Projekt / Herkunft                                          | Rolle                                           | Lizenz / Hinweis                                                                                                   | Stand                                              |
| ----------------------------------------------------------- | ----------------------------------------------- | ------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------- |
| [Laravel Framework](https://github.com/laravel/framework)   | PHP-Framework                                   | MIT                                                                                                                | Upstream verifiziert; paketgenaue Version prüfen   |
| [React / React DOM](https://github.com/facebook/react)      | Benutzeroberfläche                              | MIT                                                                                                                | Upstream verifiziert; paketgenaue Version prüfen   |
| [shadcn/ui](https://github.com/shadcn-ui/ui)                | Kopierte UI-Komponenten                         | MIT                                                                                                                | Copyright und Lizenztext übernehmen                |
| [Radix UI](https://github.com/radix-ui/primitives)          | Interaktionsprimitive                           | Paketlizenzen aus Lockfile verifizieren                                                                            | Offen                                              |
| [Inertia.js](https://github.com/inertiajs/inertia)          | Frontend-Backend-Brücke                         | Paketlizenz verifizieren                                                                                           | Offen                                              |
| [Lucide](https://github.com/lucide-icons/lucide)            | Icons (`lucide-react`)                          | ISC, inklusive Copyright- und Erlaubnishinweis                                                                     | Upstream verifiziert; ausgelieferte Version prüfen |
| [Tailwind CSS](https://github.com/tailwindlabs/tailwindcss) | CSS                                             | Paketlizenz verifizieren                                                                                           | Offen                                              |
| [Vite Plus](https://github.com/voidzero-dev/vite-plus)      | Entwicklungs-/Build-Werkzeuge                   | Lizenz der tatsächlich installierten Pakete und Binärbestandteile prüfen                                           | Offen                                              |
| [Swagger UI](https://github.com/swagger-api/swagger-ui)     | Swagger-Seite der API (`swagger-ui-dist`, #157) | Apache-2.0 mit NOTICE; selbst ausgeliefert, NOTICE mitliefern                                                      | Paketlizenz verifiziert (npm 5.34)                 |
| [PHP/Composer- und NPM-Pakete](../composer.lock)            | Direkte und transitive Abhängigkeiten           | Jede aufgelöste Lizenz samt Ausnahmen/Copyright prüfen; [NPM Lockfile](../package-lock.json) ebenfalls einbeziehen | **Vollständiger Audit offen**                      |

Nicht jedes Entwicklungswerkzeug muss im laufenden Produkt enthalten sein. Die tatsächlichen **Verteilungsartefakte** (Repository, Docker-Image, Browser-Bundle, herunterladbare Dateien) bestimmen, welche Notices mitzuliefern sind.

## 4. Schriftarten, Assets und weitere Quellen

| Herkunft                                            | Verwendung                                                                                                        | Lizenzstatus                                                                                                                |
| --------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| [Inter – rsms/inter](https://github.com/rsms/inter) | In CSS vorgesehen; prüfen, ob Schriftdateien tatsächlich im Build enthalten sind oder über externe Dienste kommen | **SIL OFL 1.1**; bei Verteilung Font-Copyright und Lizenz beilegen; Reserved Font Name beachten                             |
| Lucide-Icons                                        | Darstellungen in Web-Bundle                                                                                       | **ISC**, erforderliche Hinweise mitliefern                                                                                  |
| Markenfarben, EduKSX-Wortmarke                      | Eigenes CI (MarKusSXCH)                                                                                           | Nicht als allgemeine MIT-Freigabe ausgeben                                                                                  |
| Daten / Beschreibungen aus offiziellen Quellen      | Quellenangaben, Definitionen                                                                                      | Fakten/Bezeichnungen von kopierten schutzfähigen Texten trennen. Fremdtexte nicht ungeprüft unter CC BY 4.0 veröffentlichen |
| Externe CDNs (Scalar/jsDelivr/Bunny Fonts)          | keine: Scalar (MIT) wird als npm-Paket gebündelt, die Schrift Inter (SIL OFL 1.1) beim Build eingebunden (#44)    | Lizenzhinweise der gebündelten Pakete mitliefern                                                                            |

## 5. Erforderliche Lizenzhinweise und Veröffentlichung

1. **In GitHub:** Softwarelizenz für die eigenen EduKSX-Glossary-Anteile festlegen und als `LICENSE` veröffentlichen; dabei Rechte Dritter aus übernommenen Fremdkomponenten unverändert respektieren. Keine Lizenzbehauptung ohne Beschluss.
2. **Bei kopierten Komponenten:** vollständige ursprüngliche MIT-Hinweise, Copyright-Zuordnung und Herkunft beibehalten; zentrale `THIRD_PARTY_NOTICES` plus gegebenenfalls Lizenzdateien im verteilten Artefakt vorsehen.
3. **Bei Paketdistribution:** alle tatsächlich enthaltenen Paketlizenzen aus `composer.lock` und `package-lock.json` mit Version, SPDX-Ausdruck, Copyrightinhaber, Quell-URL und Volltext/mitgeliefertem Lizenzort erfassen; Ausnahmen und Copyleft gesondert prüfen.
4. **Bei Schrift- und Asset-Verteilung:** OFL-/ISC- bzw. sonstige Hinweise entsprechend Originalbedingungen mitliefern.
5. **Bei öffentlichen Glossary-Daten:** Name der Datenlizenz, Lizenzlink, Quelle «EduKSX-Glossary, glossary.eduksx.ch» und erforderliche Änderungskennzeichnung bereitstellen.
6. **Auf Website:** `/legals` verweist auf diese Dokumentation; getrennte Seiten zu Datenschutz und Betreiberangaben erst nach tatsächlicher rechtlicher Prüfung als final ausweisen.

Ein blosser Link zur Upstream-Lizenz ersetzt **nicht automatisch** die Pflicht zur Mitlieferung des Lizenztexts bzw. Copyright-Hinweises, wenn dieser für die konkrete Verteilung vorgeschrieben ist.

## 6. Abnahmekriterien vor öffentlicher Quellcode-/Produktdistribution

- [x] `sek-intranet` und `my-github-blueprint` sind vom Projektverantwortlichen als eigene Quell-Repositories bezeichnet.
- [ ] Bei übernommenen Dateien dieser Repositories **fremde** Bestandteile (einschliesslich kopierter UI-Komponenten, Boilerplates, Assets und weiterer Quellen) identifiziert und deren jeweilige Lizenz-/Copyrightpflichten geprüft.
- [ ] Technische Herkunft über Quellpfad/Commit dokumentiert, soweit zur Verfolgung solcher Fremdanteile nötig.
- [ ] MIT-Originalhinweise der shadcn-Komponenten und weiterer Kopien korrekt beibehalten.
- [ ] Softwarelizenz für **eigene** Anteile beschlossen, ohne Drittkomponenten unzulässig umzuzertifizieren.
- [ ] Automatischer Composer-/NPM-Lizenzreport mit vollständigen direkten und transitiven Paketen pro Release erstellt.
- [ ] Browser-Bundle, Docker-Image und mitgelieferte Assets auf Lizenzhinweise und unverträgliche Lizenzen geprüft.
- [ ] Tatsächlich eingesetzte Schriftdateien samt OFL-Text und zulässigen Schriftbezeichnungen geprüft.
- [ ] CC BY 4.0 nur auf rechtmässig entsprechend lizenzierbare Glossary-Inhalte angewandt.
- [ ] Öffentliche Legal-Seite und Notice-Dateien zeigen dieselbe geprüfte Version.
- [ ] Fachliche bzw. erforderlichenfalls juristische Freigabe protokolliert.

**Pflege:** Eigene Codeübernahmen aus `sek-intranet` und `my-github-blueprint` erfordern keine erneute Eigenlizenzprüfung. Bei darin enthaltenen fremden Bestandteilen, neuen Abhängigkeiten, Copy/Paste aus fremden Repositories, Assets oder einer neuen Release-Version ist dieses Register vor Veröffentlichung zu aktualisieren. Lizenzscanner unterstützen die Prüfung, ersetzen sie aber nicht.

## Lizenzbericht der Pakete (#73)

`scripts/license-report.mjs` listet alle Composer- und npm-Pakete der Laufzeit (direkt und transitiv) mit Lizenz.
Die CI erzeugt den Bericht bei jeder Prüfung und warnt bei starkem Copyleft, nicht-kommerziellen oder fehlenden
Lizenzangaben. Jedes Release erhält ihn als Anhang `license-report.md` (Workflow `license-report`, täglich bzw. von Hand). Offen: den
Lizenztext der SIL OFL 1.1 für die Schrift Inter als Datei beilegen.

## Softwarelizenz (DEC-021)

**Status: beschlossen am 07.11.2026 – MIT (#65),** Datei [`LICENSE`](../LICENSE). Gilt nur für den eigenen Anwendungscode. Drittkomponenten behalten ihre
Lizenz, die Daten bleiben unter CC BY 4.0 (DEC-016), Marke und Wortmarke MarKusSXCH/EduKSX sind nicht Teil der
Softwarelizenz.

| Option              | Kurz                                                                                                         | Passt, wenn …                                                                                                 | Nachteil                                                                                                     |
| ------------------- | ------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------ |
| **MIT** (empfohlen) | Freie Nutzung, Änderung, Weitergabe; nur Copyright- und Lizenzhinweis behalten                               | Schulen, Gemeinden und Dienstleister den Code ohne Hürden übernehmen sollen; gleich wie Laravel und shadcn/ui | Änderungen an betriebenen Kopien müssen nicht zurückfliessen; kein ausdrückliches Patentrecht                |
| Apache-2.0          | wie MIT, dazu ausdrückliche Patentlizenz und NOTICE-Datei                                                    | Patentfragen eine Rolle spielen oder grössere Organisationen beitragen                                        | etwas mehr Pflichten (Änderungsvermerk, NOTICE)                                                              |
| AGPL-3.0-or-later   | starkes Copyleft auch beim Betrieb übers Netz: wer eine geänderte Fassung anbietet, muss den Code offenlegen | Verbesserungen gehosteter Varianten zwingend öffentlich werden sollen                                         | schreckt kommerzielle und manche öffentliche Betreiber ab; eigene kommerzielle Angebote nur mit Doppellizenz |

**Begründung:** Das Glossary soll als offene Referenz von möglichst vielen Schulen, Gemeinden und
Anbietern genutzt werden; der Wert liegt in den gemeinsam gepflegten Daten (CC BY 4.0) und im Betrieb, nicht in der
Exklusivität des Codes. MIT ist mit allen eingesetzten Paketen verträglich (Bericht #73) und für öffentliche Stellen
leicht zu prüfen.

**Umgesetzt:** Datei `LICENSE` (MIT, Copyright Markus Keller), `license` in `composer.json` und `package.json`,
Hinweis auf `/legals` und in der README.
