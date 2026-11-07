<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Term;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Redaktion (#14): anlegen, ändern, Verlauf, veröffentlichen, zurückziehen, Listen. */
class RedaktionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin());
        Category::query()->create(['key' => 'per', 'abbreviation' => 'PER', 'name' => 'Personal', 'label' => 'Personal']);
    }

    /** @return array<string, mixed> */
    private function form(array $extra = []): array
    {
        return [
            'official_abbreviation' => 'SL', 'official_name' => 'Schulleitung', 'official_aliases' => 'S.L.; SL.',
            'short_description' => 'Leitet die Schule.', 'categories' => ['per'], 'sources' => [],
            'lifecycle_status' => 'aktuell', 'is_active' => true, ...$extra,
        ];
    }

    public function test_anlegen_aendern_verlauf(): void
    {
        $this->get('/admin/begriffe/neu')->assertOk()->assertInertia(fn (Assert $p) => $p->component('admin/term-form'));

        $this->post('/admin/begriffe', $this->form())->assertRedirect();
        $term = Term::query()->firstOrFail();
        $this->assertSame(1, $term->revision);
        $this->assertSame(['S.L.', 'SL.'], $term->aliases()->where('alias_type', 'official')->pluck('alias')->all());

        $this->put("/admin/begriffe/{$term->id}", $this->form(['short_description' => 'Führt die Schule.']))
            ->assertRedirect("/admin/begriffe/{$term->id}")
            ->assertSessionHas('status');
        $this->assertSame(2, $term->refresh()->revision);

        $this->get("/admin/begriffe/{$term->id}")->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('admin/term-show')
            ->has('verlauf', 2)
            ->where('verlauf.0.felder', ['Kurzbeschreibung'])
            ->where('begriff.ungespeichert', true));
    }

    public function test_ungueltige_eingabe(): void
    {
        $this->post('/admin/begriffe', $this->form(['official_abbreviation' => '', 'official_name' => '']))
            ->assertSessionHasErrors('form');
        $this->post('/admin/begriffe', $this->form(['url' => 'ftp://x']))->assertSessionHasErrors('form');
        $this->assertSame(0, Term::query()->count());
    }

    public function test_veroeffentlichen_und_zurueckziehen(): void
    {
        $this->post('/admin/begriffe', $this->form());
        $term = Term::query()->firstOrFail();

        $this->post("/admin/begriffe/{$term->id}/veroeffentlichen")->assertRedirect();
        $this->get('/begriffe/schulleitung')->assertOk();

        $this->post("/admin/begriffe/{$term->id}/zurueckziehen")->assertRedirect();
        $this->get('/begriffe/schulleitung')->assertNotFound();
    }

    public function test_listen_pflegen(): void
    {
        $this->get('/admin/listen')->assertOk()->assertInertia(fn (Assert $p) => $p->component('admin/lists')->has('listen.kategorien', 1));

        $this->post('/admin/listen/herkunft', ['name' => 'Volksschulamt', 'abbreviation' => 'VSA', 'url' => 'https://www.zh.ch'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sources', ['key' => 'vsa', 'name' => 'Volksschulamt']);

        $category = Category::query()->firstOrFail();
        $this->put("/admin/listen/kategorien/{$category->id}", ['name' => 'Personal & HR', 'key' => 'anders'])->assertSessionHasNoErrors();
        $this->assertSame('per', $category->refresh()->key);   // Schlüssel bleibt
        $this->assertSame('Personal & HR', $category->name);
    }

    public function test_verwendeter_listeneintrag_wird_nicht_geloescht(): void
    {
        $this->post('/admin/begriffe', $this->form());
        $category = Category::query()->firstOrFail();

        $this->delete("/admin/listen/kategorien/{$category->id}")->assertSessionHas('status', 'Wird noch von Begriffen verwendet – nicht gelöscht.');
        $this->assertNotNull($category->fresh());
    }
}
