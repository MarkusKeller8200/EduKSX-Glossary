<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use App\Models\PublicationRelease;
use App\Models\Term;
use Tests\TestCase;

/** Knopf «Ausgewählte veröffentlichen» (#23). */
class PublishSelectedTest extends TestCase
{
    private function term(string $abk): Term
    {
        return app(GlossaryService::class)->save(null, ['official_abbreviation' => $abk, 'official_name' => "Begriff {$abk}"], null, true);
    }

    public function test_ausgewaehlte_veroeffentlichen_mit_einem_datenstand(): void
    {
        $a = $this->term('AA');
        $b = $this->term('BB');
        $this->term('CC');

        $this->actingAs($this->admin())
            ->post('/admin/begriffe/veroeffentlichen', ['ids' => [$a->id, $b->id]])
            ->assertRedirect()
            ->assertSessionHas('status', '2 Begriffe veröffentlicht.');

        $this->assertSame(2, Term::query()->published()->count());
        $this->assertSame(1, PublicationRelease::query()->count());
        $this->assertSame(2, PublicationRelease::query()->firstOrFail()->term_count);
    }

    public function test_unveraenderte_werden_uebersprungen(): void
    {
        $a = $this->term('AA');
        $this->actingAs($this->admin());
        $this->post('/admin/begriffe/veroeffentlichen', ['ids' => [$a->id]]);

        $this->post('/admin/begriffe/veroeffentlichen', ['ids' => [$a->id]])
            ->assertSessionHas('status', '0 Begriffe veröffentlicht, 1 waren schon auf dem veröffentlichten Stand.');
        $this->assertSame(1, PublicationRelease::query()->count());
    }

    public function test_nur_mit_anmeldung(): void
    {
        $a = $this->term('AA');

        $this->post('/admin/begriffe/veroeffentlichen', ['ids' => [$a->id]])->assertRedirect('/anmelden');
        $this->assertSame(0, Term::query()->published()->count());
    }
}
