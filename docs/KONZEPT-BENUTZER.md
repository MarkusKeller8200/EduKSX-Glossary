# Konzept Benutzerverwaltung und Rollen (Entwurf, #34)

Vorgabe der/des Projektverantwortlichen vom 07.11.2026: Rollen Developer, Admin, Moderator und API; Angaben zur
Person nur so viele wie nötig. Entscheide vom 07.11.2026 eingearbeitet: konfigurierbare Rechte je Rolle,
Selbstregistrierung mit Double-Opt-in, Developer mit allen Rechten, Anmeldung auch über Microsoft und Edulog.
Dieses Konzept ersetzt den Abschnitt «Rollen (Vorschlag)» in [KONZEPT.md](KONZEPT.md). Es gilt erst nach der
Abnahme (DEC-017).

## Grundsätze

- **Datensparsam:** nur Angaben, die für Anmeldung, Zuordnung von Vorschlägen und Missbrauchsschutz nötig sind.
  Keine Telefonnummer, Adresse, Geburtsdatum oder Foto.
- **Öffentlich ist höchstens der Community-Name.** Vorname, Name, E-Mail, Funktion und Einrichtung sehen nur
  Moderation und Administration.
- **Rollen sind kombinierbar** (z. B. Admin und Moderator). Ein Konto ohne Rolle ist ein Community-Konto.
- **Rechte sind je Rolle einstellbar** (Entscheid): Die Administration legt in einer Matrix fest, welche Funktionen
  eine Rolle darf. Der Code prüft immer das Recht (Gate), nie den Rollennamen.
- **Anmeldung** mit lokalem Konto (E-Mail und Passwort, DEC-014) oder über **Microsoft** bzw. **Edulog** (Single
  Sign-On). Es entsteht immer ein Konto im Glossary; Rollen und Rechte vergibt das Glossary, nicht der
  Anmeldedienst.

## Rollen

| Rolle         | Wofür                                                                | Zweiter Faktor | Anmeldung             |
| ------------- | -------------------------------------------------------------------- | -------------- | --------------------- |
| _(Community)_ | Vorschläge einreichen, eigene Vorschläge und deren Stand sehen       | freiwillig     | Passwort              |
| **Moderator** | Vorschläge prüfen, Rückfragen stellen, Begriffe als Entwurf ändern   | Pflicht        | Passwort + Code       |
| **Admin**     | Freigeben/Veröffentlichen, Benutzer und Rollen, Import, Listen       | Pflicht        | Passwort + Code       |
| **Developer** | alle Rechte, auch moderieren und freigeben; dazu Technik             | Pflicht        | Passwort + Code       |
| **API**       | Maschinenkonto (z. B. Intranet, später MCP/Rovo) für Schreibzugriffe | –              | nur Token, kein Login |

### Rechte je Rolle (einstellbar)

Die Tabelle zeigt die **Voreinstellung**. In der Administration lässt sich je Rolle ein- und ausschalten, welche
Funktionen sie darf. Zusätzliche Rollen kann die Administration anlegen, z. B. «Redaktion Schule».

| Recht (Gate)      | Community | Moderator | Admin | Developer | API        |
| ----------------- | --------- | --------- | ----- | --------- | ---------- |
| `propose`         | ja        | ja        | ja    | ja        | ja (Token) |
| `moderate`        | –         | ja        | ja    | ja        | –          |
| `edit-terms`      | –         | ja        | ja    | ja        | –          |
| `publish`         | –         | –         | ja    | ja        | –          |
| `edit-official`   | –         | –         | ja    | ja        | –          |
| `manage-users`    | –         | –         | ja    | ja        | –          |
| `import`, `lists` | –         | –         | ja    | ja        | –          |
| `system`          | –         | –         | –     | ja        | –          |

- **Schutz vor Aussperren:** Developer hat immer alle Rechte; diese Rolle ist nicht einstellbar. Das Recht
  `manage-users` kann nicht allen Rollen entzogen werden, und das letzte Konto mit diesem Recht lässt sich nicht
  sperren oder löschen.
- **Zweiter Faktor:** Pflicht für jede Rolle mit einem Recht ausser `propose`; einstellbar je Rolle, Voreinstellung
  wie oben.
- Jede Änderung an Rollen und Rechten wird protokolliert (wer, wann, vorher, nachher).
- **API-Konten** melden sich nie mit Passwort an. Sie erhalten ein Token: zufällig erzeugt, nur einmal angezeigt,
  gespeichert nur als Hash, mit Ablaufdatum und jederzeit widerrufbar. Die öffentliche Lese-API bleibt ohne
  Token.
- Der heutige Schalter `is_admin` geht in den Rollen auf. Bestehende Konten erhalten die Rollen Admin und Developer.

## Angaben zum Konto

| Feld                    | Pflicht | Sichtbar für                 | Bemerkung                                                                  |
| ----------------------- | ------- | ---------------------------- | -------------------------------------------------------------------------- |
| Community-Name          | –       | öffentlich (bei Vorschlägen) | eindeutig; leer → Vorschläge erscheinen als «Community»                    |
| Vorname                 | ja      | Moderation, Administration   |                                                                            |
| Name                    | ja      | Moderation, Administration   |                                                                            |
| Funktion                | –       | Moderation, Administration   | Freitext, z. B. «Schulleitung», «Gemeindeschreiberin»                      |
| Öffentliche Einrichtung | –       | Moderation, Administration   | Art aus Liste: Schule, Schulgemeinde, Gemeinde, Kanton, andere (Entscheid) |
| Gemeinde / Schule       | –       | Moderation, Administration   | Name als Freitext, z. B. «Primarschule Musterdorf»                         |
| E-Mail                  | ja      | Moderation, Administration   | eindeutig, muss bestätigt sein                                             |
| Passwort                | ja¹     | niemand                      | mind. 12 Zeichen, nur als Hash                                             |
| Zweiter Faktor          | –       | Administration (nur Status)  | Pflicht für Moderator, Admin, Developer                                    |

¹ Nicht nötig, wenn sich jemand nur über Microsoft oder Edulog anmeldet. Ein Passwort lässt sich später setzen.

**Vom System geführt** (nicht eingegeben, nötig für Sicherheit und Löschfristen):

- Rollen, Status (aktiv / gesperrt), E-Mail bestätigt am
- verknüpfte Anmeldedienste: Dienst (Microsoft, Edulog) und die eindeutige, stabile Kennung des Dienstes
  (`sub`/`oid`), keine weiteren Profildaten
- Zustimmung zur Datenschutzerklärung (Zeitpunkt, Version)
- letzte Anmeldung: nur das Datum, um inaktive Konten zu löschen
- angelegt / geändert am

**Bewusst nicht gespeichert:** IP-Adressen über die Sitzung hinaus, Geräteangaben, Telefon, Adresse,
Geburtsdatum, Foto, Geschlecht.

## Abläufe

- **Registrierung (Entscheid: frei, Double-Opt-in):** Formular mit Spam-Schutz (Cloudflare Turnstile) und
  Zustimmung zur Datenschutzerklärung. Danach geht ein Bestätigungslink per E-Mail (60 Minuten gültig). Erst nach
  der Bestätigung ist das Konto aktiv, als Community-Konto ohne Rolle. Unbestätigte Registrierungen werden nach 7
  Tagen gelöscht. Rollen vergibt nur die Administration. Nötig ist ein E-Mail-Versand (SMTP).
- **Passwort vergessen:** Link per E-Mail (60 Minuten gültig). Ob die Adresse ein Konto hat, verrät die Antwort
  nicht.
- **Selbstverwaltung:** Angaben ändern, Passwort ändern, zweiten Faktor einrichten, Konto löschen.
- **Administration:** Benutzerliste mit Suche, Rollen vergeben, sperren/entsperren, zweiten Faktor zurücksetzen,
  Passwort-Link senden. Jede Änderung an Rollen wird protokolliert (wer, wann, was).
- **Löschen:** Auf Wunsch sofort. Inaktive Community-Konten nach 24 Monaten, mit Hinweis 30 Tage vorher.
  Vorschläge bleiben erhalten, erscheinen danach aber als «Community» ohne Personenbezug.
- **Sperren** statt löschen bei Missbrauch. Ein gesperrtes Konto kann sich nicht anmelden.

## Anmeldung über Microsoft und Edulog (Single Sign-On)

Beide Dienste über **OpenID Connect** (Authorization Code Flow mit PKCE). Auf der Anmeldeseite stehen die Knöpfe
«Mit Microsoft anmelden» und «Mit Edulog anmelden» neben E-Mail und Passwort.

- **Microsoft (Entra ID):** Geschäfts- und Schulkonten aller Organisationen (Endpunkt `organizations`), keine
  privaten Microsoft-Konten. Optional eine Liste erlaubter Organisationen (Tenant-IDs). Nötig: eine
  App-Registrierung in einem Entra-Tenant mit Rücksprungadresse `https://glossary.eduksx.ch/anmelden/microsoft`.
- **Edulog:** Föderation der Schweizer Bildung (educa); Lehrpersonen und Schulpersonal melden sich mit ihrem
  Schulkonto an. Nötig: Aufnahme des Glossary als Dienstanbieter bei Edulog (Vereinbarung mit educa,
  Testumgebung, danach Produktion). Das braucht Vorlauf. Bis dahin erscheint der Knopf nicht.
- **Erste Anmeldung:** Gibt es ein Konto mit derselben, vom Dienst bestätigten E-Mail, fragt das Glossary nach dem
  Passwort dieses Kontos und verknüpft danach. Sonst entsteht ein neues Community-Konto mit Vorname, Name und
  E-Mail vom Dienst (bestätigt, kein Double-Opt-in nötig). Fehlende Pflichtangaben und die Zustimmung zur
  Datenschutzerklärung werden einmal abgefragt.
- **Übernommene Angaben:** nur Vorname, Name, E-Mail und die Kennung. Keine Gruppen, Fotos oder
  Organisationsdaten.
- **Zweiter Faktor:** Für Rollen mit Pflicht verlangt das Glossary seinen eigenen Code auch nach einer Anmeldung
  über Microsoft oder Edulog. Später kann eine Einstellung die Mehrfaktor-Anmeldung des Dienstes anerkennen.
- **Verknüpfen und lösen** im eigenen Konto. Lösen geht nur, wenn ein Passwort oder ein anderer Dienst bleibt.
- **Technik:** Laravel Socialite mit dem Microsoft-Provider und einem allgemeinen OIDC-Provider für Edulog. Diese
  Pakete werden lokal installiert, weil die Cloud-Sitzung keine Pakete installieren kann. Geheimnisse
  (Client-Secret) nur in der Umgebung des Servers, nie im Repository.

## Umsetzung (nach Abnahme, Meilenstein 0.4.0)

1. Datenmodell: Spalten für die Angaben; Tabellen `roles`, `role_permissions`, `user_roles`,
   `user_identities` (Anmeldedienste) und `user_audit`; Migration `is_admin` → Admin + Developer.
2. Rechte (Gates) aus der Rollen-Matrix; die Administration nutzt sie statt `administer`.
3. Administration: Benutzer (Liste, Detail, Rollen, sperren) und Rollen (Matrix der Rechte), mit Bausteinen aus
   dem Intranet.
4. Eigenes Konto: Angaben, Passwort, zweiter Faktor, verknüpfte Dienste, Konto löschen.
5. Registrierung mit Double-Opt-in und «Passwort vergessen» (braucht Mailversand).
6. Anmeldung über Microsoft (braucht App-Registrierung), danach Edulog (braucht Aufnahme bei Edulog).
7. API-Konten mit Token (erst nötig, wenn Schreibzugriffe kommen: Vorschläge über die API, MCP).

## Entschieden (07.11.2026)

1. **Rechte je Rolle einstellbar** statt fest; Moderator veröffentlicht in der Voreinstellung nicht.
2. **Einrichtung:** Art aus Liste (Schule, Schulgemeinde, Gemeinde, Kanton, andere), Gemeinde/Schule als Freitext.
3. **Selbstregistrierung** mit Double-Opt-in per E-Mail.
4. **Developer** hat alle Rechte, auch moderieren und freigeben.
5. **Anmeldung über Microsoft und Edulog** zusätzlich zum lokalen Konto.

## Offene Fragen (werden geklärt, wenn benötigt)

1. **Mailversand:** Dienst und Absenderadresse (z. B. `noreply@eduksx.ch` über Infomaniak- oder Hostinger-SMTP).
2. **Microsoft:** In welchem Entra-Tenant wird die App registriert? Alle Organisationen zulassen oder nur
   bestimmte?
3. **Edulog:** Antrag als Dienstanbieter bei educa stellen (Vorlauf einplanen).
4. **Datenschutzerklärung:** Text und Version vor der ersten Registrierung.
