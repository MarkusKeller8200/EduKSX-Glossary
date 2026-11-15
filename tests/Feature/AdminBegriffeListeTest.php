<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use App\Models\Role;
use App\Models\TermRevision;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Liste der Begriffe in der Administration (#454): «Entwurf» stimmt, ohne alle Revisionen zu laden. */
final class AdminBegriffeListeTest extends TestCase
{
    public function test_entwurf_kennzeichen(): void
    {
        $g = app(GlossaryService::class);
        $nur = $g->save(null, ['official_abbreviation' => 'NE', 'official_name' => 'Nie veröffentlicht'], null, true);
        $pub = $g->save(null, ['official_abbreviation' => 'VE', 'official_name' => 'Veröffentlicht'], null, true);
        $g->publish($pub, null);
        $ent = $g->save(null, ['official_abbreviation' => 'EN', 'official_name' => 'Mit Entwurf'], null, true);
        $g->publish($ent, null);
        $g->save($ent->refresh(), ['short_description' => 'neu'], null, true);
        // höhere Revision mit gleichem Inhalt wie veröffentlicht: kein Entwurf
        $gleich = $g->save(null, ['official_abbreviation' => 'GL', 'official_name' => 'Gleich'], null, true);
        $g->publish($gleich, null);
        $gleich->refresh();
        $neu = new TermRevision(['revision' => $gleich->revision + 1, 'change_type' => 'updated', 'snapshot' => $gleich->publishedRevision->snapshot]);
        $neu->forceFill(['term_id' => $gleich->id])->save();
        $gleich->forceFill(['revision' => $gleich->revision + 1])->saveQuietly();

        $this->actingAs($this->userWithRoles([Role::DEVELOPER], 'dev@example.org'));
        $this->get('/admin/begriffe')->assertOk()->assertInertia(function (Assert $p) {
            $flags = collect($p->toArray()['props']['begriffe'])->pluck('entwurf', 'abkuerzung')->all();
            $this->assertSame(['EN' => true, 'GL' => false, 'NE' => true, 'VE' => false], collect($flags)->sortKeys()->all());
        });
    }
}
