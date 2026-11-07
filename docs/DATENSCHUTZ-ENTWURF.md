# Datenschutzerklärung EduKSX-Glossary (Entwurf, #34)

> **Entwurf zur Abnahme.** Grundlage ist das Schweizer Datenschutzgesetz (DSG). Für Personen in der EU gelten
> zusätzlich die Rechte nach DSGVO. Platzhalter stehen in eckigen Klammern. Vor der Veröffentlichung juristisch
> prüfen lassen; das ist keine Rechtsberatung. Die gültige Fassung erscheint auf der Website unter
> `/datenschutz` mit Versionsnummer. Bei der Registrierung wird die Zustimmung zu dieser Version gespeichert.

**Version [1.0], gültig ab [Datum]**

## 1. Verantwortlich

[Vorname Name / Organisation], [Adresse], [E-Mail für Datenschutzanfragen, z. B. datenschutz@eduksx.ch].
EduKSX-Glossary ist ein Angebot von [MarKusSXCH / Organisation].

## 2. Grundsatz

Das Glossary ist ein öffentliches Nachschlagewerk. Begriffe lesen geht ohne Konto und ohne Angaben zur Person.
Personendaten bearbeiten wir nur, wenn Sie ein Konto anlegen, Vorschläge einreichen oder uns schreiben, und nur so
viele, wie dafür nötig sind.

## 3. Welche Daten wir bearbeiten

**a) Besuch der Website und Nutzung der API (ohne Konto)**

- Technisch nötige Angaben beim Abruf: IP-Adresse, Zeitpunkt, aufgerufene Adresse, Browser-Kennung. Sie dienen
  dem Betrieb, der Sicherheit und der Abwehr von Missbrauch (z. B. Abruflimit der API).
- Server-Protokolle werden nach [14] Tagen gelöscht.
- Wir verwenden keine Analyse- oder Werbedienste und kein Tracking.

**b) Konto (Community, Moderation, Administration)**

| Angabe                                                                                              | Zweck                                     |
| --------------------------------------------------------------------------------------------------- | ----------------------------------------- |
| Vorname, Name, E-Mail (Pflicht)                                                                     | Anmeldung, Kontakt zu Ihren Vorschlägen   |
| Passwort (nur als Hash)                                                                             | Anmeldung                                 |
| Community-Name, Funktion, Art der Einrichtung, Gemeinde/Schule (freiwillig)                         | Einordnung von Vorschlägen                |
| Zweiter Faktor (geheimer Schlüssel, verschlüsselt)                                                  | Schutz von Konten mit erweiterten Rechten |
| Rollen, Status, Bestätigung der E-Mail, Zustimmung zu dieser Erklärung, Datum der letzten Anmeldung | Betrieb, Sicherheit, Löschfristen         |
| Verknüpfte Anmeldedienste (Microsoft, Edulog): nur die Kennung des Dienstes                         | Anmeldung über den Dienst                 |

Öffentlich sichtbar ist höchstens Ihr Community-Name bei Ihren Vorschlägen. Vorname, Name, E-Mail, Funktion und
Einrichtung sehen nur die Moderation und die Administration.

**c) Vorschläge**

Inhalt des Vorschlags, Zeitpunkt, Stand der Bearbeitung und das einreichende Konto. Übernommene Inhalte werden Teil
des Glossary und stehen unter der Lizenz der Daten (CC BY 4.0), ohne Angaben zu Ihrer Person.

## 4. Cookies und lokale Speicherung

- **Sitzungs-Cookie** und **Schutz-Cookie** gegen gefälschte Formulare (XSRF): technisch nötig für die Anmeldung,
  gelöscht nach Ende der Sitzung bzw. nach [120] Minuten Inaktivität.
- **Darstellung** (hell/dunkel): als Cookie und im Browser gespeichert (`appearance`), ohne Personenbezug.
- Keine Cookies von Dritten für Analyse oder Werbung.

## 5. Weitergabe und Dienstleister

Wir verkaufen keine Daten und geben sie nicht für Werbung weiter. Folgende Dienstleister bearbeiten Daten in
unserem Auftrag oder bei Ihrer Anmeldung:

| Dienst                                                                          | Zweck                                                                                            | Ort                           |
| ------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ | ----------------------------- |
| Hostinger                                                                       | Server (Hosting)                                                                                 | [Standort des Rechenzentrums] |
| Cloudflare                                                                      | Schutz und Auslieferung der Website (IP-Adresse, Abrufe), Spam-Schutz bei Formularen (Turnstile) | weltweit, USA²                |
| [Mail-Dienst, z. B. Microsoft 365]                                              | Versand von Bestätigungs- und Passwort-Mails                                                     | [Ort]                         |
| Microsoft (nur bei «Mit Microsoft anmelden»)                                    | Anmeldung über Ihr Microsoft-Konto                                                               | gemäss Ihrem Konto, USA²      |
| Edulog / educa (nur bei «Mit Edulog anmelden»)                                  | Anmeldung über die Föderation Edulog                                                             | Schweiz                       |
| GitHub (nur bei Meldungen über GitHub und für die Lesefassung auf GitHub Pages) | Fehlermeldungen, Ideen, statische Lesefassung                                                    | USA²                          |
| jsDelivr (nur Seite `/api/docs`)                                                | Auslieferung der Bibliothek für die API-Doku                                                     | weltweit³                     |

² Übermittlung in die USA gestützt auf das Swiss-U.S. Data Privacy Framework bzw. Standardvertragsklauseln.
³ Wird durch eine eigene Auslieferung ersetzt (offener Punkt im Konzept). Die Schrift «Inter» kommt von Bunny Fonts
und wird [beim Build eingebunden / prüfen].

Bei der Anmeldung über Microsoft oder Edulog erhalten wir nur Vorname, Name, E-Mail und eine Kennung. Für die
Bearbeitung durch diese Dienste gelten deren Datenschutzerklärungen.

## 6. Aufbewahrung

- Konto: bis Sie es löschen. Inaktive Community-Konten löschen wir nach 24 Monaten; 30 Tage vorher erhalten Sie
  einen Hinweis.
- Unbestätigte Registrierungen: nach 7 Tagen.
- Vorschläge: bleiben erhalten. Nach Löschung des Kontos erscheinen sie ohne Personenbezug als «Community».
- Protokoll von Änderungen an Rollen und Rechten: [5] Jahre (Nachvollziehbarkeit).
- Server-Protokolle: [14] Tage. Sicherungskopien: [30] Tage.

## 7. Sicherheit

Verschlüsselte Verbindung (HTTPS), Passwörter nur als Hash, zweiter Faktor für Konten mit erweiterten Rechten,
Rechte nach Rollen, regelmässige Updates und Sicherungen.

## 8. Ihre Rechte

Sie können Auskunft über Ihre Daten verlangen, falsche Daten berichtigen und Ihre Daten löschen lassen. Sie können
die Herausgabe Ihrer Daten in einem gängigen Format verlangen und eine Einwilligung widerrufen. Vieles geht direkt
im eigenen Konto (Angaben ändern, Konto löschen); sonst schreiben Sie an [E-Mail]. Beschwerden können Sie beim
Eidgenössischen Datenschutz- und Öffentlichkeitsbeauftragten (EDÖB) einreichen; Personen in der EU bei ihrer
Aufsichtsbehörde.

## 9. Änderungen

Wir passen diese Erklärung an, wenn sich das Angebot ändert. Die jeweils gültige Version steht auf der Website. Bei
wesentlichen Änderungen informieren wir angemeldete Personen per E-Mail.
