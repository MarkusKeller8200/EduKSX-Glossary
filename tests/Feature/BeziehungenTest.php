<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use App\Glossary\PublicGlossary;
use App\Models\Term;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Beziehungen zwischen Begriffen (X2, #283): Teil der Revision, nur öffentliche Ziele in API und Website,
 * Gegenrichtung auf der Website, Zusammenführen.
 */
class BeziehungenTest extends TestCase
{
    private GlossaryService $glossary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->glossary = app(GlossaryService::class);
    }

    private function make(string $abk, string $name, array $extra = [], bool $publish = true): Term
    {
        $term = $this->glossary->save(null, ['official_abbreviation' => $abk, 'official_name' => $name] + $extra, null, true);

        return $publish ? $this->glossary->publish($term, null) : $term;
    }

    public function test_nachfolger_in_api_und_website(): void
    {
        $neu = $this->make('SL', 'Schulleitung');
        $alt = $this->make('RK', 'Rektorat', ['status' => 'historical', 'relations' => [['type' => 'successor', 'term_id' => $neu->id]]]);

        $this->getJson("/api/v1/terms/{$alt->slug}")->assertOk()
            ->assertJsonPath('data.relations.0.type', 'successor')
            ->assertJsonPath('data.relations.0.slug', $neu->slug)
            ->assertJsonPath('data.relations.0.display_label', 'SL');
        $this->getJson("/api/v1/terms/{$neu->slug}")->assertJsonPath('data.relations', []);

        $this->assertSame([['art' => 'Nachfolger', 'slug' => $neu->slug, 'titel' => 'SL – Schulleitung']], app(PublicGlossary::class)->find($alt->slug)['beziehungen'] ?? null);
        $this->assertSame([['art' => 'Vorgänger', 'slug' => $alt->slug, 'titel' => 'RK – Rektorat']], app(PublicGlossary::class)->find($neu->slug)['beziehungen'] ?? null);
    }

    public function test_unveroeffentlichtes_ziel_bleibt_verborgen(): void
    {
        $entwurf = $this->make('XX', 'Entwurf', [], false);
        $term = $this->make('AB', 'Begriff', ['relations' => [['type' => 'see_also', 'term_id' => $entwurf->id]]]);

        $this->getJson("/api/v1/terms/{$term->slug}")->assertJsonPath('data.relations', []);
        $this->assertSame([], app(PublicGlossary::class)->find($term->slug)['beziehungen'] ?? null);
    }

    public function test_entwurf_erst_nach_veroeffentlichung(): void
    {
        $a = $this->make('A', 'Alpha');
        $b = $this->make('B', 'Beta');
        $b = $this->glossary->save($b, ['relations' => [['type' => 'broader', 'term_id' => $a->id]]], null, true);
        $this->getJson("/api/v1/terms/{$b->slug}")->assertJsonPath('data.relations', []);

        $this->glossary->publish($b, null);
        $this->getJson("/api/v1/terms/{$b->slug}")->assertJsonPath('data.relations.0.type', 'broader');
        $this->assertSame('Unterbegriff', app(PublicGlossary::class)->find($a->slug)['beziehungen'][0]['art'] ?? null);
    }

    public function test_pruefungen(): void
    {
        $a = $this->make('A', 'Alpha');
        $b = $this->make('B', 'Beta');
        $c = $this->make('C', 'Gamma');

        foreach ([
            [['type' => 'successor', 'term_id' => $a->id]],                                          // sich selbst
            [['type' => 'cousin', 'term_id' => $b->id]],                                             // unbekannte Art
            [['type' => 'successor', 'term_id' => $b->id], ['type' => 'successor', 'term_id' => $c->id]],   // zwei Nachfolger
            [['type' => 'see_also', 'term_id' => '01a1b335-0000-7000-8000-00000000dead']],          // gibt es nicht
        ] as $relations) {
            try {
                $this->glossary->save($a, ['relations' => $relations], null, true);
                $this->fail('Ungültige Beziehung angenommen: '.json_encode($relations));
            } catch (InvalidArgumentException) {
                $this->assertSame(0, $a->termRelations()->count());
            }
        }
    }

    public function test_zusammenfuehren_uebernimmt_beziehungen_und_loest_ziele_auf(): void
    {
        $ziel = $this->make('Z', 'Ziel');
        $doppelt = $this->make('D', 'Doppelt', ['relations' => [['type' => 'see_also', 'term_id' => $ziel->id]]]);
        $verweis = $this->make('V', 'Verweis', ['relations' => [['type' => 'see_also', 'term_id' => $doppelt->id]]]);
        $andere = $this->make('O', 'Andere');
        $this->glossary->save($doppelt, ['relations' => [['type' => 'see_also', 'term_id' => $andere->id]]], null, true);

        $merged = $this->glossary->merge($ziel, [$doppelt->fresh()], [], null, true);

        $this->assertSame([['type' => 'see_also', 'term_id' => $andere->id]], $merged->latestRevision?->snapshot['relations']);
        // Verweis auf den zusammengeführten Begriff zeigt öffentlich auf den verbleibenden
        $this->getJson("/api/v1/terms/{$verweis->slug}")->assertJsonPath('data.relations.0.id', $ziel->id);
    }

    public function test_redaktion_mit_journal(): void
    {
        $this->actingAs($this->admin());
        $neu = $this->make('SL', 'Schulleitung');
        $alt = $this->make('RK', 'Rektorat');

        $this->get("/admin/begriffe/{$alt->id}/bearbeiten")->assertInertia(fn (Assert $p) => $p
            ->where('werte.relations', [])
            ->where('listen.beziehungsarten.successor', 'Nachfolger'));

        $this->put("/admin/begriffe/{$alt->id}", [
            'official_abbreviation' => 'RK', 'official_name' => 'Rektorat', 'status' => 'historical', 'is_active' => true,
            'relations' => [['type' => 'successor', 'term_id' => $neu->id]],
        ])->assertRedirect("/admin/begriffe/{$alt->id}");

        $this->get("/admin/begriffe/{$alt->id}")->assertInertia(fn (Assert $p) => $p
            ->where('begriff.relations.0.term_id', $neu->id)
            ->where('verlauf.0.aenderungen', fn ($rows): bool => collect($rows)->contains(fn ($r): bool => $r['feld'] === 'Beziehungen' && $r['neu'] === 'Nachfolger: SL – Schulleitung')));
    }
}
