<?php

namespace Tests\Feature;

use App\Console\Commands\TakeOverSqlite;
use App\Glossary\GlossaryService;
use App\Models\Role;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** SQLite beim ersten Start auf PostgreSQL übernehmen (#357). Läuft im PostgreSQL-Job der CI. */
final class PostgresUebernahmeTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = storage_path('framework/testing/uebernahme.sqlite');
        File::delete([$this->file, TakeOverSqlite::markerPath()]);
        File::delete(glob(storage_path('app/backups/vor-postgres-*.sqlite')) ?: []);
    }

    protected function tearDown(): void
    {
        File::delete([$this->file, TakeOverSqlite::markerPath()]);
        File::delete(glob(storage_path('app/backups/vor-postgres-*.sqlite')) ?: []);
        parent::tearDown();
    }

    /** SQLite-Datei mit einem Konto und einem Begriff */
    private function quelle(): void
    {
        File::put($this->file, '');
        config(['database.connections.quelle' => ['driver' => 'sqlite', 'database' => $this->file, 'prefix' => '', 'foreign_key_constraints' => true]]);
        Artisan::call('migrate', ['--database' => 'quelle', '--force' => true]);
        $default = DB::getDefaultConnection();
        DB::setDefaultConnection('quelle');
        try {
            $this->userWithRoles([Role::ADMIN], 'admin@schule.example');
            $glossary = app(GlossaryService::class);
            $glossary->publish($glossary->save(null, ['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung'], null, true), null);
        } finally {
            DB::setDefaultConnection($default);
        }
    }

    public function test_auf_sqlite_nichts_zu_tun(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('nur SQLite');
        }
        $this->artisan('glossary:postgres-uebernahme')->assertSuccessful();
        $this->assertFileDoesNotExist(TakeOverSqlite::markerPath());
    }

    public function test_uebernahme_einmalig_mit_kopie(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('nur mit PostgreSQL (CI-Job postgres)');
        }
        $this->quelle();
        DB::table('users')->delete();   // frisch migriert: noch keine Konten

        $this->artisan('glossary:postgres-uebernahme', ['--quelle' => 'quelle'])
            ->expectsOutputToContain('übernommen')->assertSuccessful();

        $this->assertSame(1, DB::table('users')->where('email', 'admin@schule.example')->count());
        $this->assertSame(1, DB::table('terms')->count());
        $this->assertFileExists(TakeOverSqlite::markerPath());
        $this->assertCount(1, glob(storage_path('app/backups/vor-postgres-*.sqlite')) ?: []);

        // zweiter Start: nichts mehr
        $this->artisan('glossary:postgres-uebernahme', ['--quelle' => 'quelle'])
            ->doesntExpectOutputToContain('übernommen')->assertSuccessful();
    }

    public function test_keine_uebernahme_wenn_postgres_schon_daten_hat(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('nur mit PostgreSQL (CI-Job postgres)');
        }
        $this->quelle();
        $this->userWithRoles([Role::USER], 'schon-da@schule.example');

        $this->artisan('glossary:postgres-uebernahme', ['--quelle' => 'quelle'])
            ->expectsOutputToContain('schon Konten')->assertSuccessful();
        $this->assertSame(0, DB::table('users')->where('email', 'admin@schule.example')->count());
        $this->assertFileDoesNotExist(TakeOverSqlite::markerPath());
    }
}
