<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use App\Models\Role;
use App\Models\Term;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Redaktionstabelle (#67): Bearbeiten in der Zelle mit denselben Regeln wie das Formular. */
class TableEditTest extends TestCase
{
    private function term(): Term
    {
        return app(GlossaryService::class)->save(null, ['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung'], null, true);
    }

    public function test_kurzbeschreibung_in_der_zelle(): void
    {
        $this->actingAs($this->userWithRoles([Role::MODERATOR], 'mod@example.org'));
        $term = $this->term();

        $this->get('/admin/begriffe')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('offiziellErlaubt', false)->where('begriffe.0.kurz', ''));

        $this->patch("/admin/begriffe/{$term->id}/feld", ['feld' => 'short_description', 'wert' => 'Leitet die Schule.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $term->refresh();
        $this->assertSame('Leitet die Schule.', $term->short_description);
        $this->assertSame(2, $term->revision);
        $this->assertSame(['via' => 'tabelle'], $term->revisions()->get()->last()?->context);

        // gleicher Wert → keine Revision
        $this->patch("/admin/begriffe/{$term->id}/feld", ['feld' => 'short_description', 'wert' => 'Leitet die Schule.']);
        $this->assertSame(2, $term->refresh()->revision);
    }

    public function test_offizielle_felder_nur_mit_recht(): void
    {
        $this->actingAs($this->userWithRoles([Role::MODERATOR], 'mod@example.org'));
        $term = $this->term();

        $this->patch("/admin/begriffe/{$term->id}/feld", ['feld' => 'official_name', 'wert' => 'Anders'])
            ->assertSessionHasErrors('wert');
        $this->assertSame('Schulleitung', $term->refresh()->official_name);
        $this->patch("/admin/begriffe/{$term->id}/feld", ['feld' => 'note', 'wert' => 'x'])->assertSessionHasErrors('feld');

        $this->actingAs($this->admin());
        $this->patch("/admin/begriffe/{$term->id}/feld", ['feld' => 'official_name', 'wert' => 'Schulleitung Primar'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Schulleitung Primar', $term->refresh()->official_name);
    }
}
