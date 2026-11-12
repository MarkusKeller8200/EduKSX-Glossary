<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Datenmodell für die Seite «Für Entwickler» (#170): Tabellen, Spalten, Schlüssel und Verweise aus der Datenbank
 * gelesen, als ER-Diagramm (Mermaid) und als SQL-Script (nur Struktur, keine Daten). Je Stand der Migrationen
 * zwischengespeichert.
 */
final class DataModel
{
    /** Tabellen des Frameworks (Warteschlange, Sitzungen, Zwischenspeicher) – im Diagramm ausgeblendet. */
    public const FRAMEWORK = [
        'cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs', 'migrations', 'password_reset_tokens', 'sessions',
    ];

    /** Kurzbeschreibung je Tabelle. */
    public const DESCRIPTIONS = [
        'terms' => 'Begriff (Arbeitsstand); öffentlich ist nur die veröffentlichte Revision',
        'term_revisions' => 'Unveränderliche Revisionen mit Abbild und Änderungsjournal',
        'term_aliases' => 'Offizielle und alternative Schreibweisen, Suchbegriffe',
        'term_categories' => 'Zuordnung Begriff ↔ Kategorie',
        'term_sources' => 'Zuordnung Begriff ↔ Herkunft mit Prüfdatum und Beleg',
        'term_user_overrides' => 'Meine Darstellung je Konto, mit Freigabe für die Organisation',
        'term_publication_events' => 'Veröffentlichen, Zurückziehen, Zusammenführen (Change-Feed)',
        'publication_releases' => 'Datenstände mit Nummer und Prüfsumme',
        'term_translations' => 'Übersetzungen eines Begriffs (fr, it, en); Teil der Revision',
        'category_translations' => 'Übersetzungen einer Kategorie',
        'source_translations' => 'Übersetzungen einer Herkunft',
        'categories' => 'Liste Kategorien',
        'sources' => 'Liste Herkunft',
        'duplicate_decisions' => 'Als mehrdeutig belassene Doppelte',
        'import_batches' => 'Protokoll der Excel-Importe',
        'proposals' => 'Vorschläge (neu, Korrektur) mit Status',
        'proposal_events' => 'Verlauf eines Vorschlags',
        'users' => 'Persönliche Konten',
        'user_identities' => 'Anmeldedienste (Microsoft)',
        'user_audit' => 'Protokoll zu Konten, Rollen und Organisationen',
        'roles' => 'Rollen',
        'role_permissions' => 'Rechte je Rolle',
        'user_roles' => 'Rollen je Konto',
        'service_accounts' => 'Servicekonten (auch einer Organisation)',
        'service_account_tokens' => 'Token (nur Hash)',
        'service_account_audit' => 'Protokoll der Servicekonten',
        'settings' => 'Allgemeine Einstellungen',
        'organizations' => 'Organisationen',
        'organization_members' => 'Mitgliedschaften (eine je Konto)',
        'organization_invitations' => 'Einladungen (14 Tage)',
        'organization_term_overrides' => 'Darstellung der Organisation je Begriff',
    ];

    /**
     * @return list<array{name: string, beschreibung: string|null, framework: bool, spalten: list<array{name: string, typ: string, pflicht: bool, vorgabe: string|null, schluessel: string|null, verweis: string|null}>}>
     */
    public function tables(): array
    {
        /** @var list<array{name: string, beschreibung: string|null, framework: bool, spalten: list<array{name: string, typ: string, pflicht: bool, vorgabe: string|null, schluessel: string|null, verweis: string|null}>}> */
        return Cache::remember($this->key('tables'), 3600, function (): array {
            $tables = [];
            foreach (Schema::getTables() as $info) {
                $table = (string) $info['name'];
                if (str_starts_with($table, 'sqlite_')) {
                    continue;
                }
                $primary = [];
                $unique = [];
                foreach (Schema::getIndexes($table) as $index) {
                    $columns = array_map('strval', (array) $index['columns']);
                    if ($index['primary']) {
                        $primary = $columns;
                    } elseif ($index['unique'] && count($columns) === 1) {
                        $unique[] = $columns[0];
                    }
                }
                $references = [];
                foreach (Schema::getForeignKeys($table) as $fk) {
                    foreach (array_map('strval', (array) $fk['columns']) as $i => $column) {
                        $references[$column] = $fk['foreign_table'].'.'.((array) $fk['foreign_columns'])[$i];
                    }
                }
                $columns = [];
                foreach (Schema::getColumns($table) as $column) {
                    $name = (string) $column['name'];
                    $columns[] = [
                        'name' => $name,
                        'typ' => (string) $column['type'],
                        'pflicht' => ! $column['nullable'],
                        'vorgabe' => $column['default'] === null ? null : (string) $column['default'],
                        'schluessel' => in_array($name, $primary, true) ? 'PK' : (isset($references[$name]) ? 'FK' : (in_array($name, $unique, true) ? 'UK' : null)),
                        'verweis' => $references[$name] ?? null,
                    ];
                }
                $tables[] = [
                    'name' => $table,
                    'beschreibung' => self::DESCRIPTIONS[$table] ?? null,
                    'framework' => in_array($table, self::FRAMEWORK, true),
                    'spalten' => $columns,
                ];
            }
            usort($tables, fn (array $a, array $b): int => [$a['framework'], $a['name']] <=> [$b['framework'], $b['name']]);

            return $tables;
        });
    }

    /** ER-Diagramm (Mermaid erDiagram) der Tabellen des Glossary, ohne Framework-Tabellen. */
    public function mermaid(): string
    {
        $lines = ['erDiagram'];
        $tables = array_filter($this->tables(), fn (array $t): bool => ! $t['framework']);
        foreach ($tables as $table) {
            $lines[] = '    '.$table['name'].' {';
            foreach ($table['spalten'] as $column) {
                $type = preg_replace('/[^A-Za-z0-9_]/', '_', $column['typ']) ?: 'text';
                $lines[] = '        '.$type.' '.$column['name'].($column['schluessel'] !== null ? ' '.$column['schluessel'] : '');
            }
            $lines[] = '    }';
        }
        foreach ($tables as $table) {
            foreach ($table['spalten'] as $column) {
                if ($column['verweis'] === null) {
                    continue;
                }
                $target = explode('.', $column['verweis'])[0];
                $lines[] = '    '.$target.' ||--o{ '.$table['name'].' : "'.$column['name'].'"';
            }
        }

        return implode("\n", $lines)."\n";
    }

    /** SQL-Script der Struktur (SQLite), ohne Daten. */
    public function sql(): string
    {
        $out = ['-- EduKSX-Glossary – Datenmodell (nur Struktur, keine Daten), '.now()->toDateString(), '-- Datenbank: '.DB::connection()->getDriverName(), ''];
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return implode("\n", [...$out, '-- Script nur für SQLite verfügbar.', '']);
        }
        $rows = DB::select("select type, name, sql from sqlite_master where sql is not null and name not like 'sqlite_%' order by case type when 'table' then 0 else 1 end, name");
        foreach ($rows as $row) {
            $out[] = trim((string) $row->sql).';';
            $out[] = '';
        }

        return implode("\n", $out);
    }

    private function key(string $name): string
    {
        return 'datamodel.'.$name.'.'.(int) DB::table('migrations')->max('id');
    }
}
