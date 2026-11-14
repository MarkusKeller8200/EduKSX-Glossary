<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Namen nach DB-Standard A1 (#330): Umbenennen ohne Datenverlust (Zählung vorher = nachher). */
final class UmbenennungTest extends TestCase
{
    private const MIGRATION = '2026_11_14_000600_namen_nach_db_standard';

    public function test_umbenennen_ohne_datenverlust(): void
    {
        $this->onlySqlite();
        $file = storage_path('framework/testing/umbenennung.sqlite');
        $dir = storage_path('framework/testing/migrationen-vorher');
        File::ensureDirectoryExists($dir);
        File::cleanDirectory($dir);
        File::put($file, '');
        foreach (File::files(database_path('migrations')) as $migration) {
            if (strcmp($migration->getFilenameWithoutExtension(), self::MIGRATION) < 0) {
                File::copy($migration->getPathname(), $dir.'/'.$migration->getFilename());
            }
        }
        config(['database.connections.umbenennung' => ['driver' => 'sqlite', 'database' => $file, 'prefix' => '', 'foreign_key_constraints' => true]]);
        Artisan::call('migrate', ['--database' => 'umbenennung', '--path' => $dir, '--realpath' => true, '--force' => true]);
        $db = DB::connection('umbenennung');

        // Daten in jeder betroffenen Tabelle
        $user = (string) Str::uuid7();
        $term = (string) Str::uuid7();
        $org = (string) Str::uuid7();
        $db->table('users')->insert(['id' => $user, 'name' => 'Lea', 'email' => 'lea@schule.example', 'password' => 'x', 'status' => 'active']);
        $db->table('organizations')->insert(['id' => $org, 'name' => 'Schule', 'created_by_id' => $user]);
        $db->table('terms')->insert(['id' => $term, 'slug' => 'sl', 'code' => 'ABC234', 'official_name' => 'Schulleitung', 'lifecycle_status' => 'historical']);
        $db->table('term_aliases')->insert(['term_id' => $term, 'alias' => 'SL', 'alias_type' => 'official', 'language' => 'de']);
        $db->table('user_identities')->insert(['user_id' => $user, 'provider' => 'microsoft', 'issuer' => 'https://login.example/v2.0', 'subject' => 's1', 'tenant_id' => 't1']);
        $db->table('organization_term_overrides')->insert(['id' => (string) Str::uuid7(), 'organization_id' => $org, 'term_id' => $term, 'name_override' => 'Leitung']);
        $db->table('proposals')->insert(['proposal_type' => 'new', 'changes' => '{}', 'status' => 'submitted', 'user_id' => $user, 'author_label' => 'Lea', 'uuid' => (string) Str::uuid7()]);
        $db->table('import_batches')->insert(['user_id' => $user, 'file_name' => 'a.xlsx', 'format_version' => 1, 'rows' => 1, 'created_count' => 1, 'updated_count' => 0, 'error_count' => 0]);
        $db->table('user_audit')->insert(['action' => 'x', 'old' => '{"a":1}', 'new' => '{"a":2}', 'result' => 'ok', 'event_id' => (string) Str::uuid7()]);
        $tables = ['terms', 'term_aliases', 'user_identities', 'organization_term_overrides', 'proposals', 'import_batches', 'user_audit', 'term_publication_events'];
        $before = array_map(fn (string $t): int => $db->table($t)->count(), $tables);

        /** @var Migration $rename */
        $rename = require database_path('migrations/'.self::MIGRATION.'.php');
        $default = DB::getDefaultConnection();
        DB::setDefaultConnection('umbenennung');
        try {
            $rename->up();
        } finally {
            DB::setDefaultConnection($default);
        }

        $after = array_map(fn (string $t): int => $db->table(str_replace('organization_term_overrides', 'term_organization_overrides', $t))->count(), $tables);
        $this->assertSame($before, $after);
        $this->assertSame('historical', $db->table('terms')->value('status'));
        $this->assertSame('de', $db->table('term_aliases')->value('locale'));
        $this->assertSame('t1', $db->table('user_identities')->value('entra_tenant_id'));
        $this->assertSame('Leitung', $db->table('term_organization_overrides')->value('name_override'));
        $this->assertSame($user, $db->table('proposals')->value('author_id'));
        $this->assertSame($user, $db->table('import_batches')->value('imported_by_id'));
        $this->assertSame('{"a":2}', $db->table('user_audit')->value('new_values'));
        $this->assertSame([], $db->select('pragma foreign_key_check'));
        File::delete($file);
        File::deleteDirectory($dir);
    }
}
