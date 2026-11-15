<?php

namespace Tests\Feature;

use App\Models\Role;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Impressum je Instanz (#411): Angaben aus den Einstellungen bzw. der Umgebung. */
final class ImpressumTest extends TestCase
{
    public function test_ohne_angaben_hinweis_fuer_betreiber(): void
    {
        $this->get('/impressum')->assertOk()->assertInertia(fn (Assert $p) => $p->component('imprint')
            ->where('impressum.betreiber', ''));
    }

    public function test_angaben_aus_den_einstellungen(): void
    {
        $this->actingAs($this->userWithRoles([Role::DEVELOPER], 'dev@example.org'));
        $this->put('/admin/einstellungen', ['werte' => [
            'imprint.operator' => 'Markus Keller (MarKusSXCH)',
            'imprint.address' => 'Ohrenberg 13, 8465 Rudolfingen',
            'imprint.email' => 'info@eduksx.ch',
            'imprint.content' => 'Die Community des EduKSX-Glossary',
        ]])->assertRedirect()->assertSessionHasNoErrors();

        $this->get('/impressum')->assertInertia(fn (Assert $p) => $p
            ->where('impressum.betreiber', 'Markus Keller (MarKusSXCH)')
            ->where('impressum.email', 'info@eduksx.ch')
            ->where('impressum.datenschutz', 'info@eduksx.ch')   // leer = allgemeiner Kontakt
            ->where('impressum.register', ''));

        $this->put('/admin/einstellungen', ['werte' => ['imprint.email' => 'keine-adresse']])->assertSessionHasErrors();
    }
}
