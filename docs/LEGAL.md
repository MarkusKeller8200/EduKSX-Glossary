# Rechtliches, Drittlizenzen und Code-Herkunft – EduKSX-Glossary

**Stand:** 07.11.2026 · **Status:** laufendes Lizenz- und Herkunftsregister, **keine vollständige rechtliche Freigabe**  
**Öffentliche Kurzfassung:** `/legals` (nach Deployment) · **Geltung:** Quellcode, installierte Pakete, kopierte Dateien, Assets, Fonts, Build-/Containerbestandteile und Glossary-Daten.

> Diese Übersicht dokumentiert den überprüften Ausgangspunkt. Ein NPM-/Composer-Manifest allein beweist keine vollständige Lizenzkonformität. Bei jedem Release müssen die tatsächlich aufgelösten Versionen, transitiven Abhängigkeiten, übernommenen Dateien und vollständigen Lizenzhinweise überprüft werden. Marken und Projektbezeichnungen verbleiben bei ihren jeweiligen Inhabern. Die Auflistung ist keine Empfehlung der genannten Hersteller.

## 1. Eigenständige Rechtebereiche

| Bereich | Status / Regel |
| --- | --- |
| EduKSX-Glossary: eigener Anwendungscode | **Softwarelizenz noch nicht festgelegt.** Eine öffentliche GitHub-Ablage ersetzt keine ausdrückliche Nutzungslizenz. Urheber- und Mitwirkendenrechte prüfen. |
| Glossary-Daten | **CC BY 4.0 vorläufig beschlossen** (DEC-016); nur eigene oder rechtmässig entsprechend lizenzierbare Texte. Quellenhinweise und Lizenzangabe bei Nachnutzung. |
| Übernommener Code aus `MarkusKeller8200/sek-intranet` | **Rechteklärung offen**: Inhaberschaft, Arbeits-/Auftragsverhältnisse, Drittlizenzen und tatsächliche Dateiprovenienz prüfen. Keine automatische Gleichsetzung von gleichem GitHub-Account mit ausschliesslichen Nutzungsrechten. |
| Drittbibliotheken und kopierte Komponenten | Unter den jeweiligen Originalbedingungen; nicht durch spätere eigene Softwarelizenz ersetzen oder Einschränkungen auf fremden Code ausweiten. |
| Marke MarKusSXCH / EduKSX | Marken-/Kennzeichnungsrechte getrennt von Software- und Datenlizenz behandeln. |

**Besonders wichtig:** Sollte für eigene Bestandteile eine nicht-kommerzielle Lizenz gewählt werden, darf dies die durch MIT, ISC, OFL oder andere Drittbedingungen eingeräumten Rechte nicht einschränken. Eine Lizenz mit generellem Verbot kommerzieller Nutzung ist keine OSI-anerkannte Open-Source-Lizenz.

## 2. Direkt übernommener oder kopierter Quellcode

| Herkunft / Repository | Verwendete Bestandteile | Rechtebasis / Pflichten | Prüfstatus |
| --- | --- | --- | --- |
| [MarkusKeller8200/sek-intranet](https://github.com/MarkusKeller8200/sek-intranet) | UI-Komponenten, Hooks, CSS-Konzept, Middleware, Konfiguration, Docker-/CI-Dateien und weitere Grundlagen; genaue Liste: [UEBERNAHME-INTRANET.md](UEBERNAHME-INTRANET.md) | Originalurheberschaft und Berechtigung für öffentliche Weitergabe **pro Dateigruppe** nachweisen; enthaltene Fremdanteile separat lizenzieren; Herkunfts-Commit und Änderungen ergänzen | **OFFEN – vor Freigabe klären** |
| [shadcn-ui/ui](https://github.com/shadcn-ui/ui) | Kopierte und angepasste Komponenten unter `resources/js/components/ui/` | **MIT**: Copyright- und vollständigen Erlaubnishinweis in Kopien/wesentlichen Teilen erhalten; [Original-Lizenz](https://github.com/shadcn-ui/ui/blob/main/LICENSE.md) | **Lizenztyp verifiziert; Hinweise in übernommenen Dateien/Distribution noch prüfen** |
| [Laravel Application Skeleton](https://github.com/laravel/laravel) / [Framework](https://github.com/laravel/framework) | Typische Bootstrap-Dateien und Laravel-Grundgerüst; genaue Provenienz zwischen Framework, Skeleton und Intranet prüfen | Laravel unter **MIT**; originale Copyright-/Lizenztexte für kopierte Teile beibehalten | **Provenienz pro Datei offen** |
| [MarkusKeller8200/my-github-blueprint](https://github.com/MarkusKeller8200/my-github-blueprint) | GitHub-Projektvorlagen / Workflow-Konfiguration gemäss README | Lizenz und ursprüngliche Herkunft tatsächlich kopierter Dateien prüfen; eigene Projektanpassungen dokumentieren | **OFFEN** |

Die bestehende [Übernahmeliste](UEBERNAHME-INTRANET.md) ist verbindlicher Einstieg. Darin genannte Kopien sind **keine pauschale rechtliche Freigabe**. Der in [KONZEPT-DESIGN.md](KONZEPT-DESIGN.md) geforderte Dateikopf mit Quellpfad und Commit ist anhand der realen Dateien zu prüfen; bei shadcn-Komponenten dürfen Originalurheberhinweise nicht durch reine Intranet-Herkunftsangaben ersetzt werden.

## 3. Eingebundene Frameworks und Bibliotheken (Auswahl)

**Quelle der Versionszuordnung:** [composer.json](../composer.json), [package.json](../package.json), die zugehörigen Lockfiles. Die folgende Liste ist **keine** vollständige transitive Stückliste; die genannte Lizenz ist nur dort als verifiziert markiert, wo die Originalquelle geprüft wurde.

| Projekt / Herkunft | Rolle | Lizenz / Hinweis | Stand |
| --- | --- | --- | --- |
| [Laravel Framework](https://github.com/laravel/framework) | PHP-Framework | MIT | Upstream verifiziert; paketgenaue Version prüfen |
| [React / React DOM](https://github.com/facebook/react) | Benutzeroberfläche | MIT | Upstream verifiziert; paketgenaue Version prüfen |
| [shadcn/ui](https://github.com/shadcn-ui/ui) | Kopierte UI-Komponenten | MIT | Copyright und Lizenztext übernehmen |
| [Radix UI](https://github.com/radix-ui/primitives) | Interaktionsprimitive | Paketlizenzen aus Lockfile verifizieren | Offen |
| [Inertia.js](https://github.com/inertiajs/inertia) | Frontend-Backend-Brücke | Paketlizenz verifizieren | Offen |
| [Lucide](https://github.com/lucide-icons/lucide) | Icons (`lucide-react`) | ISC, inklusive Copyright- und Erlaubnishinweis | Upstream verifiziert; ausgelieferte Version prüfen |
| [Tailwind CSS](https://github.com/tailwindlabs/tailwindcss) | CSS | Paketlizenz verifizieren | Offen |
| [Vite Plus](https://github.com/voidzero-dev/vite-plus) | Entwicklungs-/Build-Werkzeuge | Lizenz der tatsächlich installierten Pakete und Binärbestandteile prüfen | Offen |
| [PHP/Composer- und NPM-Pakete](../composer.lock) | Direkte und transitive Abhängigkeiten | Jede aufgelöste Lizenz samt Ausnahmen/Copyright prüfen; [NPM Lockfile](../package-lock.json) ebenfalls einbeziehen | **Vollständiger Audit offen** |

Nicht jedes Entwicklungswerkzeug muss im laufenden Produkt enthalten sein. Die tatsächlichen **Verteilungsartefakte** (Repository, Docker-Image, Browser-Bundle, herunterladbare Dateien) bestimmen, welche Notices mitzuliefern sind.

## 4. Schriftarten, Assets und weitere Quellen

| Herkunft | Verwendung | Lizenzstatus |
| --- | --- | --- |
| [Inter – rsms/inter](https://github.com/rsms/inter) | In CSS vorgesehen; prüfen, ob Schriftdateien tatsächlich im Build enthalten sind oder über externe Dienste kommen | **SIL OFL 1.1**; bei Verteilung Font-Copyright und Lizenz beilegen; Reserved Font Name beachten |
| Lucide-Icons | Darstellungen in Web-Bundle | **ISC**, erforderliche Hinweise mitliefern |
| Markenfarben, EduKSX-Wortmarke | Eigenes CI (MarKusSXCH) | Nicht als allgemeine MIT-Freigabe ausgeben |
| Daten / Beschreibungen aus offiziellen Quellen | Quellenangaben, Definitionen | Fakten/Bezeichnungen von kopierten schutzfähigen Texten trennen. Fremdtexte nicht ungeprüft unter CC BY 4.0 veröffentlichen |
| Externe CDNs (z. B. Scalar/jsDelivr/Bunny Fonts, soweit tatsächlich genutzt) | API-Dokumentation / Schriftauslieferung | Tatsächliche Einbindung, Bedingungen und Datenschutz prüfen; CDN-Nutzung nicht automatisch als Weitergabe des Bibliotheks-Quellcodes behandeln |

## 5. Erforderliche Lizenzhinweise und Veröffentlichung

1. **In GitHub:** eigene Software-Lizenz erst nach Rechteklärung festlegen; `LICENSE`/Lizenztext ergänzen. Keine Lizenzbehauptung ohne Beschluss.
2. **Bei kopierten Komponenten:** vollständige ursprüngliche MIT-Hinweise, Copyright-Zuordnung und Herkunft beibehalten; zentrale `THIRD_PARTY_NOTICES` plus gegebenenfalls Lizenzdateien im verteilten Artefakt vorsehen.
3. **Bei Paketdistribution:** alle tatsächlich enthaltenen Paketlizenzen aus `composer.lock` und `package-lock.json` mit Version, SPDX-Ausdruck, Copyrightinhaber, Quell-URL und Volltext/mitgeliefertem Lizenzort erfassen; Ausnahmen und Copyleft gesondert prüfen.
4. **Bei Schrift- und Asset-Verteilung:** OFL-/ISC- bzw. sonstige Hinweise entsprechend Originalbedingungen mitliefern.
5. **Bei öffentlichen Glossary-Daten:** Name der Datenlizenz, Lizenzlink, Quelle «EduKSX-Glossary, glossary.eduksx.ch» und erforderliche Änderungskennzeichnung bereitstellen.
6. **Auf Website:** `/legals` verweist auf diese Dokumentation; getrennte Seiten zu Datenschutz und Betreiberangaben erst nach tatsächlicher rechtlicher Prüfung als final ausweisen.

Ein blosser Link zur Upstream-Lizenz ersetzt **nicht automatisch** die Pflicht zur Mitlieferung des Lizenztexts bzw. Copyright-Hinweises, wenn dieser für die konkrete Verteilung vorgeschrieben ist.

## 6. Abnahmekriterien vor öffentlicher Quellcode-/Produktdistribution

- [ ] Eigentums- und Weitergaberechte am ursprünglichen `sek-intranet` schriftlich geprüft, einschliesslich etwaiger Arbeitgeber-/Drittrechte.
- [ ] Pro übernommener Datei Quellpfad, Ursprungs-Commit, eigene Änderungen und Lizenz-/Copyrightinhaber identifiziert.
- [ ] `my-github-blueprint`-Vorlagen auf Lizenz und geschützte Fremdanteile geprüft.
- [ ] MIT-Originalhinweise der shadcn-Komponenten und weiterer Kopien korrekt beibehalten.
- [ ] Softwarelizenz für **eigene** Anteile beschlossen, ohne Drittkomponenten unzulässig umzuzertifizieren.
- [ ] Automatischer Composer-/NPM-Lizenzreport mit vollständigen direkten und transitiven Paketen pro Release erstellt.
- [ ] Browser-Bundle, Docker-Image und mitgelieferte Assets auf Lizenzhinweise und unverträgliche Lizenzen geprüft.
- [ ] Tatsächlich eingesetzte Schriftdateien samt OFL-Text und zulässigen Schriftbezeichnungen geprüft.
- [ ] CC BY 4.0 nur auf rechtmässig entsprechend lizenzierbare Glossary-Inhalte angewandt.
- [ ] Öffentliche Legal-Seite und Notice-Dateien zeigen dieselbe geprüfte Version.
- [ ] Fachliche bzw. erforderlichenfalls juristische Freigabe protokolliert.

**Pflege:** Bei neuer Abhängigkeit, Copy/Paste aus fremdem Repository, neuem Asset oder einer neuen Release-Version ist dieses Register vor Veröffentlichung zu aktualisieren. Lizenzscanner unterstützen die Prüfung, ersetzen sie aber nicht.
