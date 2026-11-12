<?php

namespace App\Database;

use App\Enums\AuditResult;
use App\Enums\ChangeType;
use App\Enums\DuplicateMatchType;
use App\Enums\Evidence;
use App\Enums\InstitutionType;
use App\Enums\ProposalEventType;
use App\Enums\ProposalStatus;
use App\Enums\TermStatus;
use Closure;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Übergang einer Datenbank bis 0.11.0 auf den bereinigten Anfangsstand 0.12.0 (DEC-030, DEC-033, #266; DB-Standard A5).
 *
 * Läuft in der Transaktion der Migration – bei einem Fehler wird alles zurückgerollt:
 *
 * 1. Zeilen je Tabelle zählen, alte Tabellen in `alt_*` umbenennen (Indizes vorher entfernen, Namen sind global).
 * 2. Neue Tabellen anlegen (STRICT, CHECK-Regeln).
 * 3. Entfallende Werte verschlüsselt archivieren (`storage/app/archiv/`, Datenklasse intern, Frist 12 Monate):
 *    Altwerte, Text der Suchbegriffe, `term_sources.url`, `user_identities.object_id`, `import_batches.legacy_count`.
 * 4. Daten übernehmen: umbenannte Spalten, Werte englisch, Suchbegriffe nur als Aliase (fehlende ergänzt).
 * 5. Zeilen je Tabelle vergleichen, Fremdschlüssel prüfen; bei Abweichung Abbruch.
 * 6. Alte Tabellen und die Einträge der alten Migrationen entfernen; Bericht ohne Inhalte ablegen.
 */
final class Uebergang012
{
    /** Spalten umbenennen: Tabelle → [alt → neu]. */
    private const RENAME = [
        'users' => ['password_set' => 'has_password', 'news_opt_in' => 'has_news_opt_in'],
        'term_revisions' => ['approved_by' => 'approved_by_id'],
        'term_sources' => ['retrieved_at' => 'retrieved_on'],
        'publication_releases' => ['published_by' => 'published_by_id'],
        'term_publication_events' => ['merged_into' => 'merged_into_id'],
        'duplicate_decisions' => ['kind' => 'match_type', 'decided_by' => 'decided_by_id'],
        'settings' => ['updated_by' => 'updated_by_id'],
        'organizations' => ['created_by' => 'created_by_id'],
        'organization_members' => ['granted_role' => 'is_role_granted'],
        'organization_invitations' => ['invited_by' => 'invited_by_id'],
        'organization_term_overrides' => ['updated_by' => 'updated_by_id'],
        'term_user_overrides' => ['approved_by' => 'approved_by_id'],
        'proposals' => ['kind' => 'proposal_type'],
        'proposal_events' => ['type' => 'event_type', 'by_author' => 'is_by_author'],
    ];

    /** Spalten, die entfallen (archiviert): Tabelle → Spalten. */
    private const DROP = [
        'terms' => ['search_terms'],
        'term_sources' => ['url'],
        'user_identities' => ['object_id'],
        'import_batches' => ['legacy_count'],
    ];

    /** Tabellen, die entfallen (archiviert). */
    private const DROP_TABLES = ['term_legacy_values'];

    /** Reihenfolge der Übernahme (Eltern vor Kindern). */
    private const ORDER = [
        'users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        'roles', 'role_permissions', 'user_roles', 'user_audit', 'user_identities',
        'categories', 'sources', 'terms', 'term_revisions', 'term_aliases', 'term_categories', 'term_sources',
        'publication_releases', 'term_publication_events', 'duplicate_decisions', 'import_batches', 'settings',
        'organizations', 'organization_members', 'organization_invitations', 'organization_term_overrides',
        'term_user_overrides', 'service_accounts', 'service_account_tokens', 'service_account_audit',
        'proposals', 'proposal_events',
    ];

    /** @var array<string, int> */
    private array $before = [];

    /** @var array<string, int> */
    private array $after = [];

    private int $addedSearchAliases = 0;

    /** Bestand bis 0.11.0 erkennbar an `terms.search_terms` oder der Tabelle der Altwerte. */
    public function altbestandVorhanden(): bool
    {
        return Schema::hasTable('term_legacy_values')
            || (Schema::hasTable('terms') && Schema::hasColumn('terms', 'search_terms'));
    }

    /** @param  Closure(): void  $createTables */
    public function run(Closure $createTables): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new RuntimeException('Übergang auf 0.12.0 nur für SQLite (Bestand bis 0.11.0).');
        }

        $old = $this->oldTables();
        foreach ($old as $table) {
            $this->before[$table] = DB::table($table)->count();
        }
        foreach ($old as $table) {
            $this->moveAside($table);
        }

        $createTables();

        $archive = $this->archive();
        foreach (self::ORDER as $table) {
            if (in_array($table, $old, true)) {
                $this->copy($table);
            }
        }
        $this->linkTerms();
        $this->addMissingSearchAliases();
        $this->verify($old);

        foreach (array_merge(self::DROP_TABLES, array_reverse(self::ORDER)) as $table) {
            if (in_array($table, $old, true)) {
                Schema::drop('alt_'.$table);
            }
        }
        DB::table('migrations')->delete();

        $violations = DB::select('pragma foreign_key_check');
        if ($violations !== []) {
            throw new RuntimeException('Fremdschlüssel verletzt nach dem Übergang: '.json_encode($violations));
        }

        $this->report($archive);
    }

    /** @return list<string> */
    private function oldTables(): array
    {
        $rows = DB::select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%' and name <> 'migrations' and name not like 'alt_%'");

        return array_values(array_map(fn (object $r): string => (string) $r->name, $rows));
    }

    private function moveAside(string $table): void
    {
        $indexes = DB::select("select name from sqlite_master where type = 'index' and tbl_name = ? and sql is not null", [$table]);
        foreach ($indexes as $index) {
            DB::statement('drop index "'.$index->name.'"');
        }
        DB::statement('alter table "'.$table.'" rename to "alt_'.$table.'"');
    }

    /** Entfallende Werte verschlüsselt sichern; liefert den Dateinamen. */
    private function archive(): ?string
    {
        $data = [];
        if (Schema::hasTable('alt_term_legacy_values')) {
            $data['term_legacy_values'] = DB::table('alt_term_legacy_values')->get()->all();
        }
        foreach (self::DROP as $table => $columns) {
            if (! Schema::hasTable('alt_'.$table)) {
                continue;
            }
            $key = $table === 'term_sources' ? ['term_id', 'source_id'] : ['id'];
            foreach ($columns as $column) {
                if (! Schema::hasColumn('alt_'.$table, $column)) {
                    continue;
                }
                $data[$table.'.'.$column] = DB::table('alt_'.$table)
                    ->whereNotNull($column)->where($column, '<>', '')
                    ->get([...$key, $column])->all();
            }
        }
        $data = array_filter($data, fn (array $rows): bool => $rows !== []);
        if ($data === []) {
            return null;
        }

        $dir = storage_path('app/archiv');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $name = 'uebergang-0.12.0-'.now()->utc()->format('Ymd\THis\Z').'.json.enc';
        $payload = [
            'beschreibung' => 'Beim Übergang auf 0.12.0 entfallene Werte (DEC-030, #266)',
            'datenklasse' => 'intern',
            'loeschen_nach' => now()->addMonths(12)->toDateString(),
            'daten' => $data,
        ];
        file_put_contents($dir.'/'.$name, Crypt::encryptString((string) json_encode($payload, JSON_UNESCAPED_UNICODE)));

        return $name;
    }

    private function copy(string $table): void
    {
        $rename = self::RENAME[$table] ?? [];
        $drop = self::DROP[$table] ?? [];
        $newColumns = Schema::getColumnListing($table);
        $batch = [];
        foreach (DB::table('alt_'.$table)->cursor() as $row) {
            $values = [];
            foreach ((array) $row as $column => $value) {
                if (in_array($column, $drop, true)) {
                    continue;
                }
                $target = $rename[$column] ?? $column;
                if (! in_array($target, $newColumns, true)) {
                    throw new RuntimeException("Spalte {$table}.{$column} hat kein Ziel im Anfangsstand.");
                }
                $values[$target] = $this->translate($table, $target, $value);
            }
            if ($table === 'terms') {
                // Verweise erst setzen, wenn alle Begriffe und Revisionen da sind (linkTerms)
                $values['published_revision_id'] = null;
                $values['merged_into_id'] = null;
            }
            $batch[] = $values;
            if (count($batch) >= 200) {
                DB::table($table)->insert($batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            DB::table($table)->insert($batch);
        }
    }

    /** Werte englisch (KONZEPT-KONVENTIONEN); unbekannte Werte brechen ab. */
    private function translate(string $table, string $column, mixed $value): mixed
    {
        $enum = match ("{$table}.{$column}") {
            'terms.lifecycle_status' => TermStatus::class,
            'proposals.status' => ProposalStatus::class,
            'proposal_events.event_type' => ProposalEventType::class,
            'term_sources.evidence' => Evidence::class,
            'users.institution_type' => InstitutionType::class,
            'term_revisions.change_type' => ChangeType::class,
            'duplicate_decisions.match_type' => DuplicateMatchType::class,
            'user_audit.result', 'service_account_audit.result' => AuditResult::class,
            default => null,
        };
        if ($enum === null || $value === null || $value === '') {
            return $value === '' && $enum !== null ? null : $value;
        }
        $case = $enum::fromAny((string) $value);
        if ($case === null) {
            throw new RuntimeException("Unbekannter Wert «{$value}» in {$table}.{$column}.");
        }

        return $case->value;
    }

    private function linkTerms(): void
    {
        foreach (DB::table('alt_terms')->whereNotNull('published_revision_id')->orWhereNotNull('merged_into_id')
            ->get(['id', 'published_revision_id', 'merged_into_id']) as $row) {
            DB::table('terms')->where('id', $row->id)->update([
                'published_revision_id' => $row->published_revision_id,
                'merged_into_id' => $row->merged_into_id,
            ]);
        }
    }

    /** Suchbegriffe aus dem bisherigen Text, die noch nicht als Alias vom Typ `search` bestehen (E1). */
    private function addMissingSearchAliases(): void
    {
        foreach (DB::table('alt_terms')->whereNotNull('search_terms')->where('search_terms', '<>', '')
            ->get(['id', 'search_terms']) as $row) {
            $existing = DB::table('term_aliases')->where('term_id', $row->id)->where('alias_type', 'search')
                ->pluck('alias')->map(fn (string $a): string => mb_strtolower(trim($a)))->all();
            $parts = preg_split('/[;,]/', (string) $row->search_terms) ?: [];
            $seen = [];
            foreach ($parts as $part) {
                $alias = mb_substr(trim($part), 0, 120);
                $key = mb_strtolower($alias);
                if ($alias === '' || isset($seen[$key]) || in_array($key, $existing, true)) {
                    continue;
                }
                $seen[$key] = true;
                DB::table('term_aliases')->insert(['term_id' => $row->id, 'alias' => $alias, 'alias_type' => 'search', 'language' => 'de']);
                $this->addedSearchAliases++;
            }
        }
    }

    /** @param  list<string>  $old */
    private function verify(array $old): void
    {
        $errors = [];
        foreach ($old as $table) {
            if (in_array($table, self::DROP_TABLES, true)) {
                continue;
            }
            $expected = $this->before[$table] + ($table === 'term_aliases' ? $this->addedSearchAliases : 0);
            $this->after[$table] = DB::table($table)->count();
            if ($this->after[$table] !== $expected) {
                $errors[] = "{$table}: vorher {$this->before[$table]}, erwartet {$expected}, nachher {$this->after[$table]}";
            }
        }
        $unknown = array_diff($old, self::ORDER, self::DROP_TABLES);
        if ($unknown !== []) {
            $errors[] = 'Tabellen ohne Übernahme: '.implode(', ', $unknown);
        }
        if ($errors !== []) {
            throw new RuntimeException("Übergang abgebrochen, Zählung stimmt nicht:\n".implode("\n", $errors));
        }
    }

    private function report(?string $archive): void
    {
        $report = [
            'zeitpunkt' => now()->toIso8601String(),
            'zeilen_vorher' => $this->before,
            'zeilen_nachher' => $this->after,
            'suchbegriffe_ergaenzt' => $this->addedSearchAliases,
            'archiv' => $archive,
        ];
        $dir = storage_path('app/archiv');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        file_put_contents($dir.'/uebergang-0.12.0-bericht.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        Log::info('Übergang auf 0.12.0 abgeschlossen', $report);
    }
}
