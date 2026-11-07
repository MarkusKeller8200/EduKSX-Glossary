# Konzept: Benutzerverwaltung, Identitäten und Berechtigungen

**Stand:** 07.11.2026 · **Status:** fachlich abgenommenes Konzept · **Entscheide:** DEC-014, DEC-017 bis DEC-019 (letztere nach Eintrag ins Register)  
**Geltung:** EduKSX-Glossary; ersetzt den bisherigen Entwurf `KONZEPT-BENUTZER.md`.

## 1. Ziele und Grundsätze

EduKSX-Glossary ist öffentlich lesbar. Persönliche Konten dienen der Einreichung und Bearbeitung von Vorschlägen sowie redaktionellen und administrativen Aufgaben. Technische Integrationen verwenden getrennte Servicekonten. Das Glossary selbst verwaltet Rollen und Rechte; externe Anmeldedienste vergeben keine Glossary-Berechtigungen.

- **Minimalprinzip:** Nur erforderliche Personendaten, Berechtigungen und technische Identitäten; standardmässig kein Recht ohne ausdrückliche Zuweisung.
- **Öffentlichkeit:** Nur veröffentlichte Glossary-Daten verlassen die interne Veröffentlichungsgrenze. Öffentlich sichtbar ist bei einem Vorschlag höchstens der freiwillige Community-Name; private Vorschläge selbst bleiben intern.
- **Nachvollziehbarkeit:** Sicherheitsrelevante Änderungen, Moderation, Freigaben und schreibende Servicezugriffe werden angemessen protokolliert.
- **Kompatibilität:** Laravel 13, bestehender TOTP-Mechanismus, SQLite, öffentliche REST-API ohne Leseschlüssel; spätere Microsoft-, Edulog-, Confluence- und MCP-Anbindungen.

## 2. Identitätsarten und Rollen

### 2.1 Persönliche Benutzerkonten

| Rolle | Aufgaben | MFA | Anmeldung |
| --- | --- | --- | --- |
| Community (keine zusätzliche Rolle) | Vorschläge einreichen; eigene Vorschläge und Status einsehen | freiwillig | lokal oder unterstützter Identitätsanbieter |
| Moderator | Vorschläge prüfen; Rückfragen; Entwürfe bearbeiten | verpflichtend | persönliche Anmeldung mit wirksamer MFA |
| Admin | Veröffentlichen; Benutzer- und Rollenverwaltung innerhalb der Sicherheitsgrenzen; Import und Listen | verpflichtend | persönliche Anmeldung mit wirksamer MFA |
| Developer | sämtliche Anwendungsrechte sowie System- und Sicherheitsverwaltung | verpflichtend | persönliche Anmeldung mit wirksamer MFA |

Rollen sind kombinierbar. Die Anwendung berechnet die effektiven Rechte aus allen zugewiesenen Rollen und prüft sie **serverseitig für jede geschützte Aktion**. Eine Rolle ist keine direkte Autorisierung; massgebend ist das angeforderte Recht. Die Developer-Rolle ist eine unveränderbare, geschützte Systemrolle.

### 2.2 Servicekonten

Servicekonten sind **keine Benutzerrolle** und werden getrennt von Personenkonten geführt. Sie dienen etwa dem Intranet, einem zukünftigen MCP-Adapter oder anderen freigegebenen Automatisierungen. Kein interaktiver Login, kein Passwort, keine Sitzungscookies. Die öffentliche Lese-API bleibt ohne Konto oder Schlüssel zugänglich.

Jedes Servicekonto erhält einen Namen, Zweck, verantwortliche natürliche Person, Status und eng begrenzte Scopes. Token werden kryptografisch zufällig erzeugt, nur einmal angezeigt, ausschliesslich als sichere Hashwerte abgelegt, befristet, einzeln widerrufbar und nach Möglichkeit regelmässig rotiert. Token und Identitäten werden von anderen Rollen unabhängig geprüft. Schreibende Servicezugriffe verwenden dieselbe Geschäftslogik, Validierung, Auditierung und Moderationskette wie schreibende Personenzugriffe. Ein Servicekonto darf nicht unmittelbar veröffentlichen, solange dafür kein gesonderter Entscheid getroffen wurde.

## 3. Berechtigungsmodell

| Gate / Recht | Community | Moderator | Admin | Developer | Servicekonto (Default) |
| --- | --- | --- | --- | --- | --- |
| `propose` | ja | ja | ja | ja | nur mit Scope |
| `moderate` | nein | ja | ja | ja | nein |
| `edit-terms` | nein | ja | ja | ja | nein |
| `publish` | nein | nein | ja | ja | nein |
| `edit-official` | nein | nein | ja | ja | nein |
| `manage-users` | nein | nein | ja (begrenzt) | ja | nein |
| `import`, `lists` | nein | nein | ja | ja | nein |
| `system` | nein | nein | nein | ja | nein |
| Developer-Rolle vergeben/entziehen | nein | nein | nein | ja | nein |

Dies sind **Voreinstellungen**, nicht zwingend jede denkbare zukünftige Rolle. Zusätzliche Rollen (z. B. «Redaktion Schule») sind möglich. Für die Konfiguration gelten drei Klassen:

1. **Normale Rechte** (`propose`, `moderate`, `edit-terms`): durch autorisierte Rollenverwaltung anpassbar.
2. **Privilegierte Rechte** (`publish`, `edit-official`, `manage-users`, `import`, `lists`): nur mit ausdrücklich zugestandener Verwaltungsbefugnis veränderbar; eine Person darf eigene Berechtigungen weder direkt noch indirekt erhöhen. Die Vergabe eines Rechts darf niemals über die eigene Verwaltungsgrenze hinausgehen.
3. **Systemkritische Rechte** (`system`, Developer-Vergabe, Sicherheitskonfiguration): ausschliesslich Developer, nicht über die Rollenmatrix umkonfigurierbar.

Die Developer-Rolle wird nur durch einen bereits berechtigten Developer oder über einen **gesicherten administrativen Serverprozess** zugewiesen beziehungsweise entzogen. Ein Admin kann weder sich selbst noch anderen Developer-Rechte geben. Bei der Migration erhalten vorhandene Admin-Konten zunächst **nur Admin**, niemals automatisch Developer. Die anfängliche Developer-Zuweisung ist explizit vorzunehmen und zu protokollieren. Schutz gegen Aussperren: Das letzte funktionsfähige Developer-Konto darf nicht über die GUI gesperrt, gelöscht oder seiner Rolle beraubt werden; ein kontrollierter, protokollierter Notfallzugang bleibt möglich. Das gilt auch für die letzte nutzbare Verwaltungsberechtigung.

Änderungen an Rollen, privilegierten Rechten und Sicherheitskonfigurationen enthalten ausführende Identität, Ziel, Zeit, alten und neuen Wert sowie Ergebnis im Audit-Protokoll. Rollen- und Rechteänderungen werden unverzüglich auf aktive Sitzungen und Tokenberechtigungen wirksam.

## 4. Personendaten und Kontofelder

| Feld | Pflicht | Öffentlichkeit / Sichtbarkeit | Hinweis |
| --- | --- | --- | --- |
| Vorname, Nachname | grundsätzlich ja; bei Edulog nach Attributverfügbarkeit gesondert beurteilen | nur berechtigte Moderation/Administration | nicht öffentlich |
| E-Mail | für lokale Anmeldung ja; bei Edulog ggf. gesondert erheben und verifizieren | nur berechtigte Stellen | Kontakt / Wiederherstellung |
| Passwort | nur bei lokalem Login | nie im Klartext | mindestens 12 Zeichen; sicherer Passwort-Hash |
| Community-Name | nein | höchstens bei ausdrücklich öffentlich gezeigter Zuschreibung | eindeutig; leer = «Community» |
| Funktion | nein | intern | Freitext |
| Einrichtungsart | nein | intern | Schule, Schulgemeinde, Gemeinde, Kanton, andere |
| Gemeinde/Schule | nein | intern | Freitext |
| Zweiter Faktor | für privilegierte Nutzung ja | Admin sieht nur Status | TOTP-Geheimnis verschlüsselt; Recovery-Codes gehasht |

Weitere intern geführte Daten: Rollen, Kontostatus, E-Mail-Verifikation, Datum der letzten Anmeldung (zwecks Inaktivität), Erstell- und Änderungszeitpunkt, Version/Zeitpunkt der **Kenntnisnahme** der Datenschutzerklärung sowie getrennte, zweckgebundene Einwilligungen nur falls benötigt. Externe Identitäten werden als `provider`, `issuer`, `subject` und nötigenfalls ergänzend `tenant_id`/`object_id` gespeichert. Eine E-Mail ist niemals der technische Schlüssel einer externen Identität.

Keine routinemässige Speicherung von Telefonnummer, Adresse, Geburtsdatum, Foto, Geschlecht oder detaillierten Geräteprofilen. Temporäre IP-/Zugriffsdaten für Sicherheit und Missbrauchsschutz sind von persistenten Kontoattributen getrennt und folgen dem Löschkonzept.

## 5. Registrierung, Anmeldung und Kontoschutz

### 5.1 Community

Öffentliches Lesen inklusive der GET-API benötigt kein Konto. Für Vorschläge **innerhalb der Anwendung** wird ein persönliches Konto mit verifiziertem Zugang benötigt. Lokale Selbstregistrierung: Spam-Schutz (z. B. Cloudflare Turnstile), Information über die Datenschutzerklärung mit protokollierter Kenntnisnahme, Bestätigungslink per E-Mail (60 Minuten gültig), Freischaltung erst nach Bestätigung. Unbestätigte Registrierungen werden nach sieben Tagen gelöscht. Die Rolle ist zunächst ausschliesslich Community; weitere Rechte weist eine autorisierte Stelle zu. Bei fehlender Versandkonfiguration ist Selbstregistrierung deaktiviert; autorisierte Personen können Konten administrativ anlegen.

Die GitHub-Issue-Formulare können bis zur integrierten Community-Funktion als Übergangskanal bestehen bleiben. Sie sind kein anonymer Zugang zu internen Vorschlägen und unterliegen den separaten GitHub-Datenschutzbedingungen. Kein Vorschlag wird ohne redaktionelle Freigabe veröffentlicht.

### 5.2 Lokale Anmeldung

Passwörter werden ausschliesslich sicher gehasht. Es gelten Anmeldebegrenzungen, generische Fehlertexte, Session-Rotation nach Authentifizierung, sichere Cookies (`Secure`, `HttpOnly`, angemessenes `SameSite`) und HTTPS. Passwortzurücksetzung über einmaligen, kurzlebigen Link (Vorschlag: 60 Minuten) ohne Offenlegung, ob ein Konto existiert. Gesperrte Konten können sich nicht anmelden.

### 5.3 MFA

MFA ist **aufgrund der effektiven Berechtigungen** verpflichtend, nicht als deaktivierbare Eigenschaft einer Rolle. Vor Bestätigung des zweiten Faktors sind sämtliche privilegierten Funktionen gesperrt. Das gilt auch nach Microsoft- oder Edulog-Anmeldung. Fremde MFA wird nur anerkannt, wenn deren Durchführung über vertrauenswürdige, überprüfbare Authentifizierungsinformationen belegt ist und die Anerkennung ausdrücklich freigegeben wurde. Der bestehende TOTP-Mechanismus nach RFC 6238 wird weiterverwendet; Geheimnisse verschlüsselt, acht einmalige Wiederherstellungscodes nur als Hash. MFA-Reset verlangt überprüfte Identität und ein Audit-Ereignis; für Notfälle ist ein gesicherter Serverprozess vorzusehen.

### 5.4 Selbstverwaltung, Sperrung und Löschung

Personen können zulässige Profildaten, Passwort, MFA und Identitätsverknüpfungen verwalten sowie die Kontolöschung beantragen. Eine letzte nutzbare Anmeldemethode darf nicht versehentlich entfernt werden. Bei Löschung werden Sitzungen und Token unverzüglich ungültig; restliche Daten folgen dem dokumentierten Aufbewahrungs- und Löschkonzept. Gesperrte Konten behalten ausschliesslich zweckmässig notwendige Daten und dürfen keine neuen Aktionen durchführen.

## 6. Föderierte Anmeldung

### 6.1 Microsoft

Microsoft-Login über OpenID Connect, Authorization Code Flow mit PKCE und sorgfältiger Prüfung von `state`, `nonce`, Token-Signatur, Aussteller, Audience, Ablauf und Redirect-URI. Das Glossary besitzt eine eigene mehrinstanzenfähige App-Registrierung für Organisations- und private Microsoft-Konten; einzelne Schulen und Gemeinden müssen im Regelfall keine eigene App registrieren. Organisatorische Consent-Richtlinien können dennoch eine Freigabe verlangen.

Identitätsschlüssel ist die Kombination aus bestätigtem `issuer` und stabilem `subject`; für Microsoft können `tid` und `oid` ergänzend hinterlegt werden. Ein E-Mail-Gleichstand **führt niemals allein** zur automatischen Kontoübernahme oder Verknüpfung. Verknüpfung mit einem existierenden Konto nur nach erneuter Authentifizierung über dessen bereits vertrauenswürdige Methode. Eine vom Provider nicht zuverlässig verifizierte E-Mail ist gesondert zu bestätigen. Externe Gruppen, Rollen, Mandanten oder Claims verleihen keine Glossary-Privilegien. Angeforderte Attribute werden auf das Notwendige beschränkt.

### 6.2 Edulog

Edulog ist eine nachgelagerte Integration und wird erst nach vertraglicher Aufnahme, technischer Prüfung und Freigabe angeboten. Das genaue Protokoll (OIDC/SAML) sowie zulässige Attribute werden mit Edulog geklärt; nicht vorab voraussetzen. Technischer Identitätsschlüssel ist die bestätigte stabile pseudonyme Kennung samt Aussteller beziehungsweise Föderationskontext. Vorname, Name und E-Mail können fehlen. Fehlende fachlich erforderliche Angaben werden nur wo notwendig ergänzend erhoben; Kontozuordnung und Kontaktkanal müssen sicher verifizierbar sein.

Der Zugang von Lernenden und insbesondere minderjährigen Personen wird **vor einer Freischaltung** gesondert beurteilt (Zweck, erforderliche Daten, rechtliche Grundlagen und Schutzvorkehrungen). Die Edulog-Anmeldung gibt standardmässig nur Community-Rechte; externe Attribute berechtigen niemals automatisch zur Moderation oder Administration.

### 6.3 Identitäten verknüpfen und lösen

Eine externe Identität kann erst nach belastbarer Authentifizierung mit einem bestehenden Konto verknüpft werden. Einmalige Zuordnung von `provider`/`issuer`/`subject` durch Datenbank-Constraint erzwingen. Entkopplung nur durch angemeldete Person mit erneuter Authentifizierung und verbleibender funktionsfähiger Anmeldemethode. Sensible Verknüpfungsänderungen protokollieren.

## 7. E-Mail-Versand

Laravel-Mail-Schicht, providerunabhängig konfiguriert (`MAIL_MAILER`, `MAIL_HOST`, Zugangsdaten nur in der Serverumgebung). Unterstützte Wege: SMTP oder passende Treiber für Microsoft 365, Google Workspace, Infomaniak, Hostinger oder spezialisierte Versanddienste. Versand über eine Warteschlange mit Wiederholung und sicherem Fehlerverhalten; ausgehende Nachrichten geben keine unnötigen persönlichen Informationen preis. Ohne funktionierenden E-Mail-Versand bleibt Selbstregistrierung deaktiviert. Testmail nur mit Systemrecht. Tatsächlicher Anbieter und Datenstandort sind im Datenschutz-/Betriebskonzept zu dokumentieren.

## 8. Datenmodell und technische Umsetzung

- Personen: `users`, `roles`, `permissions`/`role_permissions`, `user_roles`, `user_identities`, `user_audit`.
- Servicekonten: `service_accounts`, `service_account_tokens`, `service_account_audit`; bislang angedachte `api_clients` darin aufgehen lassen, nicht parallel führen.
- Datenschutz: Kenntnisnahme der Datenschutzerklärung (`privacy_notice_version`, `privacy_notice_acknowledged_at`) und gegebenenfalls getrennte `user_consents` mit Zweck, Zeitpunkt, Version und Widerruf.
- Audit und redaktionelle Daten getrennt vom öffentlichen Veröffentlichungsmodell. Serverseitig Gates/Policies für Web, API, Import, Exporte und künftig MCP identisch durchsetzen.
- Der bisherige Schalter `is_admin` wird bei Migration in die **Admin-Rolle** überführt, **nicht automatisch in Developer**. Initialer Developer nur nach ausdrücklich protokolliertem Bootstrap.

### Abnahmetests

1. Admin kann weder direkt noch mittelbar Developer werden; letzte Developer-Identität ist geschützt.
2. Rollenänderung kann weder eigene Rechte erhöhen noch systemkritische Rechte entsperren; MFA lässt sich nicht durch Rollenänderung umgehen.
3. Servicekonten haben keinen Browser-Login; Token sind gehasht, widerrufbar, ablaufend und scope-begrenzt.
4. Microsoft-Verknüpfung allein über gleiche E-Mail wird abgelehnt; Provider-Identität ist eindeutig.
5. Edulog kann technisch mit pseudonymer Identität ohne vorausgesetzte E-Mail modelliert werden.
6. Öffentliche API, Exporte, Change-Feed und GitHub Pages enthalten ausschliesslich veröffentlichte Fachdaten.
7. Kontolöschung entzieht Zugänge und wahrt die dokumentierten Lösch- und Auditregeln.

## 9. Umsetzungsreihenfolge und offene Punkte

Reihenfolge: Rollen-/Identitätsmodell und Migration → MFA-/Policy-Härtung → Benutzer-Selbstverwaltung und Registrierung → Microsoft → Edulog nach Zulassung → schreibende Servicekonten, wenn die entsprechenden Integrationen beginnen. Bestehende lokale Admin-Anmeldung und öffentliche Lese-API bleiben bis zum Ausbau betriebsfähig.

**Noch betrieblich beziehungsweise rechtlich zu klären:** produktiver Mailanbieter/Absender, Microsoft-App-Tenant und organisatorische Freigaben, Edulog-Beitritt/Attributvertrag/Lernendenfreigabe, technische Löschfristen je Datenart, tatsächliche Cloud-Datenflüsse sowie juristische Abnahme der Datenschutzerklärung.