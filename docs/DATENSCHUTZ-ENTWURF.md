# Datenschutzerklärung EduKSX-Glossary – konsolidierter Entwurf

> **Nicht zur Veröffentlichung freigegeben.** Die folgenden Angaben müssen vor öffentlicher Nutzung anhand tatsächlicher Betriebsdaten, Verträge und Rechtsgrundlagen geprüft werden. Insbesondere sind Betreiber, Anschrift, Kontakt, Datenstandorte und Fristen zu bestätigen. Die anwendbare Schweizer Rechtsordnung hängt auch davon ab, wer die Anwendung rechtlich betreibt; für eine öffentliche Stelle können kantonale Bestimmungen massgeblich sein. Aussagen zur EU-DSGVO gelten nur, soweit diese im Einzelfall anwendbar ist.

**Version:** [1.0] · **Gültig ab:** [Datum] · **Status:** Entwurf vom 07.11.2026

## 1. Verantwortliche Stelle

[Rechtlich verantwortliche Person / Organisation], [vollständige Adresse], [Kontaktadresse für Datenschutzanliegen]. EduKSX-Glossary wird unter der Marke [MarKusSXCH / präzise Betreibergesellschaft] angeboten. Verantwortlichkeiten, insbesondere bei Auftragsbearbeitern, werden vertraglich festgelegt.

## 2. Zweck und Grundsatz

EduKSX-Glossary ist eine öffentlich lesbare Begriffssammlung für das Umfeld von Volksschulen, Schulgemeinden und Gemeinden. Das Lesen der veröffentlichten Begriffe und die Nutzung der öffentlichen GET-API sind ohne Konto möglich. Beim Abruf fallen technisch dennoch Verbindungsdaten an. Zusätzliche personenbezogene Daten entstehen durch Konten, Vorschläge, Anmeldedienste, Supportkontakte und sicherheitsrelevante Verarbeitung. Wir beschränken die Bearbeitung auf erforderliche Zwecke und schützen interne Daten vor der öffentlichen Veröffentlichung.

## 3. Bearbeitete Personendaten

### a) Website und öffentliche API

Bei Zugriffen können IP-Adresse, Zeitpunkt, angefragte URL, Browser-/Client-Kennung sowie technische Sicherheitsmerkmale verarbeitet werden. Zweck sind Auslieferung, Fehlerdiagnose, Sicherheit und Missbrauchsschutz beziehungsweise Rate-Limiting. **Vorgesehene Frist für Serverlogs: [14 Tage; technisch prüfen].** Es sind keine Werbe- oder Nutzungsanalyse-Tracker vorgesehen. Temporäre Sicherheitsdaten bei Dienstleistern können abweichenden Fristen folgen; diese sind vor Freigabe zu prüfen.

### b) Persönliche Benutzerkonten

- Bei lokaler Anmeldung: Vorname, Nachname, verifizierte E-Mail und Passwort nur als sicherer Hash; bei Edulog können Attribute fehlen und nur soweit nötig ergänzt werden.
- Freiwillig: Community-Name, Funktion, Art der Einrichtung, Gemeinde/Schule.
- Sicherheit und Betrieb: Rollen, Kontostatus, E-Mail-Bestätigung, Datum der letzten Anmeldung, MFA-Status, verschlüsselte TOTP-Geheimnisse und nur gehashte Wiederherstellungscodes.
- Externe Anmeldedienste: Anbieter, Ausstellerkennung und stabile pseudonyme beziehungsweise technische Benutzerkennung; weitere Attribute nur soweit erforderlich.
- Dokumentation der **Kenntnisnahme** dieser Erklärung: Version und Zeitpunkt. Zweckgebundene **Einwilligungen** werden gesondert protokolliert, wenn sie erforderlich sind.

- Persönliche Darstellung von Begriffen («Meine Darstellung», freiwillig): eigene Abkürzung, Bezeichnung, Beschreibungen und Schreibweisen. Nur für die Person selbst sichtbar, wenn sie angemeldet ist; nie veröffentlicht, nicht über die API oder Exporte weitergegeben, mit dem Konto gelöscht.
- Änderungen an Begriffen: Zeitpunkt, Art der Änderung, alte und neue Werte, Rolle sowie Community-Name (sonst Name) als Text zum Zeitpunkt der Änderung; nur für Redaktion und Administration. Bei Kontolöschung wird der Name durch «Gelöschtes Konto» ersetzt und der Bezug zum Konto entfernt; die fachliche Änderung bleibt nachvollziehbar.

Der Community-Name kann bei öffentlich zugeschriebenen Beiträgen sichtbar sein. Vorname, Nachname, E-Mail, Funktion und Einrichtung sind nicht Teil öffentlicher API- oder Glossary-Ausgaben. Zugriff auf interne Kontodaten erhalten nur hierfür berechtigte Personen.

### c) Vorschläge und Moderation

Gespeichert werden eingereichter Inhalt, betroffener Begriff, Zeitpunkt, Bearbeitungsstatus, notwendige Kommentare/Prüfvermerke und interne Zuordnung des Kontos. Freigegebene **Fachinhalte** können Teil des öffentlichen Glossary werden und der dafür beschlossenen Datenlizenz CC BY 4.0 unterliegen. Interne Identitätsangaben und Moderationsinformationen werden nicht mit veröffentlicht. Inhalte mit personenbezogenen Informationen werden vor Veröffentlichung geprüft und erforderlichenfalls bereinigt.

### d) Technische Servicekonten

Für automatisierte schreibende Integrationen speichern wir Bezeichnung, Zweck, zuständige Person, zugewiesene Rechte, Token-Hashes, Ablauf- und Widerrufsdaten sowie notwendige Zugriffs- und Sicherheitsprotokolle. Die öffentliche Lese-API erfordert kein Servicekonto.

## 4. Cookies und lokale Speicherung

Wir verwenden nur technisch notwendige Cookies und speichern vom Besucher selbst gewählte Einstellungen im Browser. Es sind keine Drittanbieter-Cookies zu Werbe- oder Trackingzwecken und keine Nutzungsanalyse vorgesehen. Deshalb zeigt die Website beim ersten Besuch einen Hinweis (keine Einwilligung); kommen später nicht notwendige Cookies dazu, wird daraus eine Einwilligung mit Ablehnen-Möglichkeit. [Rechtliche Einordnung nach DSG bzw. DSGVO/ePrivacy prüfen, #66.]

| Name                      | Art                    | Zweck                                             | Dauer                                             | Anbieter   |
| ------------------------- | ---------------------- | ------------------------------------------------- | ------------------------------------------------- | ---------- |
| `eduksx-glossary-session` | Cookie, notwendig      | Sitzung (Anmeldung, Formulare)                    | bis zum Schliessen bzw. [120 Minuten] Inaktivität | Glossary   |
| `XSRF-TOKEN`              | Cookie, notwendig      | Schutz gegen gefälschte Formulare                 | wie die Sitzung                                   | Glossary   |
| `remember_web_…`          | Cookie, notwendig      | «Angemeldet bleiben», nur wenn gewählt            | bis zum Abmelden, höchstens [400 Tage; prüfen]    | Glossary   |
| `appearance`              | Cookie und Browser     | Einstellung hell/dunkel/System                    | 1 Jahr                                            | Glossary   |
| `view-width`              | Browser (localStorage) | Ansicht Mobil/Desktop schmal/ganze Breite (#76)   | bis zum Löschen im Browser                        | Glossary   |
| `cookie-notice`           | Browser (localStorage) | Hinweis zu Cookies gelesen (mit Fassung)          | bis zum Löschen im Browser                        | Glossary   |
| `__cf_bm` u. a.           | Cookie, notwendig      | Schutz vor Bots im Betrieb hinter Cloudflare      | [30 Minuten; prüfen]                              | Cloudflare |
| Turnstile                 | Cookie/Speicher        | Schutz der Registrierung, nur auf `/registrieren` | [prüfen]                                          | Cloudflare |

Die Lesefassung auf GitHub Pages setzt selbst keine Cookies.

## 5. Anmeldung über Microsoft und Edulog

Bei freiwilliger Verwendung eines externen Anmeldedienstes verarbeitet dieser die für die Anmeldung erforderlichen Informationen gemäss seinen eigenen Datenschutzhinweisen. Das Glossary übernimmt nur freigegebene, für den Zugang erforderliche Identitätsattribute. Die sichere Kontozuordnung stützt sich auf bestätigte technische Kennungen, nicht allein auf eine E-Mail-Adresse.

Microsoft-Anmeldung wird für private und organisatorische Microsoft-Konten vorgesehen. Edulog wird **erst nach Föderationsbeitritt und Prüfung** freigeschaltet. Je nach Edulog-Konfiguration können Vorname, Name und E-Mail fehlen; eine pseudonyme Kennung genügt für die technische Identifikation. Die Nutzung durch Lernende oder Minderjährige wird vor Freischaltung gesondert beurteilt.

## 6. Externe Dienstleister und internationale Bearbeitung

Wir verwenden Dienstleister ausschliesslich für dokumentierte Zwecke. Die konkrete Konfiguration und Unterauftragsbearbeitung sind vor Publikation zu verifizieren:

| Anbieter / Dienst                                             | Datenkategorien / Zweck                                                                 | Standort / Prüfung                                          |
| ------------------------------------------------------------- | --------------------------------------------------------------------------------------- | ----------------------------------------------------------- |
| Hostinger                                                     | Hosting von Anwendung, Konten, Datenbank und Betriebsdaten                              | [konkretes Rechenzentrum und Verträge prüfen]               |
| Cloudflare                                                    | IP-Adresse, Verbindungs- und Sicherheitsdaten für Proxy/WAF und ggf. Turnstile          | [Verarbeitungsorte, Produktkonfiguration, Garantien prüfen] |
| [tatsächlicher Mailanbieter]                                  | E-Mail-Adresse, Versand- und Nachrichtenmetadaten, technische Konto-Nachrichten         | [Anbieter, Ort, Unterauftragsbearbeiter]                    |
| Microsoft, nur bei Nutzung der Anmeldung                      | Authentifizierungsdaten und angeforderte Attribute                                      | [vertragliche Hinweise / Auslandbearbeitung prüfen]         |
| Edulog/angeschlossene Identitätsdienste, erst bei Aktivierung | pseudonyme Kennungen und vereinbarte Attribute                                          | [Föderationsbedingungen / konkrete Stellen prüfen]          |
| GitHub                                                        | öffentliche statische Lesefassung; personenbezogene GitHub-Issues nur bei deren Nutzung | [Datenflüsse und Hinweise prüfen]                           |
| jsDelivr / Bunny Fonts                                        | keine – Schrift und API-Doku werden selbst ausgeliefert (#44)                           | entfällt; Zustand bei Änderungen erneut prüfen              |

Daten werden weder verkauft noch für fremde Werbung bereitgestellt. Internationale Übermittlungen werden anhand der tatsächlich beteiligten Empfänger und einer im Einzelfall einschlägigen Grundlage abgesichert. Die Verfügbarkeit eines Datenschutzrahmens oder von Standardvertragsklauseln darf **nicht pauschal für jeden Anbieter oder jede Bearbeitung** unterstellt werden.

## 7. Aufbewahrung und Löschung

Die tatsächlichen Fristen werden in einem internen, versionierten Löschkonzept je Datenkategorie, Zweck, Fristbeginn und Ausnahmeregel dokumentiert. Derzeit vorgesehen:

| Kategorie                                                | Geplante Frist / Auslöser                                            | Vor Veröffentlichung zu prüfen                                            |
| -------------------------------------------------------- | -------------------------------------------------------------------- | ------------------------------------------------------------------------- |
| Unbestätigte Registrierung                               | 7 Tage                                                               | Löschjob und Fehlerversand                                                |
| Inaktives Community-Konto                                | 24 Monate Inaktivität, Benachrichtigung 30 Tage zuvor                | Rechtsgrundlage, Wiederaktivierung, Ausnahmen                             |
| Benutzerprofil auf Löschantrag                           | ohne unnötige Verzögerung, soweit keine Aufbewahrungsgründe bestehen | technische Umsetzung und Fristen                                          |
| Externe Identitätsverknüpfungen, aktive Sitzungen, Token | bei Löschung/Entkopplung unverzüglich widerrufen oder entfernen      | Protokollreste separat behandeln                                          |
| Persönliche Darstellung von Begriffen                    | mit dem Konto                                                        | –                                                                         |
| Änderungsjournal der Begriffe                            | Name bei Kontolöschung durch «Gelöschtes Konto» ersetzt              | Freitexte in Änderungswerten auf Personenbezug prüfen                     |
| Vorschläge und private Moderationsdaten                  | zweckbezogen begrenzen; keine unbegrenzte Personenbindung            | konkrete Frist und redaktioneller Archivbedarf                            |
| Rollen- und Sicherheits-Audits                           | **[vorläufig 5 Jahre]**                                              | Erforderlichkeit, Verhältnismässigkeit, Rechtsgrundlage und Umfang prüfen |
| Serverlogs                                               | [14 Tage]                                                            | tatsächliche Einstellungen auch bei Dienstleistern                        |
| Sicherungskopien                                         | [30 Tage]                                                            | Wiederherstellungsverfahren und ausgenommene Daten                        |

Nach Kontolöschung können fachliche Vorschlagsinhalte nur dann ohne Personenbezug verbleiben, wenn nicht nur die Kontoreferenz, sondern auch identifizierende Freitextinhalte und Verknüpfungsmöglichkeiten angemessen berücksichtigt werden. Sicherheitsprotokolle können bei dokumentiertem Bedarf begrenzt fortbestehen. Bei der Wiederherstellung von Backups werden bereits ausgeführte Löschungen und Sperren erneut angewandt.

## 8. Datenschutz durch Technik

Die Anwendung setzt HTTPS, Passwort-Hashing, verpflichtende MFA für privilegierte Funktionen, serverseitige Berechtigungsprüfungen, sichere Sitzungen, minimale Servicekonto-Scopes, regelmässige Sicherheitsupdates und geprüfte Backups ein. Öffentliche Ansichten, API, Export, Change-Feed, GitHub Pages und später Confluence werden ausschliesslich aus ausdrücklich freigegebenen Datenfeldern erzeugt. Kontodaten, unveröffentlichte Vorschläge, interne Moderation und Auditdaten werden nicht veröffentlicht.

## 9. Kenntnisnahme, Einwilligung und Rechte

Bei der Registrierung wird die geltende Datenschutzerklärung angezeigt; Version und Zeitpunkt der Kenntnisnahme werden dokumentiert. Dies ist **keine pauschale Einwilligung** in alle Datenbearbeitungen. Wo eine Einwilligung erforderlich ist, wird sie getrennt, informiert, zweckbezogen und nachweisbar eingeholt; ein Widerruf ist mit Wirkung für die Zukunft möglich, soweit keine andere zulässige Grundlage die Bearbeitung trägt.

Betroffene Personen können je nach anwendbarem Recht Auskunft, Berichtigung und unter den gesetzlichen Voraussetzungen Löschung oder Herausgabe ihrer Personendaten verlangen. Eine Herausgabe umfasst auch die eigene persönliche Darstellung von Begriffen. Weitergehende Rechte, Beschwerdestellen und Rechtsbehelfe bestimmen sich nach der tatsächlich anwendbaren Rechtsordnung. Für Anfragen: [Kontakt]. Zuständige Aufsicht: [nach Betreiberstatus und geltendem Recht präzisieren; nicht automatisch nur EDÖB].

## 10. Änderungen

Die aktuelle Fassung steht unter `/datenschutz` mit Versionsnummer und Gültigkeitsdatum. Bei wesentlichen Änderungen werden registrierte Personen angemessen informiert. Eine neue Einwilligung wird nur eingeholt, wo dies für eine bestimmte Bearbeitung erforderlich ist.

## 11. Freigabecheck vor Veröffentlichung

- [ ] Verantwortliche Stelle, Adresse und Kontakt vollständig.
- [ ] Anwendbares Datenschutzrecht und Aufsicht korrekt bestimmt; rechtliche Prüfung erfolgt.
- [ ] Tatsächliches Hosting, Cloudflare, Mailanbieter und Drittdienste dokumentiert.
- [ ] Verträge, internationale Datenflüsse und Garantien geprüft.
- [ ] Technische Cookies, Log- und Backupfristen verifiziert.
- [ ] Edulog nur nach formeller und technischer Freigabe in die Erklärung aufgenommen beziehungsweise als zukünftig markiert.
- [ ] Löschkonzept samt Auditfrist fachlich/rechtlich geprüft.
- [ ] Öffentliches Export-/API-Schema gegen Abfluss interner Daten getestet.
