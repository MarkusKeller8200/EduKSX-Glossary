<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use Tests\TestCase;

/** Sitemap und Suchmaschinen (#393): indexiert wird nur mit GLOSSARY_SUCHMASCHINEN=true. */
final class SuchmaschinenTest extends TestCase
{
    private function begriff(string $abk, string $name, bool $publish = true): void
    {
        $glossary = app(GlossaryService::class);
        $term = $glossary->save(null, ['official_abbreviation' => $abk, 'official_name' => $name], null, true);
        if ($publish) {
            $glossary->publish($term, null);
        }
    }

    public function test_ohne_schalter_noindex_und_alles_gesperrt(): void
    {
        config(['glossary.search_engines' => false]);

        $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $robots = (string) $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8')->getContent();
        $this->assertStringContainsString("Disallow: /\n", $robots);
        $this->assertStringNotContainsString('Sitemap:', $robots);
        $this->get('/sitemap.xml')->assertNotFound();
    }

    public function test_mit_schalter_robots_und_sitemap(): void
    {
        config(['glossary.search_engines' => true, 'app.url' => 'https://glossary.example']);
        $this->begriff('SL', 'Schulleitung');
        $this->begriff('SHP', 'Schulische Heilpädagogik');
        $this->begriff('ENT', 'Entwurf', false);

        $this->get('/')->assertHeaderMissing('X-Robots-Tag');

        $robots = (string) $this->get('/robots.txt')->assertOk()->getContent();
        $this->assertStringContainsString('Sitemap: https://glossary.example/sitemap.xml', $robots);
        $this->assertStringContainsString('Disallow: /admin', $robots);
        $this->assertStringNotContainsString("Disallow: /\n", $robots);

        $xml = (string) $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=utf-8')->getContent();
        $doc = simplexml_load_string($xml);
        $this->assertNotFalse($doc);
        $locs = [];
        foreach ($doc->url as $url) {
            $locs[] = (string) $url->loc;
        }
        $this->assertContains('https://glossary.example/', $locs);
        $this->assertContains('https://glossary.example/hilfe', $locs);
        $terms = array_values(array_filter($locs, fn (string $l): bool => str_contains($l, '/begriffe/')));
        $this->assertCount(2, $terms);   // Entwurf nicht veröffentlicht → nicht in der Sitemap
        foreach ($terms as $loc) {
            $this->get(substr($loc, strlen('https://glossary.example')))->assertOk();
        }
        $this->assertNotEmpty((string) $doc->url[count($locs) - 1]->lastmod);
    }
}
