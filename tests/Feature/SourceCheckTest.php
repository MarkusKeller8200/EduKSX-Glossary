<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use App\Models\Source;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Quellenprüfung (#69): Prüfdatum und Belegstatus je Herkunft, jährliche Wiedervorlage. */
class SourceCheckTest extends TestCase
{
    public function test_pruefen_und_wiedervorlage(): void
    {
        $this->actingAs($this->admin());
        $vsa = Source::query()->create(['key' => 'vsa', 'abbreviation' => 'VSA', 'name' => 'Volksschulamt', 'label' => 'VSA']);
        $term = app(GlossaryService::class)->save(null, ['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung', 'sources' => ['vsa']], null, true);

        $this->get('/admin/pruefung')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('admin/source-check')->has('begriffe', 1)
                ->where('begriffe.0.gruende', fn ($g): bool => collect($g)->contains('VSA: nie geprüft') && collect($g)->contains('offiziell ohne amtlichen Beleg')));

        $this->put("/admin/begriffe/{$term->id}/herkunft", ['herkunft' => [
            ['source_id' => $vsa->id, 'retrieved_at' => now()->toDateString(), 'evidence' => 'amtlich', 'note' => 'Website VSA'],
        ]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $term->refresh()->revision);   // keine neue Revision

        $this->get('/admin/pruefung')->assertInertia(fn (Assert $p) => $p->has('begriffe', 0));
        $this->get("/admin/begriffe/{$term->id}")
            ->assertInertia(fn (Assert $p) => $p->where('begriff.herkunft_pruefung.0.evidence', 'amtlich'));

        // älter als ein Jahr → wieder in der Liste
        $this->put("/admin/begriffe/{$term->id}/herkunft", ['herkunft' => [
            ['source_id' => $vsa->id, 'retrieved_at' => now()->subMonths(13)->toDateString(), 'evidence' => 'amtlich'],
        ]]);
        $this->get('/admin/pruefung')->assertInertia(fn (Assert $p) => $p->has('begriffe', 1));

        $this->put("/admin/begriffe/{$term->id}/herkunft", ['herkunft' => [
            ['source_id' => $vsa->id, 'evidence' => 'erfunden'],
        ]])->assertSessionHasErrors('herkunft.0.evidence');
    }
}
