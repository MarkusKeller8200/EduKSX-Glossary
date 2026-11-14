<?php

namespace App\Data;

/**
 * Zweck je Tabelle (DB-Standard B9, API-Standard C9, #314): Zweck, Rechtsgrundlage bzw. Auftrag, Geltungsbereich (B1),
 * Betroffene, Empfänger und Löschfrist. Jede Tabelle (ohne Framework-Tabellen) gehört zu genau einem Zweck; ein Test
 * prüft das gegen die Datenbank. Daraus entsteht das Verzeichnis der Bearbeitungen docs/DATENSCHUTZ-VERZEICHNIS.md
 * (php artisan glossary:verzeichnis). Fristen wie im Löschkonzept (docs/LOESCHKONZEPT.md). Arbeitshilfe, keine
 * Rechtsberatung – rechtliche Prüfung mit #278 und #66.
 */
final class Purposes
{
    /**
     * @var array<string, array{
     *     purpose: string, basis: string, scope: string, subjects: string, recipients: string, retention: string,
     *     tables: list<string>, operations: list<string>
     * }>
     */
    public const MAP = [
        'directory' => [
            'purpose' => 'Öffentliches Begriffsverzeichnis: Begriffe erfassen, prüfen, veröffentlichen, suchen und an Abnehmer abgeben',
            'basis' => 'Auftrag des Betreibers (Verzeichnis für Schulen und Gemeinden); veröffentlichte Daten unter CC BY 4.0',
            'scope' => 'global',
            'subjects' => 'keine (Fachdaten); im Journal Redaktion als Bezeichnung',
            'recipients' => 'öffentlich (Website, API, Change-Feed, Exporte, GitHub Pages)',
            'retention' => 'dauerhaft; Begriffe werden zurückgezogen statt gelöscht; Personenbezug im Journal bei Kontolöschung entfernt',
            'tables' => [
                'terms', 'term_aliases', 'term_categories', 'term_sources', 'term_translations', 'term_relations',
                'categories', 'category_translations', 'sources', 'source_translations', 'term_revisions',
                'term_publication_events', 'publication_releases', 'duplicate_decisions',
            ],
            'operations' => [
                'getStatus', 'listTerms', 'getTerm', 'getIndex', 'listCategories', 'listSources', 'getLatestRelease',
                'listChanges', 'exportTerms',
            ],
        ],
        'accounts' => [
            'purpose' => 'Konten, Anmeldung und Rechte (Redaktion, Moderation, Verwaltung, Community)',
            'basis' => 'Nutzungsbedingungen bei der Registrierung; Anmeldung mit Microsoft nur nach Verknüpfung durch die Person',
            'scope' => 'Plattform/intern',
            'subjects' => 'registrierte Personen (Lehrpersonen, Schulverwaltung, Gemeinden, Fachpersonen)',
            'recipients' => 'Verwaltung des Glossary; Mail-Versand (Bestätigung, Hinweise)',
            'retention' => 'unbestätigt 7 Tage; inaktiv ohne Rolle 24 Monate (Hinweis 30 Tage vorher); auf Wunsch endgültig nach [30 Tagen]',
            'tables' => ['users', 'user_identities', 'roles', 'user_roles', 'role_permissions'],
            'operations' => [],
        ],
        'security_log' => [
            'purpose' => 'Nachvollziehbarkeit und Informationssicherheit: Änderungen, Rechte, Anmeldedienste, Servicekonten',
            'basis' => 'Pflicht zur Informationssicherheit und Nachvollziehbarkeit von Änderungen',
            'scope' => 'Plattform/intern',
            'subjects' => 'Personen, die etwas ändern oder deren Konto geändert wird',
            'recipients' => 'Developer und Verwaltung des Glossary',
            'retention' => '[5 Jahre] nach Eintrag; personenbezogene Werte nie gespeichert (nur «geändert»); Bezug bei Kontolöschung entfernt',
            'tables' => ['user_audit', 'service_account_audit', 'data_changes'],
            'operations' => [],
        ],
        'organizations' => [
            'purpose' => 'Organisationen (Schulen, Gemeinden): Mitglieder, Einladungen, Darstellung der Organisation',
            'basis' => 'Nutzungsbedingungen; Organisation durch eine berechtigte Person angelegt',
            'scope' => 'Organisation',
            'subjects' => 'Mitglieder und eingeladene Personen einer Organisation',
            'recipients' => 'Mitglieder der Organisation; Servicekonten der Organisation (Scope glossary:terms.read)',
            'retention' => 'Einladungen bis Ablauf; Organisation im Papierkorb [30 Tage], dann endgültig',
            'tables' => ['organizations', 'organization_members', 'organization_invitations', 'term_organization_overrides'],
            'operations' => ['listOrganizationTerms', 'getOrganizationTerm'],
        ],
        'personal_display' => [
            'purpose' => 'Persönliche Darstellung von Begriffen («Meine Darstellung»)',
            'basis' => 'Nutzungsbedingungen; freiwillige Angaben der Person',
            'scope' => 'Persönlich',
            'subjects' => 'die Person selbst',
            'recipients' => 'nur die Person; nach Freigabe ihre Organisation',
            'retention' => 'mit dem Konto',
            'tables' => ['term_user_overrides'],
            'operations' => [],
        ],
        'proposals' => [
            'purpose' => 'Vorschläge für neue Begriffe und Korrekturen, Moderation',
            'basis' => 'Nutzungsbedingungen; Mitwirkung am Verzeichnis',
            'scope' => 'Organisation bzw. Persönlich',
            'subjects' => 'Personen und Servicekonten, die Vorschläge einreichen; Moderation',
            'recipients' => 'Moderation; Organisations-Admins für Vorschläge ihrer Mitglieder',
            'retention' => '24 Monate nach Abschluss mit Personenbezug, dann ohne; bei Kontolöschung sofort ohne',
            'tables' => ['proposals', 'proposal_events'],
            'operations' => ['createProposal', 'getProposal', 'withdrawProposal'],
        ],
        'integrations' => [
            'purpose' => 'Schnittstellen für Integrationen (Servicekonten und Tokens), Ereignisse der Plattform',
            'basis' => 'Nutzungsbedingungen; Servicekonto durch eine berechtigte Person angelegt',
            'scope' => 'Organisation bzw. Plattform/intern',
            'subjects' => 'verantwortliche Person des Servicekontos',
            'recipients' => 'Integrationen mit gültigem Token (z. B. Intranet); EduKSX-Ident (Ereignisse)',
            'retention' => 'Tokens bis Ablauf bzw. Widerruf; Ereignis-IDs 30 Tage; Idempotency-Keys 24 Stunden',
            'tables' => ['service_accounts', 'service_account_tokens', 'received_events', 'api_idempotency_keys'],
            'operations' => ['getServiceWhoami'],
        ],
        'operations' => [
            'purpose' => 'Betrieb und Verbesserung: Einstellungen, Importe aus dem Intranet, Suchen ohne Treffer',
            'basis' => 'Betrieb des Verzeichnisses',
            'scope' => 'Plattform/intern',
            'subjects' => 'Person, die importiert (als Bezeichnung); sonst keine',
            'recipients' => 'Redaktion und Verwaltung des Glossary',
            'retention' => 'Import-Protokoll 12 Monate; Suchen ohne Treffer 90 Tage nach dem letzten Auftreten; Einstellungen dauerhaft',
            'tables' => ['settings', 'import_batches', 'search_misses'],
            'operations' => [],
        ],
    ];

    /** Zweck einer Tabelle; null, wenn keiner festgelegt ist. */
    public static function of(string $table): ?string
    {
        foreach (self::MAP as $key => $purpose) {
            if (in_array($table, $purpose['tables'], true)) {
                return $key;
            }
        }

        return null;
    }

    /** Höchste Datenklasse der Spalten einer Tabelle (aus DataClasses). */
    public static function highestClass(string $table): DataClass
    {
        $order = [DataClass::Public, DataClass::Internal, DataClass::Personal, DataClass::Sensitive, DataClass::Secret];
        $max = 0;
        foreach (DataClasses::MAP[$table] ?? [] as $class) {
            $max = max($max, (int) array_search($class, $order, true));
        }

        return $order[$max];
    }
}
