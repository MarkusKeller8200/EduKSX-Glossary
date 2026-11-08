<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use App\Glossary\Permalink;
use App\Models\Term;
use Tests\TestCase;

/** Permalink je Begriff und kompakter Index (#154, #159, DEC-026). */
class PermalinkTest extends TestCase
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

    public function test_jeder_begriff_hat_einen_unveraenderlichen_code(): void
    {
        $term = $this->published(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung']);
        $this->assertMatchesRegularExpression('/^'.Permalink::PATTERN.'$/', $term->code);
        $code = $term->code;

        $term->code = 'AAAAAA';
        $term->save();
        $this->assertSame($code, $term->fresh()?->code);
        $this->assertSame($code, Permalink::normalize(strtolower($code)));
        $this->assertNull(Permalink::normalize('../../x'));
    }

    public function test_permalink_leitet_auf_den_begriff(): void
    {
        $term = $this->published(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung']);
        $this->get("/g/{$term->code}")->assertRedirect("/begriffe/{$term->slug}");
        $this->get('/g/'.strtolower($term->code))->assertRedirect("/begriffe/{$term->slug}");
        $this->get('/g/ZZZZZZ')->assertNotFound();
        $this->get('/g/abc')->assertNotFound();

        // nicht veröffentlicht → 404
        $draft = $this->glossary->save(null, ['official_abbreviation' => 'LP', 'official_name' => 'Lehrperson'], null, true);
        $this->get("/g/{$draft->code}")->assertNotFound();

        // Detailseite zeigt den Permalink
        $this->get("/begriffe/{$term->slug}")->assertInertia(fn ($p) => $p->where('begriff.permalink', url("/g/{$term->code}")));
    }

    public function test_zusammengefuehrter_begriff_leitet_weiter(): void
    {
        $keep = $this->published(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung']);
        $other = $this->published(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung Primar']);
        $this->glossary->merge($keep, [$other], [], null, true);

        $this->get("/g/{$other->code}")->assertRedirect("/begriffe/{$keep->slug}");
    }

    public function test_api_mit_code_permalink_und_index(): void
    {
        $term = $this->published(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung', 'short_description' => 'Leitet die Schule.']);

        $this->getJson('/api/v1/terms')->assertOk()
            ->assertJsonPath('data.0.code', $term->code)
            ->assertJsonPath('data.0.permalink', url("/g/{$term->code}"));
        $this->getJson("/api/v1/terms/{$term->code}")->assertOk()->assertJsonPath('data.id', $term->id);
        $this->getJson('/api/v1/terms/'.strtolower($term->code))->assertOk();

        $response = $this->getJson('/api/v1/index')->assertOk()
            ->assertJsonPath('terms.0.code', $term->code)
            ->assertJsonPath('terms.0.abbreviation', 'SL')
            ->assertJsonPath('terms.0.short_description', 'Leitet die Schule.')
            ->assertJsonMissingPath('terms.0.description');
        $etag = (string) $response->headers->get('ETag');
        $this->getJson('/api/v1/index', ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_cors_erlaubt_authorization(): void
    {
        $this->call('OPTIONS', '/api/v1/index', [], [], [], [
            'HTTP_ORIGIN' => 'https://schule.example', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization',
        ])->assertHeader('Access-Control-Allow-Origin', '*');
    }
}
