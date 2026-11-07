# EduKSX-Glossary – Sicherheits- und Datenschutzkonzept

**Version:** 0.1 (Prüfentwurf)  
**Datum:** 07.11.2026  
**Status:** Dokumentiert, **nicht zur Umsetzung freigegeben**; Bearbeitung und Priorisierung erfolgen später.  
**Geltungsbereich:** Öffentliche Website, REST-API, persönliche Benutzerkonten, Community, Redaktion, Datenhaltung, Hosting, CI/CD und künftige Integrationen (Intranet, Confluence, MCP/Rovo).

> Dieses Dokument hält die vorläufige Sicherheits- und Datenschutzprüfung fest. Es ist weder ein abgeschlossener Penetrationstest noch eine rechtliche Freigabe. Befunde sind als **im Code beobachtet**, **im Konzept offen** oder **noch zu verifizieren** zu kennzeichnen. Die Prioritäten sind Vorschläge und keine bestätigten Sicherheitsvorfälle. Es werden durch diesen Check-in keine technischen Änderungen vorgenommen.

## 1. Ziel und Schutzprinzipien

EduKSX-Glossary soll besonders hohe Datenschutz- und Sicherheitsanforderungen für den Schul- und Gemeindekontext erfüllen. Das Glossar selbst ist öffentlich lesbar, verarbeitet aber zusätzlich personenbezogene Konten, SSO-Identitäten, private Vorschläge, Moderationsdaten, Sicherheitsprotokolle und technische Zugangsdaten.

Leitprinzipien:

1. **Privacy by Design / Default:** Nur notwendige Daten erfassen, standardmässig vertraulich, zweckgebundene Verarbeitung.
2. **Trennung:** Öffentliche Fachdaten, private Community-Daten und sicherheitskritische Betriebsdaten erhalten unterschiedliche Zugriffs- und Veröffentlichungsgrenzen.
3. **Keine Personendossiers:** Besonders schützenswerte Personendaten sind kein regulärer Zweck des Glossars. Unbeabsichtigte Angaben in Freitexten müssen erkannt, zurückgehalten und bereinigt werden.
4. **Least Privilege und Zero Trust:** Berechtigung jeder Aktion serverseitig prüfen; Serviceidentitäten unabhängig von Benutzerkonten führen.
5. **Datenminimierung und Löschbarkeit:** Personenbezogene Informationen müssen auch über Revisionen, Logs, Zwischenspeicher und Backups hinweg kontrollierbar bleiben.
6. **Nachweisbarkeit:** Sicherheit durch dokumentierte Tests, Revisionen, unabhängige Prüfungen und reproduzierbare Wiederherstellung belegen.
7. **Schweizer Kontext:** Anwendbares Recht und verantwortliche Stelle (privater Plattformbetreiber oder öffentliche Stelle) vor Inbetriebnahme festlegen. Bundes-DSG sowie gegebenenfalls kantonales Zürcher Informations- und Datenschutzrecht prüfen. Der Schweizer Serverstandort allein garantiert keine Rechtskonformität.

## 2. Umfang und Prüfgrenzen

Berücksichtigt wurden im Repository `MarkusKeller8200/EduKSX-Glossary` insbesondere:

- `README.md`, `DEPLOY.md`, `docs/KONZEPT.md`, `docs/KONZEPT-DATENMODELL.md`, `docs/KONZEPT-BENUTZER.md`, `docs/KONZEPT-HOSTING.md` und `docs/DATENSCHUTZ-ENTWURF.md`;
- `routes/web.php`, `routes/api.php`;
- `app/Http/Controllers/AccountController.php`, `app/Access/AccountManager.php`, `app/Access/Audit.php`;
- `app/Http/Controllers/Auth/SsoController.php`, `app/Http/Controllers/Api/GlossaryApiController.php`, `app/Glossary/ApiPresenter.php`, `app/Http/Controllers/Admin/ExcelController.php`.

**Nicht vollständig geprüft:** alle Migrationen/Fremdschlüssel, sämtliche Middleware, Provider-Tokenvalidierung, alle Frontend-Renderpfade, Docker-/Traefik-Konfiguration, CI/CD-Berechtigungen, tatsächliche Produktionskonfiguration, Logs, Cloudflare-Einstellungen und die Laufzeitumgebung. Keine aktiven Angriffs- oder Penetrationstests durchgeführt.

## 3. Positive vorhandene Grundlagen

- Öffentliche Lese-API und private Administrationsrouten sind getrennt.
- Öffentliche Glossarbegriffe werden aus veröffentlichten Revisionen ausgegeben; `ApiPresenter::PUBLIC_TERM_FIELDS` bildet eine explizite Feld-Positivliste.
- Rollen, Rechte, MFA-Pflicht für privilegierte Funktionen und geschützte Developer-Rolle sind vorgesehen bzw. teilweise umgesetzt.
- Microsoft-SSO-Identitäten werden nicht allein aufgrund einer übereinstimmenden E-Mail automatisch verknüpft.
- Änderungsereignisse und Veröffentlichungsstände sind angelegt; Freigaben erfolgen redaktionell.
- Datenschutzentwurf, Rechte- und Hosting-Konzepte benennen offene Fragen bereits ausdrücklich.

## 4. Risikoregister

| ID     | Priorität | Befund                                                                                                                                                      | Evidenz/Status                                                                           | Erforderliche Massnahme                                                                                                                                                             | Abnahmekriterium                                                                                          |
| ------ | --------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------- |
| SEC-01 | P0        | Kontolöschung eines ausschliesslich föderierten Kontos verlangt nur die Eingabe «LÖSCHEN» innerhalb einer aktiven Sitzung, keine frische Authentifizierung. | Im Code beobachtet: `AccountController::destroy()`.                                      | Frische, nachgewiesene Re-Authentifizierung via OIDC (mit geeigneter Authentifizierungszeit) oder anderer vorhandener vertrauenswürdiger Methode; optional zusätzliche Bestätigung. | Session-Diebstahl allein genügt nicht für Löschung; automatisierte Tests.                                 |
| SEC-02 | P0        | Glossary teilt sich laut Hostingkonzept Host/Traefik mit dem Intranet-Prototyp. Ein kompromittierter Host kann beide Systeme betreffen.                     | Im Konzept bestätigt.                                                                    | Für hohe Schutzanforderungen isolierten Produktionshost bzw. gleichwertige Host-Isolation, restriktive Netze, eigene Secrets und Least-Privilege-Container vorsehen.                | Dokumentiertes Threat Model und verifizierte Isolation.                                                   |
| SEC-03 | P0        | Aufbewahrung, Löschung und Wiederherstellung gelöschter Daten aus Backups sind noch nicht vollständig betrieblich geklärt.                                  | Konzept und Deploymentdokumentation: lokale Rotationssicherung, Offsite-Backup offen.    | Verbindliche Löschmatrix und Backup-Policy inkl. verschlüsselter Offsite-Kopie, Restore-Test, Wiederanwendung von Löschaufträgen.                                                   | Test: gelöschtes Konto bleibt auch nach Restore gelöscht/anonymisiert; dokumentierte Backupfristen.       |
| SEC-04 | P1        | Personenbezug kann in historischen Audits, Revisionen, Vorschlägen und Freitexten fortbestehen.                                                             | Audit nutzt User-IDs; Migrationen und Referenzen noch nicht abschliessend geprüft.       | Datenkategorien und Aufbewahrungsgrund je Referenz erfassen; entkoppelte historische Zuschreibung, Pseudonymisierung und Anonymisierung mit Freitextprüfung.                        | Kontolöschtest über alle Tabellen, Revisionen, Exporte, Log- und Suchpfade.                               |
| SEC-05 | P1        | SSO-Registrierung übernimmt Provider-E-Mail als Kontaktdatum; Vertrauenswürdigkeit und Verifikation jedes Claims bedürfen Prüfung.                          | Im Code beobachtet: `SsoController::register()`; Validierung im Provider noch zu prüfen. | E-Mail-Verifikation getrennt von Identitätsverifikation behandeln; bestätigte Claims und Provider-Verhalten prüfen.                                                                 | Tests für fehlende, unbestätigte und kollidierende E-Mail-Claims.                                         |
| SEC-06 | P1        | `own_name` / `own_abbreviation` sind noch im öffentlichen DTO, obwohl persönliche Varianten nur im Benutzerkontext gelten sollen.                           | Im Code beobachtet: `ApiPresenter`; fachliche Zielmodelländerung.                        | Globale öffentliche Begriffe von benutzerbezogenen Overrides mit separatem Zugriffskontext trennen.                                                                                 | Persönliche Felder erscheinen nie in öffentlicher API, Export, Change-Feed, GitHub Pages oder Confluence. |
| SEC-07 | P1        | Veröffentlichung versehentlich personenbezogener Freitexte kann irreversible Verbreitung über externe Konsumenten bewirken.                                 | Architekturbedingt, keine konkrete Offenlegung festgestellt.                             | Vor Veröffentlichung Inhaltsprüfung, redaktionelle Freigabe, Notfall-Rückzug und Benachrichtigung von Abnehmern.                                                                    | Negativtests mit gezielt platzierten Personendaten.                                                       |
| SEC-08 | P1        | Tatsächliche Datenflüsse an Cloudflare, Hostinger, E-Mail-Provider, GitHub und spätere Integrationen sind nicht vollständig freigegeben.                    | Im Datenschutz-/Hostingkonzept offen.                                                    | Datenflussinventar mit Empfängern, Unterauftragsbearbeitern, Standorten, Zugriffsmöglichkeiten, Verträgen und Auslandübermittlungen.                                                | Rechts-/Datenschutzfreigabe vor Community-Start.                                                          |
| SEC-09 | P1        | Auditänderungen sind vorhanden, aber Unveränderbarkeit, eingeschränkte Zugriffe und unabhängiger Schutz sind noch nicht belegt.                             | `Audit::log()` schreibt in die Anwendungsdatenbank.                                      | Append-only-Ereignisse, externe bzw. getrennte manipulationsgeschützte Ablage, Integritätsprüfung, definierte Fristen.                                                              | Manipulations- und Berechtigungstests; nachweisbare Unveränderbarkeit.                                    |
| SEC-10 | P1        | Host, Anwendung und Backups könnten dieselbe Vertrauens- bzw. Schlüsselgrenze teilen.                                                                       | Schlüsselmanagement nicht abschliessend geprüft.                                         | Getrennte Schlüsselverwaltung, rollenbasierte Schlüsselzugriffe, Rotation, Restore-Prozess, kein Schlüsselmaterial im Repository oder ungeschützten Backup.                         | Dokumentiertes KMS-/Secret-Konzept und Rotationstest.                                                     |
| SEC-11 | P1        | Deploymentbeispiel verwendet einen SSH-Zugang über `root`; Origin-Zugriff und Firewallabsicherung noch offen.                                               | In `DEPLOY.md` dokumentiert.                                                             | Dedizierter unprivilegierter Deploy-Benutzer, minimale Befehlsrechte, Hostkey-Verifikation, Origin-Firewall bzw. authentifizierter Tunnel.                                          | Kein direkter Root-Deploy; Origin-Bypass verhindert.                                                      |
| SEC-12 | P1        | Incident Response, Sicherheitsüberwachung und unabhängige Sicherheitsabnahme nicht ausreichend nachgewiesen.                                                | Betriebsunterlagen unvollständig.                                                        | Incident-Runbook, Meldewege, Monitoring, Updates, Patch-SLAs und Penetrationstest.                                                                                                  | Abnahmeprotokoll und simulierte Sicherheitsübung.                                                         |
| SEC-13 | P2        | XLSX-Importvorschauen liegen zeitweise im Cache; Inhaltsschutz, Cache-Retention und Parser-Härtung vollständig prüfen.                                      | Im Code beobachtet: `ExcelController::preview()`, TTL 30 Minuten, max. 5 MB.             | Dateiinhaltstyp/Struktur validieren, Ressourcenlimits, sichere Parserkonfiguration, Cache-Bereinigung, keine Rohdaten in Debug/Logs.                                                | Tests für fehlerhafte Dateien, Ressourcenverbrauch und Retention.                                         |
| SEC-14 | P2        | API-Suche verarbeitet alle veröffentlichten Begriffe im Anwendungsspeicher.                                                                                 | Im Code beobachtet: `GlossaryApiController::terms()`.                                    | Ressourcenlimits und Lasttests, ggf. DB-seitige Suche/Pagination, Rate-Limits prüfen.                                                                                               | Stabile Reaktion bei Last und missbräuchlichen Suchanfragen.                                              |
| SEC-15 | P2        | Hosting- und Datenschutzentwurf enthalten noch offene oder vorläufige Fristen.                                                                              | Dokumentiert.                                                                            | Verantwortliche, Fristbeginn, Rechtsgrundlage, Ausnahmen und automatisierte Löschjobs festlegen.                                                                                    | Genehmigte Daten-/Aufbewahrungsmatrix.                                                                    |

**Prioritäten:** P0 = vor hochsensibler produktiver Nutzung beheben oder nachweislich gleichwertig kompensieren; P1 = vor Community-Produktivstart prüfen und schliessen; P2 = vor vollständiger Sicherheitsabnahme bzw. risikobasiert bearbeiten. Diese Stufen sind eine vorläufige Empfehlung.

## 5. Verbindlich zu definierende technische Anforderungen

### 5.1 Identitäten und Konten

- Privilegierte Funktionen nur nach MFA; phishingresistente MFA (Passkeys/WebAuthn) prüfen.
- Re-Authentifizierung für Kontolöschung, Identitätswechsel, MFA-Reset, Passwort-/E-Mail-Änderung und sicherheitskritische Rollenänderungen.
- OIDC: `state`, `nonce`, PKCE, Signatur, Issuer, Audience, Ablauf und verifizierte Claims strikt prüfen.
- Benutzerkonten, externe Identitäten und technische Servicekonten strikt trennen; Token nur gehasht, befristet und widerrufbar.
- Rechteentzug muss für aktive Sessions und Tokens unverzüglich greifen.
- Eigen- und Fremdprivilegierung, IDOR und Mass-Assignment durch Tests ausschliessen.

### 5.2 Datenmodell und Veröffentlichung

- Öffentliche Glossarbegriffe und persönliche Bezeichnungen/Beschreibungen als getrennte Datenmodelle.
- Community-Name nie als FK oder alleinige historische Personenidentität; Löschung eines Kontos darf keinen Glossareintrag zerstören.
- Feldbezogenes Journal mit Zeitpunkt, Aktion, Akteur-Kategorie und altem/neuem Wert, aber keine unnötige dauerhafte Personenidentifizierbarkeit.
- Für das Journal sensible Werte minimieren/maskieren; Aufbewahrungsfristen und Berechtigungen pro Journaltyp.
- Alle öffentlichen Outputs basieren auf einem einzigen zentralen positiven Veröffentlichungs-Schema.
- Neue öffentliche Felder dürfen nicht ohne explizite Review- und Testanpassung freigegeben werden.
- Bei Veröffentlichung und Rückzug: Konsumenten-/Cache-Invalidierung, Behandlung externer Kopien, Prüfpfad für versehentliche PII.

### 5.3 Anwendungssicherheit

- Sichere HTML-/Markdown-Darstellung, Schutz gegen XSS, CSRF, SQL Injection, SSRF und Dateiupload-Missbrauch.
- Sichere Cookies, Session-Rotation, passende Zeitlimits, restriktive CORS- und CSP-Konfiguration.
- API-Eingabevalidierung, Rate-Limits, Pagination-/Payload-Limits, kontrollierte Fehlerausgaben.
- Security-Header und HSTS; moderne TLS-Konfiguration.
- Abhängigkeitsscans, CodeQL/SAST, Secret-Scanning, Dependency-Updates, Lockfiles und CI-Prüfungen.
- OWASP ASVS Level 2 als Prüfbasis; für kritische Funktionen ergänzende Level-3-Kontrollen.

### 5.4 Hosting und Betrieb

- Separate Produktionsumgebung, minimale Containerprivilegien, keine unnötigen Hostmounts; abgeschottete Netzwerke.
- Deploy-Schlüssel und technische Konten nach Least Privilege; keine direkte Root-Deploymentroutine.
- Host-/Containerpatching, Protokollüberwachung, Sicherheitsalarmierung und dokumentierter Notfallzugang.
- Verschlüsselte Backups getrennt vom Host, regelmässige Restore-Tests, kontrollierte Schlüsselverwaltung.
- Cloudflare-Origin gegen Direktzugriffe absichern und Drittdienst-Datenflüsse prüfen.
- Ausfall-, Notfall- und Meldeverfahren mit Verantwortlichkeiten und RTO/RPO definieren.

### 5.5 Datenschutz und Recht

- Betreiberrolle und anwendbares Recht verbindlich klären; gegebenenfalls Auftragsbearbeitung durch Schulen/Gemeinden vertraglich regeln.
- Dateninventar und Verzeichnis der Bearbeitungstätigkeiten, Datenflussübersicht und Löschkonzept erstellen.
- Datenschutz-Folgenabschätzung anhand konkreter Risiken und Rechtsgrundlagen prüfen, nicht pauschal voraussetzen.
- Betroffenenrechte (Auskunft, Berichtigung, Löschung soweit zulässig) organisatorisch und technisch sicherstellen.
- Keine Einführung von Edulog für Minderjährige ohne separate Schutzbedarfs-/Rechtsprüfung.
- Datenschutzerklärung erst nach tatsächlicher technischer und rechtlicher Verifikation freigeben.

## 6. Abnahmetests und Nachweise

| Testgruppe            | Mindestnachweis                                                                                   |
| --------------------- | ------------------------------------------------------------------------------------------------- |
| Authentifizierung     | Session-Diebstahl reicht nicht für sensible Kontoaktion; SSO-Claims/Verknüpfungen robust          |
| Rollen und Rechte     | Kein Privilege Escalation, keine Selbstvergabe, keine IDOR                                        |
| Veröffentlichung      | Gezielte private Testwerte erscheinen auf keinem öffentlichen Endpunkt oder Export                |
| Löschung              | Identität, Referenzen, Vorschläge, Auditfristen und Wiederherstellung vollständig nachvollziehbar |
| Backup/Restore        | Verschlüsselung, Konsistenz, Aufbewahrung, Wiederanwendung von Löschungen                         |
| Infrastruktur         | Härtung, Netzgrenzen, Schlüsselzugriff, SSH/CI/CD-Prüfung                                         |
| Code und Dependencies | SAST/DAST, Dependency-/Secret-Scan; Ergebnisse dokumentiert                                       |
| Penetrationstest      | Unabhängiger Test mit Nachprüfung kritischer/höherer Findings                                     |
| Datenschutzfreigabe   | Datenflüsse, Verträge, Zweck-/Fristenmatrix, Verantwortlichkeiten dokumentiert                    |

## 7. Empfohlene Phasen (noch **nicht beauftragt**)

1. **Kontosicherheit:** Re-Authentifizierung, SSO-Verifikationsnachweise, MFA-Härtung.
2. **Datenmodell:** persönliche Overrides, Audit-Entkopplung, Löschregeln und Referenzen.
3. **Veröffentlichungsgrenze:** zentrale DTOs, Freitext-/PII-Prüfung, Tests für API/Export/Pages/Confluence.
4. **Infrastruktur:** Host-Isolation, CI/CD- und SSH-Härtung, Netzschutz, Secrets/KMS.
5. **Betrieb:** verschlüsselte Sicherung, Wiederherstellung, Löschjobs, Monitoring, Incident Response.
6. **Recht:** Verantwortlichkeiten, Anbieter-/Vertragsprüfung, Datenschutz-Folgenabschätzungsbedarf.
7. **Abnahme:** Testmatrix, Penetrationstest und dokumentierte Freigabeentscheidung.

## 8. Vorläufige Nutzungsempfehlung

- **Entwicklung/interne Tests:** möglich mit synthetischen Daten und gesichertem Testbetrieb.
- **Öffentliche rein redaktionelle Fachdaten:** nach spezifischer technischer Veröffentlichungsprüfung.
- **Community-Produktivbetrieb mit Personenkonten:** erst nach Klärung der P0/P1-Befunde und Rechts-/Betriebsfreigabe.
- **Besonders schützenswerte Personendaten:** kein vorgesehener Verarbeitungszweck; keine Freigabe hierfür.

## 9. Referenzen und Folgeentscheid

Interne Referenzen: [KONZEPT.md](KONZEPT.md), [KONZEPT-BENUTZER.md](KONZEPT-BENUTZER.md), [KONZEPT-DATENMODELL.md](KONZEPT-DATENMODELL.md), [KONZEPT-HOSTING.md](KONZEPT-HOSTING.md), [DATENSCHUTZ-ENTWURF.md](DATENSCHUTZ-ENTWURF.md), [../DEPLOY.md](../DEPLOY.md).

Externe Orientierung: Schweizer DSG und gegebenenfalls kantonales Zürcher Recht; EDÖB-Empfehlungen zu technischen und organisatorischen Massnahmen; OWASP ASVS, OWASP Top 10 und OWASP API Security Top 10.

**Folgeentscheid:** Das Sicherheits- und Datenschutzkonzept wird jetzt lediglich versioniert abgelegt. Über Umsetzung, Issue-Erstellung, technische Änderungen, Priorisierung und Release-Freigabe wird zu einem späteren Zeitpunkt entschieden.
