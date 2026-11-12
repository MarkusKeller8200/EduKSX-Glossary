<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use App\Models\Category;
use App\Models\Term;
use App\Models\TermPublicationEvent;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Doppelte zusammenführen oder als mehrdeutig belassen (#16). */
class DuplicatesTest extends TestCase
{
    private GlossaryService $glossary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin());
        $this->glossary = app(GlossaryService::class);
        Category::query()->create(['key' => 'per', 'abbreviation' => 'PER', 'name' => 'Personal', 'label' => 'Personal']);
        Category::query()->create(['key' => 'org', 'abbreviation' => 'ORG', 'name' => 'Organisation', 'label' => 'Organisation']);
    }

    /** @param  array<string, mixed>  $data */
    private function term(array $data, bool $publish = true): Term
    {
        $term = $this->glossary->save(null, $data, null, true);

        return $publish ? $this->glossary->publish($term, null) : $term;
    }

    public function test_gruppen_werden_angezeigt_ohne_automatik(): void
    {
        $this->term(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung']);
        $this->term(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung ']);
        $this->term(['official_abbreviation' => 'sl', 'official_name' => 'Schlussläuten']);
        $this->term(['official_abbreviation' => 'LP', 'official_name' => 'Lehrperson']);

        $this->get('/admin/doppelte')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('admin/duplicates')
            ->has('gruppen', 2)
            ->where('gruppen.0.art', 'name')
            ->has('gruppen.0.begriffe', 2)
            ->where('gruppen.1.art', 'abbreviation')
            ->has('gruppen.1.begriffe', 3));

        $this->assertSame(4, Term::query()->current()->count());   // nichts automatisch
    }

    public function test_mehrdeutig_belassen(): void
    {
        $this->term(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung']);
        $this->term(['official_abbreviation' => 'SL', 'official_name' => 'Schlussläuten']);

        $signatur = '';
        $this->get('/admin/doppelte')->assertInertia(function (Assert $p) use (&$signatur) {
            $p->has('gruppen', 1)->where('gruppen.0.signatur', function ($s) use (&$signatur) {
                $signatur = (string) $s;

                return true;
            });
        });
        $this->post('/admin/doppelte/belassen', ['signatur' => $signatur])->assertRedirect()->assertSessionHas('status');
        $this->get('/admin/doppelte')->assertInertia(fn (Assert $p) => $p->has('gruppen', 0)->where('belassen', 1));

        // neues Mitglied → Gruppe erscheint wieder
        $this->term(['official_abbreviation' => 'SL', 'official_name' => 'Sprachlehrperson']);
        $this->get('/admin/doppelte')->assertInertia(fn (Assert $p) => $p->has('gruppen', 1)->has('gruppen.0.begriffe', 3));

        $this->post('/admin/doppelte/zuruecksetzen')->assertRedirect();
        $this->get('/admin/doppelte')->assertInertia(fn (Assert $p) => $p->where('belassen', 0));
    }

    public function test_zusammenfuehren_vereinigt_und_leitet_weiter(): void
    {
        $alt = $this->term([
            'official_abbreviation' => 'SL', 'official_name' => 'Schulleitung', 'short_description' => 'Kurz alt.',
            'categories' => ['per'], 'official_aliases' => ['S.L.'],
        ]);
        $neu = $this->term([
            'official_abbreviation' => 'SL', 'official_name' => 'Schulleitung',
            'short_description' => 'Leitet die Schule.', 'categories' => ['org'],
        ], publish: false);

        $this->get('/admin/doppelte/zusammenfuehren?'.http_build_query(['ids' => [$alt->id, $neu->id]]))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('admin/merge')->has('begriffe', 2));

        $this->post('/admin/doppelte/zusammenfuehren', [
            'ziel' => $neu->id,
            'ids' => [$alt->id, $neu->id],
            'auswahl' => ['short_description' => $neu->id],
        ])->assertRedirect("/admin/begriffe/{$neu->id}");

        $neu->refresh();
        $alt->refresh();
        $this->assertSame($neu->id, $alt->merged_into_id);
        $this->assertNull($alt->published_revision_id);
        $this->assertSame('Leitet die Schule.', $neu->short_description);
        $this->assertEqualsCanonicalizing(['per', 'org'], $neu->categories()->pluck('key')->all());
        $this->assertContains('S.L.', $neu->aliases()->pluck('alias')->all());
        $this->assertNotNull($neu->published_revision_id, 'Ein Teil war veröffentlicht → Ergebnis veröffentlicht');
        $this->assertSame('merged', $neu->revisions()->where('revision', $neu->revision)->value('change_type'));

        // Website: alte Adresse leitet weiter
        $this->get("/begriffe/{$alt->slug}")->assertRedirect("/begriffe/{$neu->slug}")->assertStatus(301);
        $this->get("/begriffe/{$neu->slug}")->assertOk();

        // API: alte ID leitet weiter, Change-Feed meldet «merged»
        $this->get("/api/v1/terms/{$alt->id}")->assertStatus(301)->assertHeader('Location', url("/api/v1/terms/{$neu->id}"));
        $this->assertSame($neu->id, TermPublicationEvent::query()->where('action', 'merged')->value('merged_into_id'));
        $this->getJson('/api/v1/changes')->assertOk()->assertJsonFragment(['action' => 'merged', 'merged_into' => $neu->id]);

        // Redaktion: nur noch der verbleibende Begriff; der alte zeigt die Weiterleitung
        $this->get('/admin/begriffe')->assertInertia(fn (Assert $p) => $p->has('begriffe', 1));
        $this->get("/admin/begriffe/{$alt->id}")->assertInertia(fn (Assert $p) => $p
            ->where('begriff.zusammengefuehrt_in.id', $neu->id));
        $this->get('/admin/doppelte')->assertInertia(fn (Assert $p) => $p->has('gruppen', 0));
    }

    public function test_zusammenfuehren_ungueltig(): void
    {
        $a = $this->term(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung']);
        $b = $this->term(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung']);

        $this->post('/admin/doppelte/zusammenfuehren', ['ziel' => $a->id, 'ids' => [$b->id, $b->id]])
            ->assertSessionHasErrors();
        $this->post('/admin/doppelte/zusammenfuehren', [
            'ziel' => $a->id, 'ids' => [$a->id, $b->id], 'auswahl' => ['description' => '0190a0a0-0000-7000-8000-000000000000'],
        ])->assertSessionHasErrors('form');
        $this->assertSame(2, Term::query()->current()->count());
    }
}
