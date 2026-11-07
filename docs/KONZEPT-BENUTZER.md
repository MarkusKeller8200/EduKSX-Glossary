# Konzept Benutzerverwaltung und Rollen (Entwurf, #34)

Vorgabe der/des Projektverantwortlichen vom 07.11.2026: Rollen Developer, Admin, Moderator und API; Angaben zur
Person nur so viele wie nötig. Dieses Konzept ersetzt den Abschnitt «Rollen (Vorschlag)» in
[KONZEPT.md](KONZEPT.md). Es gilt erst nach der Abnahme (DEC-017).

## Grundsätze

- **Datensparsam:** nur Angaben, die für Anmeldung, Zuordnung von Vorschlägen und Missbrauchsschutz nötig sind.
  Keine Telefonnummer, Adresse, Geburtsdatum oder Foto.
- **Öffentlich ist höchstens der Community-Name.** Vorname, Name, E-Mail, Funktion und Einrichtung sehen nur
  Moderation und Administration.
- **Rollen sind kombinierbar** (z. B. Admin und Moderator). Ein Konto ohne Rolle ist ein Community-Konto.
- **Rechte im Code** über Gates (wie heute `administer`, `edit-official`), nie über Rollennamen in den Seiten.
- Lokale Konten (DEC-014). Eine Anmeldung über GitHub, Microsoft oder Edulog bleibt später möglich und ist nicht
  Teil dieses Konzepts.

## Rollen

| Rolle         | Wofür                                                                | Zweiter Faktor | Anmeldung             |
| ------------- | -------------------------------------------------------------------- | -------------- | --------------------- |
| _(Community)_ | Vorschläge einreichen, eigene Vorschläge und deren Stand sehen       | freiwillig     | Passwort              |
| **Moderator** | Vorschläge prüfen, Rückfragen stellen, Begriffe als Entwurf ändern   | Pflicht        | Passwort + Code       |
| **Admin**     | Freigeben/Veröffentlichen, Benutzer und Rollen, Import, Listen       | Pflicht        | Passwort + Code       |
| **Developer** | Technik: Systeminfo, Wartung, Protokolle, Testdaten; dazu Admin      | Pflicht        | Passwort + Code       |
| **API**       | Maschinenkonto (z. B. Intranet, später MCP/Rovo) für Schreibzugriffe | –              | nur Token, kein Login |

### Rechte im Einzelnen

| Recht (Gate)      | Community | Moderator | Admin | Developer | API        |
| ----------------- | --------- | --------- | ----- | --------- | ---------- |
| `propose`         | ja        | ja        | ja    | ja        | ja (Token) |
| `moderate`        | –         | ja        | ja    | ja        | –          |
| `edit-terms`      | –         | ja        | ja    | ja        | –          |
| `publish`         | –         | **offen** | ja    | ja        | –          |
| `edit-official`   | –         | –         | ja    | ja        | –          |
| `manage-users`    | –         | –         | ja    | ja        | –          |
| `import`, `lists` | –         | –         | ja    | ja        | –          |
| `system`          | –         | –         | –     | ja        | –          |

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
| Öffentliche Einrichtung | –       | Moderation, Administration   | Art aus Liste: Schule, Schulgemeinde, Gemeinde, Kanton, andere (**offen**) |
| Gemeinde / Schule       | –       | Moderation, Administration   | Name, z. B. «Primarschule Musterdorf» (**offen**)                          |
| E-Mail                  | ja      | Moderation, Administration   | eindeutig, muss bestätigt sein                                             |
| Passwort                | ja      | niemand                      | mind. 12 Zeichen, nur als Hash                                             |
| Zweiter Faktor          | –       | Administration (nur Status)  | Pflicht für Moderator, Admin, Developer                                    |

**Vom System geführt** (nicht eingegeben, nötig für Sicherheit und Löschfristen):

- Rollen, Status (aktiv / gesperrt), E-Mail bestätigt am
- Zustimmung zur Datenschutzerklärung (Zeitpunkt, Version)
- letzte Anmeldung: nur das Datum, um inaktive Konten zu löschen
- angelegt / geändert am

**Bewusst nicht gespeichert:** IP-Adressen über die Sitzung hinaus, Geräteangaben, Telefon, Adresse,
Geburtsdatum, Foto, Geschlecht.

## Abläufe

- **Registrierung (Community):** Formular mit Spam-Schutz (Cloudflare Turnstile) und Bestätigung per E-Mail;
  danach Community-Konto ohne Rolle. Rollen vergibt nur die Administration. **Offen:** freie Registrierung oder
  nur auf Einladung. Beides braucht einen E-Mail-Versand (SMTP), der noch einzurichten ist.
- **Selbstverwaltung:** Angaben ändern, Passwort ändern, zweiten Faktor einrichten, Konto löschen.
- **Administration:** Benutzerliste mit Suche, Rollen vergeben, sperren/entsperren, zweiten Faktor zurücksetzen,
  Passwort-Link senden. Jede Änderung an Rollen wird protokolliert (wer, wann, was).
- **Löschen:** Auf Wunsch sofort. Inaktive Community-Konten nach 24 Monaten, mit Hinweis 30 Tage vorher.
  Vorschläge bleiben erhalten, erscheinen danach aber als «Community» ohne Personenbezug.
- **Sperren** statt löschen bei Missbrauch. Ein gesperrtes Konto kann sich nicht anmelden.

## Umsetzung (nach Abnahme, Meilenstein 0.4.0)

1. Datenmodell: Spalten für die Angaben, Rollen als eigene Tabelle `user_roles`, Protokoll `user_audit`,
   Migration `is_admin` → Admin + Developer.
2. Gates nach Tabelle oben; die Administration nutzt sie statt `administer`.
3. Benutzerverwaltung in der Administration (Liste, Detail, Rollen, sperren) mit Bausteinen aus dem Intranet.
4. Eigenes Konto (Angaben, Passwort, zweiter Faktor, löschen).
5. Registrierung mit E-Mail-Bestätigung (nach Entscheid und eingerichtetem Mailversand).
6. API-Konten mit Token (erst nötig, wenn Schreibzugriffe kommen: Vorschläge über die API, MCP).

## Offene Fragen

1. **Moderator veröffentlichen?** Darf die Rolle Moderator auch veröffentlichen, oder nur prüfen und
   Entwürfe vorbereiten (Vorschlag: nur prüfen; Freigabe bleibt bei Admin)?
2. **Einrichtung:** Ist «Öffentliche Einrichtung» die Art (Auswahlliste) und «Gemeinde/Schule» der Name
   (Freitext)?
3. **Registrierung:** frei mit E-Mail-Bestätigung oder nur auf Einladung durch die Administration?
4. **Developer:** immer zugleich Admin (Vorschlag), oder nur Technik ohne Inhalte?
5. **Mailversand:** über welchen Dienst (z. B. Infomaniak- oder Hostinger-Mail per SMTP)? Wird geklärt, wenn
   Registrierung oder Passwort-Link umgesetzt werden.
