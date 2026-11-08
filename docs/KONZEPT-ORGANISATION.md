# Konzept Organisation (DEC-025, #148)

Stand: Entwurf 08.11.2026 – zur Abnahme durch die Projektverantwortliche Person. Die Entscheide unten hat der
Agent im Auftrag («entscheide selbst, fasse am Ende zusammen») getroffen; jeder ist einzeln änderbar.

## Ziel

Eine Schule, Schulgemeinde, Gemeinde oder ein Team zeigt die Begriffe in einer gemeinsamen, eigenen Darstellung
(z. B. hausinterne Abkürzung), ohne die gemeinsamen Fachdaten zu verändern. Die Organisation ist freiwillig.

## Begriffe

| Begriff              | Bedeutung                                                                                          |
| -------------------- | -------------------------------------------------------------------------------------------------- |
| Organisation         | Name (eindeutig, ohne Gross-/Kleinschreibung), Mitglieder, Einladungen, Darstellung, Servicekonten |
| Organisations-Admin  | Mitglied mit Verwaltungsrecht in **dieser** Organisation                                           |
| Mitglied             | sieht die Darstellung der Organisation                                                             |
| Darstellung der Org. | Abkürzung, Bezeichnung, Kurzbeschreibung, Beschreibung, zusätzliche Schreibweisen je Begriff       |

## Entscheide

| Nr. | Entscheid                                                                                                                                                                                                                                                                                                                                 |
| --- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| O1  | Gründen ab Rolle **Benutzer** über das neue Grundrecht `create-organization` (auch Org-Admin, Moderator, Admin; nicht Gast).                                                                                                                                                                                                              |
| O2  | **Höchstens eine Organisation je Konto.** Wer gründet, wird Organisations-Admin.                                                                                                                                                                                                                                                          |
| O3  | Organisations-Admin erhält automatisch die globale Rolle **Organisations-Admin** (Servicekonten, zweiter Faktor Pflicht). Entzogen wird sie nur, wenn die Organisation sie vergeben hat (`granted_role`).                                                                                                                                 |
| O4  | Verwaltung (Name, Einladungen, Mitglieder, Darstellung, Löschen) nur mit **bestätigtem zweitem Faktor**. Ohne 2FA zeigt die Seite den Link zur Einrichtung.                                                                                                                                                                               |
| O5  | **Einladen** per E-Mail, mehrere Adressen getrennt mit `;`, `,`, Leerzeichen oder Zeilenumbruch. Gültig **14 Tage**, erneutes Einladen verlängert. Mail nur mit eingerichtetem Versand; die Einladung gilt auch ohne Mail.                                                                                                                |
| O6  | **Beitritt automatisch bei der Anmeldung** (Passwort, zweiter Faktor, Microsoft) eines Kontos mit der eingeladenen Adresse – auch für bestehende Konten, sofern sie noch keiner Organisation angehören. Sonst bleibt die Einladung unter «Meine Organisation» zum Annehmen (nach Austritt) oder Ablehnen.                                 |
| O7  | **Sperren** = Mitgliedschaft ruht (gemeinsame Darstellung, keine Verwaltung). **Löschen** durch den Organisations-Admin = aus der Organisation **entfernen**; das Konto bleibt (Konto löschen bleibt bei der Person bzw. der Plattform-Verwaltung).                                                                                       |
| O8  | Organisations-Admins ernennen andere zu Admins und entziehen sich selbst, solange **mindestens ein aktiver Organisations-Admin** bleibt. Der letzte Admin kann nicht gesperrt, entfernt oder herabgestuft werden und nicht austreten; als einziges Mitglied löscht er die Organisation.                                                   |
| O9  | **Reihenfolge der Anzeige:** persönliche Darstellung vor Darstellung der Organisation vor gemeinsamer Form. Schreibweisen werden ergänzt, nicht ersetzt. Die Detailseite nennt die gemeinsame Form.                                                                                                                                       |
| O10 | Darstellung der Organisation bearbeiten **nur Organisations-Admins**, auf der Seite des Begriffs. Sichtbar für alle **aktiven** Mitglieder (auch mit Rolle Gast). Nie öffentlich, nie in Revisionen, Datenständen, öffentlicher API, Export, GitHub Pages oder Confluence; Antworten mit `Cache-Control: private, no-store`.              |
| O11 | **API:** Servicekonto mit Scope **`read-org`** (nur Organisations-Admins anlegbar, gehört der Organisation, von allen ihren Admins verwaltbar). `GET /api/v1/org/terms[?customized=1]` und `GET /api/v1/org/terms/{idOrSlug}` liefern je Begriff die gemeinsamen Werte (`term`) und die Anpassung (`organization_display`, sonst `null`). |
| O12 | «Eigene Begriffe» bedeutet die **eigene Darstellung** bestehender Begriffe. Neue Begriffe nur einer Organisation gibt es nicht; neue Begriffe laufen über Vorschläge (DEC-024).                                                                                                                                                           |
| O13 | **Kontolöschung:** Ist das Konto der letzte Organisations-Admin, übernimmt das am längsten aktive Mitglied; ohne weitere Mitglieder wird die Organisation gelöscht.                                                                                                                                                                       |
| O14 | **Organisation löschen** entfernt Darstellung, Einladungen und Servicekonten (mit Token) der Organisation; die Konten der Mitglieder bleiben.                                                                                                                                                                                             |
| O15 | Mitglieder sehen Namen und Rollen der anderen Mitglieder; E-Mail-Adressen und offene Einladungen nur Organisations-Admins.                                                                                                                                                                                                                |
| O16 | Jede Aktion (auch abgewiesene) im Audit-Protokoll (`organization.*`), Rollenvergabe im Rollen-Protokoll mit Bezeichnung «Organisation».                                                                                                                                                                                                   |

## Daten

- `organizations` (Name, gegründet von), `organization_members` (eine je Konto; Rolle `admin`/`member`, Status
  `active`/`blocked`, `granted_role`), `organization_invitations` (E-Mail, eingeladen von, gültig bis),
  `organization_term_overrides` (Felder wie `term_user_overrides`, geändert von), `service_accounts.organization_id`.
- Löschfristen: abgelaufene Einladungen werden bei der nächsten Anmeldung eines beliebigen Kontos gelöscht;
  Mitgliedschaft endet mit Austritt, Entfernen oder Kontolöschung; die Darstellung mit der Organisation.

## Oberfläche

- **Mein Konto → Organisation → «Meine Organisation»** (`/organisation`): gründen; Einladungen an mich; Mitglieder
  (Admin: E-Mail, zum Admin, Admin entziehen, sperren, entfernen); einladen und offene Einladungen; angepasste
  Begriffe; Verwaltung (umbenennen, API-Zugang, löschen); austreten.
- **Begriffsseite:** Abschnitt «Darstellung der Organisation «Name»» für Organisations-Admins, Hinweis für
  Mitglieder; Kennzeichnung «Darstellung «Name»» neben der gemeinsamen Form.

## Offen / später

- Mehrere Organisationen je Konto, Unterorganisationen.
- Eigene Begriffe nur für eine Organisation.
- Darstellung der Organisation im Export (Excel/PDF) für Mitglieder.
