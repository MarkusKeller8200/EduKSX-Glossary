<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use App\Models\Category;
use App\Models\Role;
use App\Proposals\ProposalService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Umzug SQLite → PostgreSQL (#191, #333): Daten aus einer SQLite-Datei in das Schema «glossary» übernehmen, Zählung
 * gleich, Verweise (Begriff ↔ Revision) und Zähler stimmen. Läuft im PostgreSQL-Job der CI.
 */
final class UmzugPostgresTest extends TestCase
{
    public function test_umzug_ohne_datenverlust(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('nur mit PostgreSQL (CI-Job postgres)');
        }
        $default = DB::getDefaultConnection();
        $file = storage_path('framework/testing/umzug.sqlite');
        File::put($file, '');
        config(['database.connections.quelle' => ['driver' => 'sqlite', 'database' => $file, 'prefix' => '', 'foreign_key_constraints' => true]]);
        Artisan::call('migrate', ['--database' => 'quelle', '--force' => true]);

        // Daten in der Quelle (SQLite): Konten, Begriffe mit Revisionen, Kategorie, Vorschlag, Protokoll
        DB::setDefaultConnection('quelle');
        try {
            $admin = $this->admin();
            Category::query()->create(['key' => 'per', 'abbreviation' => 'PER', 'name' => 'Personal', 'label' => 'Personal']);
            $glossary = app(GlossaryService::class);
            $term = $glossary->publish($glossary->save(null, ['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung', 'categories' => ['per']], null, true), null);
            $glossary->publish($glossary->save($term, ['short_description' => 'Leitet die Schule'], $admin, true), $admin);
            $member = $this->userWithRoles([Role::USER], 'lea@schule.example');
            app(ProposalService::class)->submit(null, ['official_name' => 'Hauswart'], null, $member);
            $source = DB::connection('quelle');
            $counts = [];
            foreach (['users', 'terms', 'term_revisions', 'term_categories', 'proposals', 'proposal_events', 'term_publication_events', 'data_changes', 'user_audit'] as $table) {
                $counts[$table] = $source->table($table)->count();
            }
        } finally {
            DB::setDefaultConnection($default);
        }
        $this->assertGreaterThan(0, $counts['term_revisions']);

        $this->artisan('glossary:umzug-postgres', ['--quelle' => 'quelle', '--ziel' => $default, '--leeren' => true])->assertSuccessful();

        foreach ($counts as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }
        $published = DB::table('terms')->value('published_revision_id');
        $this->assertNotNull($published, 'Verweis Begriff → Revision gesetzt');
        // Zähler folgen dem höchsten Wert: neue Zeile ohne Kollision
        $max = (int) DB::table('term_publication_events')->max('id');
        $this->assertGreaterThan($max, (int) DB::selectOne("select nextval(pg_get_serial_sequence('term_publication_events', 'id')) as n")->n);
        File::delete($file);
    }
}
