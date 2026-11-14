<?php

namespace Tests\Feature;

use App\Access\Audit;
use App\Glossary\GlossaryService;
use App\Models\Role;
use App\Proposals\ProposalService;
use App\Support\DatabaseDump;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

/** Anonymisierte Kopien für dev und test (#313, DB-Standard B12). */
final class AnonymisierungTest extends TestCase
{
    private function dump(bool $anonymize): string
    {
        $file = storage_path('framework/testing/dump-'.($anonymize ? 'anon' : 'voll').'.zip');
        DatabaseDump::create($file, null, null, $anonymize);

        return $file;
    }

    private function daten(): void
    {
        $lea = $this->userWithRoles([Role::USER], 'lea.muster@schule.example');
        $lea->forceFill(['first_name' => 'Lea', 'last_name' => 'Muster', 'community_name' => 'LeaM', 'institution_name' => 'Schule Muster'])->save();
        $lea->identities()->create(['provider' => 'microsoft', 'issuer' => 'https://login.example/v2.0', 'subject' => 'sub-lea-123']);
        $glossary = app(GlossaryService::class);
        $term = $glossary->publish($glossary->save(null, ['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung'], null, true), null);
        app(ProposalService::class)->submit($term, ['short_description' => 'Neu'], 'Begründung von Lea Muster', $lea);
        Audit::log($lea, $lea, 'account.updated', ['email' => 'lea.muster@schule.example']);
    }

    public function test_anonymisierter_dump_ohne_personendaten_mit_gleichen_mengen(): void
    {
        $this->daten();
        $file = $this->dump(true);
        $zip = new ZipArchive;
        $zip->open($file);
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $content = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $content .= (string) $zip->getFromIndex($i);
        }
        $zip->close();

        $this->assertTrue($manifest['anonymized']);
        $this->assertFalse($manifest['sqlite_file']);
        foreach (['lea.muster@schule.example', 'Muster', 'LeaM', 'sub-lea-123', 'Begründung von Lea'] as $real) {
            $this->assertStringNotContainsString($real, $content, $real);
        }
        // öffentliche Fachdaten bleiben, Mengen gleich
        $this->assertStringContainsString('Schulleitung', $content);
        foreach ($manifest['tables'] as $table => $count) {
            $this->assertSame(DB::table($table)->count(), $count, $table);
        }
        @unlink($file);
    }

    public function test_einspielen_nur_anonymisiert_und_mit_gleichen_zeilen(): void
    {
        $this->onlySqlite();   // Einspielen nur auf SQLite (dev, test)
        $this->daten();
        $full = $this->dump(false);
        $anon = $this->dump(true);
        $counts = ['users' => DB::table('users')->count(), 'proposals' => DB::table('proposals')->count(), 'terms' => DB::table('terms')->count()];

        $this->artisan('glossary:dump-einspielen', ['datei' => $full, '--ja' => true])
            ->expectsOutputToContain('nicht anonymisiert')->assertFailed();
        $this->assertSame(1, DB::table('users')->where('email', 'lea.muster@schule.example')->count());

        $this->artisan('glossary:dump-einspielen', ['datei' => $anon, '--ja' => true])->assertSuccessful();
        foreach ($counts as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }
        $this->assertSame(0, DB::table('users')->where('email', 'lea.muster@schule.example')->count());
        $this->assertGreaterThan(0, DB::table('users')->where('email', 'like', 'person-%@example.invalid')->count());
        $this->assertSame([], DB::select('pragma foreign_key_check'));
        @unlink($full);
        @unlink($anon);
    }
}
