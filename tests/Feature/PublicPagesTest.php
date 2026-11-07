<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Öffentlich lesbar ohne Anmeldung (PUB-01), Versionshistorie (#6). */
class PublicPagesTest extends TestCase
{
    public function test_startseite_ohne_anmeldung(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('home')
                ->where('auth.user', null)
                ->has('appVersion.version'));
    }

    public function test_versionen_aus_dem_changelog(): void
    {
        $this->get('/versionen')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('versions')->has('releases'));
    }

    public function test_pruefadresse(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_schutz_kopfzeilen(): void
    {
        $this->get('/')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
