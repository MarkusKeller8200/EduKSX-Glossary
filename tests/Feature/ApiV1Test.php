<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use App\Models\Category;
use App\Models\Source;
use App\Models\Term;
use Tests\TestCase;

/** API v1: Begriffe, Listen, Datenstand, Change-Feed, Export, ETag (#25). */
class ApiV1Test extends TestCase
{
    private GlossaryService $glossary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->glossary = app(GlossaryService::class);
        Category::query()->create(['key' => 'per', 'abbreviation' => 'PER', 'name' => 'Personal', 'label' => 'Personal']);
        Source::query()->create(['key' => 'vsa', 'abbreviation' => 'VSA', 'name' => 'Volksschulamt', 'label' => 'VSA', 'url' => 'https://www.zh.ch']);
    }

    /** @param  array<string, mixed>  $data */
    private function term(array $data, bool $publish = true): Term
    {
        $term = $this->glossary->save(null, $data, null, true);

        return $publish ? $this->glossary->publish($term, null) : $term;
    }

    public function test_suchbegriffe_und_angaben_der_listen(): void
    {
        Category::query()->where('key', 'per')->update(['description' => 'Personen und Funktionen', 'color' => 'blue', 'note' => 'INTERN-NOTIZ']);
        $this->term(['official_abbreviation' => 'KLP', 'official_name' => 'Klassenlehrperson', 'search_terms' => 'Klassenlehrer; Klassenlehrerin', 'categories' => ['per']]);

        $this->getJson('/api/v1/terms')
            ->assertJsonPath('data.0.search_terms', ['Klassenlehrer', 'Klassenlehrerin'])
            ->assertJsonPath('data.0.categories.0.abbreviation', 'PER')
            ->assertJsonPath('data.0.categories.0.description', 'Personen und Funktionen')
            ->assertJsonPath('data.0.categories.0.color', 'blue');
        $this->assertNotNull($this->getJson('/api/v1/terms')->json('data.0.updated_at'));
        $list = $this->getJson('/api/v1/categories')->assertJsonPath('data.0.color', 'blue');
        $this->assertStringNotContainsString('INTERN-NOTIZ', (string) $list->getContent());
        $this->assertStringNotContainsString('INTERN-NOTIZ', (string) $this->getJson('/api/v1/exports?format=json')->getContent());
    }

    public function test_begriffe_nur_veroeffentlicht(): void
    {
        $this->term(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung', 'categories' => ['per'], 'sources' => ['vsa']]);
        $this->term(['official_abbreviation' => 'LP', 'official_name' => 'Lehrperson'], publish: false);

        $this->getJson('/api/v1/terms')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.abbreviation', 'SL')
            ->assertJsonPath('data.0.categories.0.key', 'per')
            ->assertJsonPath('data.0.sources.0.url', 'https://www.zh.ch')
            ->assertJsonMissingPath('data.0.note');
    }

    public function test_suche_filter_und_seiten(): void
    {
        foreach (['AA', 'BB', 'CC'] as $abk) {
            $this->term(['official_abbreviation' => $abk, 'official_name' => "Begriff {$abk}", 'categories' => $abk === 'BB' ? ['per'] : []]);
        }

        $this->getJson('/api/v1/terms?q=bb')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/terms?category=Personal')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/terms?per_page=2&page=2')
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('links.next', null);
        $this->getJson('/api/v1/terms?status=gibts-nicht')->assertStatus(400)->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_einzelner_begriff_nach_id_oder_slug(): void
    {
        $term = $this->term(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung']);

        $this->getJson("/api/v1/terms/{$term->id}")->assertOk()->assertJsonPath('data.slug', 'schulleitung');
        $this->getJson('/api/v1/terms/schulleitung')->assertOk()->assertJsonPath('data.id', $term->id);
        $this->getJson('/api/v1/terms/gibt-es-nicht')->assertNotFound()->assertJsonPath('status', 404);
    }

    public function test_entwurf_aenderung_bleibt_unsichtbar(): void
    {
        $term = $this->term(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung', 'short_description' => 'Alt.']);
        $this->glossary->save($term, ['short_description' => 'Neu.'], null, true);

        $this->getJson('/api/v1/terms/schulleitung')->assertJsonPath('data.short_description', 'Alt.')->assertJsonPath('data.revision', 1);
    }

    public function test_etag_und_304(): void
    {
        $this->term(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung']);
        $etag = (string) $this->get('/api/v1/terms')->assertOk()->headers->get('ETag');

        $this->get('/api/v1/terms', ['If-None-Match' => $etag])->assertStatus(304);

        $this->term(['official_abbreviation' => 'LP', 'official_name' => 'Lehrperson']);
        $this->get('/api/v1/terms', ['If-None-Match' => $etag])->assertOk();
    }

    public function test_datenstand(): void
    {
        $this->getJson('/api/v1/releases/latest')->assertJsonPath('data.release', 0);
        $this->term(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung']);

        $this->getJson('/api/v1/releases/latest')->assertJsonPath('data.release', 1)->assertJsonPath('data.term_count', 1);
    }

    public function test_change_feed_mit_rueckzug_und_cursor(): void
    {
        $sl = $this->term(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung']);
        $this->term(['official_abbreviation' => 'LP', 'official_name' => 'Lehrperson']);
        $this->glossary->unpublish($sl, null);

        $all = $this->getJson('/api/v1/changes?since=0')->assertOk();
        $all->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.action', 'published')
            ->assertJsonPath('data.0.term.abbreviation', 'SL')
            ->assertJsonPath('data.2.action', 'unpublished')
            ->assertJsonPath('data.2.term', null)
            ->assertJsonPath('meta.has_more', false);

        $cursor = (int) $all->json('data.0.cursor');
        $this->getJson("/api/v1/changes?since={$cursor}&limit=1")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonPath('data.0.term.abbreviation', 'LP');

        $next = (int) $all->json('meta.next_cursor');
        $this->getJson("/api/v1/changes?since={$next}")->assertJsonCount(0, 'data')->assertJsonPath('meta.next_cursor', $next);
    }

    public function test_viele_auf_einmal_veroeffentlicht_im_feed(): void
    {
        $a = $this->term(['official_abbreviation' => 'AA', 'official_name' => 'A'], publish: false);
        $b = $this->term(['official_abbreviation' => 'BB', 'official_name' => 'B'], publish: false);
        $this->glossary->publishMany([$a, $b], null);

        $this->getJson('/api/v1/changes')->assertJsonCount(2, 'data')->assertJsonPath('data.1.release', 1);
    }

    public function test_export_und_listen(): void
    {
        $this->term(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung']);

        $this->getJson('/api/v1/exports?format=json')
            ->assertOk()
            ->assertJsonCount(1, 'terms')
            ->assertJsonPath('categories.0.key', 'per')
            ->assertJsonPath('release', 1);
        $this->getJson('/api/v1/exports?format=csv')->assertStatus(400);
        $this->getJson('/api/v1/categories')->assertJsonPath('data.0.label', 'Personal');
        $this->getJson('/api/v1/sources')->assertJsonPath('data.0.key', 'vsa');
    }

    public function test_inaktiv_im_veroeffentlichten_stand_ist_unsichtbar(): void
    {
        $term = $this->term(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung']);
        $this->glossary->publish($this->glossary->save($term, ['is_active' => false], null, true), null);

        $this->getJson('/api/v1/terms')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/changes')->assertJsonPath('data.1.term.is_active', false);
    }
}
