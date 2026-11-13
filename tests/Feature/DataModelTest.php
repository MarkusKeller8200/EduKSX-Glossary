<?php

namespace Tests\Feature;

use App\Glossary\ExcelFormat;
use App\Glossary\GlossaryService;
use App\Glossary\Journal;
use App\Models\Role;
use App\Models\Term;
use App\Models\TermRevision;
use App\Models\TermUserOverride;
use App\Models\User;
use App\Organizations\OrganizationService;
use App\Support\SpreadsheetWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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

    /** Konto mit der Rolle Benutzer (Rolle neuer Konten, #110). */
    private function community(string $email): User
    {
        return $this->userWithRoles([Role::USER], $email, twoFactor: false);
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
        $this->assertNull($revision->approved_by_id);
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
        // seit #148 nur als Mitglied einer Organisation; eigene Texte der Organisations-Admins gelten sofort
        app(OrganizationService::class)->create($a, 'Schule A');

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
            ->assertInertia(fn (Assert $p) => $p->where('begriff.bezeichnung', 'Schulleitung')->missing('begriff.persoenlich')
                ->where('begriff.meine_moeglich', false));

        $this->app['auth']->forgetGuards();
        $this->get("/begriffe/{$term->slug}")
            ->assertInertia(fn (Assert $p) => $p->where('begriff.bezeichnung', 'Schulleitung')->missing('begriff.meine'));
        $this->assertStringNotContainsString('Primar', (string) $this->getJson('/api/v1/exports?format=json')->getContent());
        $this->assertStringNotContainsString('Primar', (string) $this->getJson("/api/v1/terms/{$term->id}")->getContent());

        // leer speichern entfernt die Anpassung
        $this->actingAs($a)->put("/begriffe/{$term->slug}/meine-darstellung", [])->assertRedirect();
        $this->assertSame(0, TermUserOverride::query()->count());
    }

    public function test_gast_ohne_persoenliche_darstellung(): void
    {
        $term = $this->published(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung']);
        $gast = $this->userWithRoles([Role::GUEST], 'g@example.org', twoFactor: false);
        // frühere Anpassung (z. B. vor dem Wechsel zu Gast) bleibt gespeichert, gilt aber nicht
        TermUserOverride::query()->forceCreate(['term_id' => $term->id, 'user_id' => $gast->id, 'name_override' => 'Schulleitung Primar']);

        $this->actingAs($gast)->put("/begriffe/{$term->slug}/meine-darstellung", ['name_override' => 'X'])->assertForbidden();
        $this->actingAs($gast)->delete("/begriffe/{$term->slug}/meine-darstellung")->assertForbidden();
        $this->actingAs($gast)->get("/begriffe/{$term->slug}")
            ->assertInertia(fn (Assert $p) => $p->where('begriff.bezeichnung', 'Schulleitung')->missing('begriff.meine'));
        $this->actingAs($gast)->get('/')->assertInertia(fn (Assert $p) => $p->where('begriffe.0.bezeichnung', 'Schulleitung'));
        $this->assertSame(1, TermUserOverride::query()->where('user_id', $gast->id)->count());
    }

    public function test_meine_darstellung_braucht_anmeldung_und_veroeffentlichten_begriff(): void
    {
        $term = $this->published(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung']);
        $this->put("/begriffe/{$term->slug}/meine-darstellung", ['name_override' => 'X'])->assertRedirect('/anmelden');

        // ohne Organisation abgewiesen (#148)
        $this->actingAs($this->community('d@example.org'))
            ->put("/begriffe/{$term->slug}/meine-darstellung", ['name_override' => 'X'])->assertSessionHasErrors('organisation');
        $this->assertSame(0, TermUserOverride::query()->count());

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

    // ---------- Excel (C) ----------

    public function test_altes_intranet_format_wird_abgelehnt(): void
    {
        // Altwerte werden seit 0.12.0 nicht mehr unterstützt (DEC-030): Datei mit Spalten des alten Formats ablehnen
        $this->actingAs($this->admin());
        $header = [...ExcelFormat::header(), 'Abkürzung eigene', 'Intern genutzt in'];
        $row = ['Abkürzung offiziell' => 'SL', 'Bezeichnung offiziell' => 'Schulleitung', 'Abkürzung eigene' => 'SLe'];
        $file = UploadedFile::fake()->createWithContent('intranet.xlsx', SpreadsheetWriter::xlsx($header, [array_map(fn (string $c): string => $row[$c] ?? '', $header)]));

        $this->post('/admin/import', ['datei' => $file])->assertSessionHasErrors('datei');
        $this->assertSame(0, Term::query()->count());
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

}
