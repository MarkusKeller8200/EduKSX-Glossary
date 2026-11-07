<?php

namespace Tests\Feature;

use App\Glossary\ExcelFormat;
use App\Glossary\GlossaryService;
use App\Glossary\Journal;
use App\Models\Term;
use App\Models\TermLegacyValue;
use App\Models\TermRevision;
use App\Models\TermUserOverride;
use App\Models\User;
use App\Support\SpreadsheetWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Gemeinsame und persönliche Daten, Änderungsjournal (DEC-020, #80–#83): Abnahmetests der Entwicklungsübergabe.
 */
class DataModelTest extends TestCase
{
    private GlossaryService $glossary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->glossary = app(GlossaryService::class);
    }

    /** @param  array<string, mixed>  $data */
    private function published(array $data): Term
    {
        return $this->glossary->publish($this->glossary->save(null, $data, null, true), null);
    }

    private function community(string $email): User
    {
        return User::query()->create(['name' => 'Konto '.$email, 'email' => $email, 'password' => 'ein-langes-Test-Passwort-1']);
    }

    // ---------- Journal (B) ----------

    public function test_journal_feldgenau_mit_akteur(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['community_name' => 'Redaktion Nord'])->save();

        $term = $this->glossary->save(null, [
            'official_abbreviation' => 'KLP', 'official_name' => 'Klassenlehrperson', 'alternative_aliases' => ['KL', 'Klassen-LP'],
        ], $admin, true);
        $created = $term->revisions()->first();
        $this->assertInstanceOf(TermRevision::class, $created);
        $this->assertSame(['old' => null, 'new' => 'KLP'], $created->change_set['official_abbreviation'] ?? null);
        $this->assertSame(['old' => null, 'new' => ['KL', 'Klassen-LP']], $created->change_set['alternative_aliases'] ?? null);
        $this->assertSame('admin', $created->actor_type);
        $this->assertSame('Redaktion Nord', $created->author_label);

        $term = $this->glossary->save($term, ['short_description' => 'Lehrperson einer Klasse.'], $admin, true);
        $latest = $term->revisions()->get()->last();
        $this->assertInstanceOf(TermRevision::class, $latest);
        $this->assertSame(['short_description' => ['old' => null, 'new' => 'Lehrperson einer Klasse.']], $latest->change_set);

        // gleiches Ergebnis (auch andere Reihenfolge der Liste) → keine Revision
        $this->glossary->save($term, ['alternative_aliases' => ['Klassen-LP', 'KL']], $admin, true);
        $this->assertSame(2, $term->refresh()->revision);
    }

    public function test_kontoloeschung_behaelt_journal_und_entfernt_persoenliches(): void
    {
        $user = $this->community('weg@example.org');
        $user->forceFill(['community_name' => 'Sonnenschein'])->save();
        $term = $this->glossary->save(null, ['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung'], $user, true);
        $this->glossary->publish($term, $user);
        $override = new TermUserOverride(['name_override' => 'Meine SL']);
        $override->term_id = $term->id;
        $override->user_id = $user->id;
        $override->save();

        $user->delete();

        $revision = TermRevision::query()->where('term_id', $term->id)->firstOrFail();
        $this->assertSame(Journal::DELETED_LABEL, $revision->author_label);
        $this->assertNull($revision->author_id);
        $this->assertNull($revision->approved_by);
        $this->assertSame(0, TermUserOverride::query()->count());
        $this->assertSame(1, Term::query()->count());
        $this->assertSame(Journal::DELETED_LABEL, DB::table('term_publication_events')->value('actor_label'));
    }

    // ---------- Meine Darstellung (C) ----------

    public function test_persoenliche_darstellung_nur_fuer_das_eigene_konto(): void
    {
        $term = $this->published(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung']);
        $a = $this->community('a@example.org');
        $b = $this->community('b@example.org');

        $this->actingAs($a)->put("/begriffe/{$term->slug}/meine-darstellung", [
            'name_override' => 'Schulleitung Primar', 'aliases_override' => 'SLP; Leitung',
        ])->assertRedirect();
        $this->assertSame(1, TermUserOverride::query()->where('user_id', $a->id)->count());

        $response = $this->actingAs($a)->get("/begriffe/{$term->slug}")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('begriff.bezeichnung', 'Schulleitung Primar')
                ->where('begriff.persoenlich', true)->where('begriff.gemeinsam.bezeichnung', 'Schulleitung'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->actingAs($a)->get('/')->assertInertia(fn (Assert $p) => $p->where('begriffe.0.bezeichnung', 'Schulleitung Primar'));

        $this->actingAs($b)->get("/begriffe/{$term->slug}")
            ->assertInertia(fn (Assert $p) => $p->where('begriff.bezeichnung', 'Schulleitung')->where('begriff.persoenlich', false));

        $this->app['auth']->forgetGuards();
        $this->get("/begriffe/{$term->slug}")
            ->assertInertia(fn (Assert $p) => $p->where('begriff.bezeichnung', 'Schulleitung')->missing('begriff.meine'));
        $this->assertStringNotContainsString('Primar', (string) $this->getJson('/api/v1/exports?format=json')->getContent());
        $this->assertStringNotContainsString('Primar', (string) $this->getJson("/api/v1/terms/{$term->id}")->getContent());

        // leer speichern entfernt die Anpassung
        $this->actingAs($a)->put("/begriffe/{$term->slug}/meine-darstellung", [])->assertRedirect();
        $this->assertSame(0, TermUserOverride::query()->count());
    }

    public function test_meine_darstellung_braucht_anmeldung_und_veroeffentlichten_begriff(): void
    {
        $term = $this->published(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung']);
        $this->put("/begriffe/{$term->slug}/meine-darstellung", ['name_override' => 'X'])->assertRedirect('/anmelden');

        $draft = $this->glossary->save(null, ['official_abbreviation' => 'LP', 'official_name' => 'Lehrperson'], null, true);
        $this->actingAs($this->community('c@example.org'))
            ->put("/begriffe/{$draft->slug}/meine-darstellung", ['name_override' => 'X'])->assertNotFound();
    }

    public function test_zusammenfuehren_uebernimmt_persoenliche_darstellung_ohne_ueberschreiben(): void
    {
        $this->actingAs($this->admin());
        $keep = $this->published(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung']);
        $other = $this->published(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung', 'url' => 'https://example.org']);
        $a = $this->community('a@example.org');
        $b = $this->community('b@example.org');
        foreach ([[$keep, $a, 'A Ziel'], [$other, $a, 'A Quelle'], [$other, $b, 'B Quelle']] as [$t, $u, $name]) {
            $o = new TermUserOverride(['name_override' => $name]);
            $o->term_id = $t->id;
            $o->user_id = $u->id;
            $o->save();
        }

        $this->glossary->merge($keep, [$other], [], null, true);

        $this->assertSame('A Ziel', TermUserOverride::query()->where('user_id', $a->id)->where('term_id', $keep->id)->value('name_override'));
        $this->assertSame('B Quelle', TermUserOverride::query()->where('user_id', $b->id)->where('term_id', $keep->id)->value('name_override'));
        // Widerspruch bleibt am alten Begriff
        $this->assertSame(1, TermUserOverride::query()->where('term_id', $other->id)->count());
        $this->assertSame(1, $this->glossary->lastMergeConflicts);
    }

    // ---------- Excel und Altwerte (C) ----------

    public function test_altes_intranet_format_wird_gemeldet_und_gesichert(): void
    {
        $this->actingAs($this->admin());
        $header = [...ExcelFormat::header(), 'Abkürzung eigene', 'Bezeichnung eigene', 'Alias eigene', 'Intern genutzt in'];
        $row = ['ID' => '', 'Abkürzung offiziell' => 'SL', 'Bezeichnung offiziell' => 'Schulleitung', 'Status' => 'aktuell', 'Aktiv' => 'ja',
            'Abkürzung eigene' => 'SLe', 'Bezeichnung eigene' => 'Schulleitung eigen', 'Alias eigene' => 'S-L', 'Intern genutzt in' => 'personal; kalender'];
        $file = UploadedFile::fake()->createWithContent('intranet.xlsx', SpreadsheetWriter::xlsx($header, [array_map(fn (string $c): string => $row[$c] ?? '', $header)]));

        $preview = $this->post('/admin/import', ['datei' => $file])->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('altesFormat', true)->where('neu', 1)->where('altwerte', 5)
                ->where('plan.0.meldungen', fn ($m): bool => count($m) === 4 && str_contains((string) $m[0], 'nicht zentral übernommen')));
        $this->assertSame(0, TermLegacyValue::query()->count());

        $token = (string) $preview->viewData('page')['props']['token'];
        $this->post("/admin/import/{$token}")->assertRedirect('/admin/begriffe');

        $term = Term::query()->sole();
        $this->assertNull($term->aliases()->where('alias', 'S-L')->first());
        $this->assertEqualsCanonicalizing(
            ['own_abbreviation:SLe', 'own_name:Schulleitung eigen', 'own_alias:S-L', 'used_in:personal', 'used_in:kalender'],
            TermLegacyValue::query()->get()->map(fn (TermLegacyValue $v): string => "{$v->field}:{$v->value}")->all(),
        );
        $this->assertArrayNotHasKey('own_abbreviation', $term->revisions()->firstOrFail()->snapshot);
    }

    public function test_neue_zeile_nur_mit_eigener_bezeichnung_ist_fehler(): void
    {
        $this->actingAs($this->admin());
        $header = [...ExcelFormat::header(), 'Abkürzung eigene'];
        $row = ['Abkürzung eigene' => 'SLe', 'Status' => 'aktuell'];
        $file = UploadedFile::fake()->createWithContent('intranet.xlsx', SpreadsheetWriter::xlsx($header, [array_map(fn (string $c): string => $row[$c] ?? '', $header)]));

        $this->post('/admin/import', ['datei' => $file])->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('fehler', 1)->where('altwerte', 0));
    }

    public function test_excel_v2_roundtrip_alternative_schreibweisen(): void
    {
        $this->actingAs($this->admin());
        $term = $this->glossary->save(null, ['official_abbreviation' => 'KLP', 'official_name' => 'Klassenlehrperson', 'alternative_aliases' => ['KL', 'Klassen-LP']], null, true);
        $row = ExcelFormat::row($term->refresh());
        $this->assertSame('KL; Klassen-LP', $row['Alternative Schreibweisen']);
        $this->assertArrayNotHasKey('Abkürzung eigene', $row);
        $this->assertArrayNotHasKey('Intern genutzt in', $row);
        $this->assertSame(['KL', 'Klassen-LP'], ExcelFormat::data($row)['alternative_aliases']);
    }

    public function test_altwerte_zuordnen(): void
    {
        $this->actingAs($this->admin());
        $term = $this->glossary->save(null, ['official_name' => 'Schulleitung'], null, true);
        TermLegacyValue::remember($term->id, 'own_abbreviation', 'SL', 'migration');
        TermLegacyValue::remember($term->id, 'own_alias', 'S-L', 'migration');
        TermLegacyValue::remember($term->id, 'own_name', 'Schulleitung eigen', 'migration');
        TermLegacyValue::remember($term->id, 'used_in', 'personal', 'migration');
        $id = fn (string $field): int => (int) TermLegacyValue::query()->where('field', $field)->value('id');

        $this->get('/admin/altwerte')->assertOk()->assertInertia(fn (Assert $p) => $p->component('admin/legacy-values')->where('offen', 4));

        $this->post('/admin/altwerte', ['ids' => [$id('own_abbreviation')], 'zuordnung' => 'official'])->assertRedirect();
        $this->assertSame('SL', $term->refresh()->official_abbreviation);

        $this->post('/admin/altwerte', ['ids' => [$id('own_alias')], 'zuordnung' => 'alternative'])->assertRedirect();
        $this->assertSame(['S-L'], $term->aliases()->where('alias_type', 'alternative')->pluck('alias')->all());

        // offizielle Bezeichnung schon anders gesetzt → nicht überschrieben
        $this->post('/admin/altwerte', ['ids' => [$id('own_name')], 'zuordnung' => 'official'])->assertRedirect();
        $this->assertSame('Schulleitung', $term->refresh()->official_name);
        $this->assertNull(TermLegacyValue::query()->whereKey($id('own_name'))->value('resolution'));

        // «Intern genutzt in» nur verwerfen
        $this->post('/admin/altwerte', ['ids' => [$id('used_in')], 'zuordnung' => 'search'])->assertRedirect();
        $this->assertNull(TermLegacyValue::query()->whereKey($id('used_in'))->value('resolution'));
        $this->post('/admin/altwerte', ['ids' => [$id('used_in')], 'zuordnung' => 'discarded'])->assertRedirect();
        $this->assertSame('discarded', TermLegacyValue::query()->whereKey($id('used_in'))->value('resolution'));
        $this->assertStringContainsString('personal', $this->get('/admin/altwerte/intern-genutzt-in.csv')->assertOk()->streamedContent());

        $revision = $term->revisions()->get()->last();
        $this->assertInstanceOf(TermRevision::class, $revision);
        $this->assertSame(['via' => 'altwerte'], $revision->context);
    }

    public function test_altwerte_nur_mit_recht(): void
    {
        $this->actingAs($this->community('x@example.org'))->get('/admin/altwerte')->assertForbidden();
        $this->post('/admin/altwerte', ['ids' => [1], 'zuordnung' => 'discarded'])->assertForbidden();
    }

    public function test_bestand_befehl(): void
    {
        $term = $this->glossary->save(null, ['official_name' => 'Schulleitung'], null, true);
        TermLegacyValue::remember($term->id, 'own_abbreviation', 'SL', 'migration');
        $csv = storage_path('framework/testing/bestand-'.Str::random(6).'.csv');
        @mkdir(dirname($csv), 0777, true);

        $this->artisan('glossary:bestand-eigene', ['--csv' => $csv])->assertSuccessful()
            ->expectsOutputToContain('Begriffe ohne gemeinsame Abkürzung, aber mit offener eigener Abkürzung: 1');
        $this->assertStringContainsString(';SL;migration;', (string) file_get_contents($csv));
        @unlink($csv);
    }

    // ---------- API und Anzeige (D) ----------

    public function test_berechnete_werte_und_keine_own_felder(): void
    {
        $this->published(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung']);
        $this->published(['official_abbreviation' => 'SL', 'official_name' => 'schulleitung ']);   // gleiche Bedeutung
        $this->published(['official_abbreviation' => 'SL', 'official_name' => 'Schullaufbahn']);
        $full = $this->published(['official_name' => 'Elternrat']);

        $terms = collect($this->getJson('/api/v1/terms')->assertOk()->json('data'));
        $sl = $terms->firstWhere('name', 'Schulleitung');
        $this->assertTrue($sl['is_ambiguous']);
        $this->assertSame(2, $sl['meaning_count']);
        $this->assertSame('SL', $sl['display_label']);
        $this->assertArrayNotHasKey('own_abbreviation', $sl);
        $this->assertArrayNotHasKey('own_name', $sl);

        $elternrat = $this->getJson("/api/v1/terms/{$full->id}")->assertOk()->json('data');
        $this->assertTrue($elternrat['is_full_term']);
        $this->assertSame('Elternrat', $elternrat['display_label']);
        $this->assertFalse($elternrat['is_ambiguous']);
        $this->assertSame(1, $elternrat['meaning_count']);

        $change = $this->getJson('/api/v1/changes')->json('data.0.term');
        $this->assertArrayNotHasKey('meaning_count', $change);
        $this->assertArrayHasKey('display_label', $change);
    }

    public function test_alte_abbilder_mit_eigenen_feldern_werden_nicht_ausgegeben(): void
    {
        $term = $this->published(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung']);
        // Abbild im alten Format (vor DEC-020) direkt setzen
        $snapshot = $term->publishedRevision()->firstOrFail()->snapshot;
        DB::table('term_revisions')->where('id', $term->published_revision_id)->update(['snapshot' => json_encode(
            $snapshot + ['own_abbreviation' => 'ALT-EIGEN', 'own_name' => 'Alt eigen', 'own_alias' => 'ALT-ALIAS', 'used_in' => ['ALT-INTERN']],
        )]);

        foreach (['/api/v1/terms', "/api/v1/terms/{$term->id}", '/api/v1/changes', '/api/v1/exports?format=json'] as $path) {
            $content = (string) $this->getJson($path)->assertOk()->getContent();
            foreach (['ALT-EIGEN', 'Alt eigen', 'ALT-ALIAS', 'ALT-INTERN'] as $old) {
                $this->assertStringNotContainsString($old, $content, "{$old} in {$path}");
            }
        }
        $this->get("/begriffe/{$term->slug}")->assertInertia(fn (Assert $p) => $p->where('begriff.abkuerzung', 'SL'));
    }

    // ---------- Migration (A) ----------

    public function test_migration_sichert_altwerte_und_ist_umkehrbar(): void
    {
        $term = $this->glossary->save(null, ['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung'], null, true);
        $migration = require database_path('migrations/2026_11_07_001200_shared_and_personal_data.php');

        $migration->down();
        DB::table('terms')->where('id', $term->id)->update([
            'own_abbreviation' => 'SLe', 'own_name' => 'Schulleitung eigen', 'used_in' => json_encode(['personal', 'kalender']),
        ]);
        DB::table('term_aliases')->insert(['term_id' => $term->id, 'alias' => 'S-L', 'alias_type' => 'own', 'language' => 'de']);
        DB::table('term_aliases')->insert(['term_id' => $term->id, 'alias' => 'S.L.', 'alias_type' => 'own', 'language' => 'de']);

        $migration->up();

        $values = TermLegacyValue::query()->orderBy('id')->get()->map(fn (TermLegacyValue $v): string => "{$v->field}:{$v->value}")->all();
        $this->assertEqualsCanonicalizing([
            'own_abbreviation:SLe', 'own_name:Schulleitung eigen', 'used_in:personal', 'used_in:kalender', 'own_alias:S-L', 'own_alias:S.L.',
        ], $values);
        $this->assertSame(0, DB::table('term_aliases')->where('alias_type', 'own')->count());
        $this->assertFalse(Schema::hasColumn('terms', 'own_abbreviation'));

        $migration->down();
        $row = DB::table('terms')->where('id', $term->id)->first();
        $this->assertSame('SLe', $row->own_abbreviation);
        $this->assertSame(['personal', 'kalender'], json_decode((string) $row->used_in, true));
        $this->assertSame(2, DB::table('term_aliases')->where('alias_type', 'own')->count());
        $migration->up();
    }
}
