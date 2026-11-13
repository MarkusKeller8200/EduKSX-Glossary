<?php

namespace Tests\Feature;

use App\Database\StrictSchema;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Bereinigter Anfangsstand 0.12.0 (#266, DB-Standard A4, A5, C3): STRICT-Tabellen mit CHECK-Regeln, Fremdschlüssel
 * und der Übergang einer Datenbank bis 0.11.0 ohne Datenverlust.
 */
class AnfangsstandTest extends TestCase
{
    public function test_tabellen_sind_strict_mit_check_regeln_und_ohne_verletzungen(): void
    {
        $this->onlySqlite();
        $tables = DB::select("select name, sql from sqlite_master where type = 'table' and name not like 'sqlite_%' and name <> 'migrations'");
        $this->assertNotEmpty($tables);
        foreach ($tables as $table) {
            $this->assertStringEndsWith('strict', strtolower(trim((string) $table->sql)), "{$table->name} ist nicht STRICT");
        }
        $this->assertSame('ok', DB::selectOne('pragma integrity_check')->integrity_check);
        $this->assertSame([], DB::select('pragma foreign_key_check'));

        $this->expectException(QueryException::class);
        DB::table('terms')->insert(['id' => (string) str()->uuid(), 'slug' => 'x', 'lifecycle_status' => 'aktuell']);
    }

    public function test_strict_typen_werden_abgebildet(): void
    {
        $sql = StrictSchema::strictSql('create table "t" ("a" varchar not null, "b" tinyint(1) not null, "c" datetime, "d" date)', ['t_b_check' => '"b" in (0, 1)']);

        $this->assertSame('create table "t" ("a" TEXT not null, "b" INTEGER not null, "c" TEXT, "d" TEXT, constraint "t_b_check" check ("b" in (0, 1))) strict', $sql);
    }

    public function test_uebergang_aus_0_11_ohne_datenverlust(): void
    {
        $this->onlySqlite();
        $file = tempnam(sys_get_temp_dir(), 'glossary-0.11').'.sqlite';
        touch($file);
        $storage = sys_get_temp_dir().'/glossary-storage-'.bin2hex(random_bytes(4));
        mkdir($storage.'/app', 0777, true);
        $this->app->useStoragePath($storage);
        config(['database.connections.alt' => ['driver' => 'sqlite', 'database' => $file, 'prefix' => '', 'foreign_key_constraints' => true]]);
        $alt = DB::connection('alt');
        $alt->unprepared((string) file_get_contents(base_path('tests/Fixtures/schema-0.11.sql')));

        $user = '01a1b335-0000-7000-8000-000000000001';
        $term = '01a1b335-0000-7000-8000-0000000000a1';
        $rev = '01a1b335-0000-7000-8000-0000000000b1';
        $source = '01a1b335-0000-7000-8000-0000000000c1';
        $alt->table('migrations')->insert(['migration' => '2026_11_07_000100_create_glossary_tables', 'batch' => 1]);
        $alt->table('users')->insert(['id' => $user, 'name' => 'Anna Muster', 'first_name' => 'Anna', 'last_name' => 'Muster',
            'email' => 'anna@example.org', 'password' => 'x', 'status' => 'active', 'password_set' => 0, 'news_opt_in' => 1,
            'institution_type' => 'schule']);
        $alt->table('sources')->insert(['id' => $source, 'key' => 'vsa', 'name' => 'Volksschulamt', 'sort_order' => 0]);
        $alt->table('terms')->insert(['id' => $term, 'slug' => 'sl', 'code' => 'ABC234', 'official_abbreviation' => 'SL',
            'official_name' => 'Schulleitung', 'search_terms' => 'Rektorat; Schulleiter', 'lifecycle_status' => 'zu_pruefen',
            'is_active' => 1, 'revision' => 1, 'published_revision_id' => $rev]);
        $alt->table('term_revisions')->insert(['id' => $rev, 'term_id' => $term, 'revision' => 1, 'change_type' => 'created',
            'snapshot' => json_encode(['lifecycle_status' => 'zu_pruefen']), 'approved_by' => $user]);
        $alt->table('term_aliases')->insert(['term_id' => $term, 'alias' => 'Rektorat', 'alias_type' => 'search', 'language' => 'de']);
        $alt->table('term_sources')->insert(['term_id' => $term, 'source_id' => $source, 'url' => 'https://x.example',
            'retrieved_at' => '2026-11-01', 'evidence' => 'amtlich']);
        $alt->table('term_legacy_values')->insert(['term_id' => $term, 'field' => 'own_name', 'value' => 'Schulleitung eigen', 'origin' => 'migration']);
        $alt->table('proposals')->insert(['id' => 7, 'kind' => 'change', 'term_id' => $term, 'changes' => '{}', 'status' => 'rueckfrage', 'author_label' => 'Anna']);
        $alt->table('proposal_events')->insert(['proposal_id' => 7, 'type' => 'org_bestaetigt', 'by_author' => 1]);

        config(['database.default' => 'alt']);
        try {
            $this->pruefeUebergang($alt, $storage, $user, $term, $rev);
        } finally {
            config(['database.default' => 'sqlite']);
            DB::purge('alt');
            @unlink($file);
            array_map('unlink', glob($storage.'/app/archiv/*') ?: []);
        }
    }

    private function pruefeUebergang(Connection $alt, string $storage, string $user, string $term, string $rev): void
    {
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'alt', '--force' => true]));

        $this->assertSame('under_review', $alt->table('terms')->value('lifecycle_status'));
        $this->assertSame($rev, $alt->table('terms')->value('published_revision_id'));
        $this->assertSame($user, $alt->table('term_revisions')->value('approved_by_id'));
        $this->assertSame(['Rektorat', 'Schulleiter'], $alt->table('term_aliases')->orderBy('id')->pluck('alias')->all());
        $this->assertSame('official', $alt->table('term_sources')->value('evidence'));
        $this->assertSame('2026-11-01', $alt->table('term_sources')->value('retrieved_on'));
        $this->assertSame('school', $alt->table('users')->value('institution_type'));
        $this->assertSame(0, (int) $alt->table('users')->value('has_password'));
        $this->assertSame(1, (int) $alt->table('users')->value('has_news_opt_in'));
        $this->assertSame(['change', 'query'], [$alt->table('proposals')->value('proposal_type'), $alt->table('proposals')->value('status')]);
        $this->assertSame('confirmed_by_organization', $alt->table('proposal_events')->value('event_type'));
        $this->assertSame('2026_11_01_000000_anfangsstand', $alt->table('migrations')->orderBy('id')->value('migration'));
        $this->assertSame(0, $alt->table('migrations')->where('migration', 'like', '%create_glossary_tables')->count());
        $this->assertSame([], $alt->select("select name from sqlite_master where name like 'alt_%' or name = 'term_legacy_values'"));
        $this->assertSame([], $alt->select('pragma foreign_key_check'));

        // Entfallenes verschlüsselt archiviert, Bericht ohne Inhalte
        $archive = glob($storage.'/app/archiv/uebergang-0.12.0-*.json.enc') ?: [];
        $this->assertCount(1, $archive);
        $data = json_decode(Crypt::decryptString((string) file_get_contents($archive[0])), true)['daten'];
        $this->assertSame('Schulleitung eigen', $data['term_legacy_values'][0]['value']);
        $this->assertSame('Rektorat; Schulleiter', $data['terms.search_terms'][0]['search_terms']);
        $this->assertSame('https://x.example', $data['term_sources.url'][0]['url']);
        $report = json_decode((string) file_get_contents($storage.'/app/archiv/uebergang-0.12.0-bericht.json'), true);
        $this->assertSame(1, $report['suchbegriffe_ergaenzt']);
        $this->assertSame($report['zeilen_vorher']['terms'], $report['zeilen_nachher']['terms']);
    }
}
