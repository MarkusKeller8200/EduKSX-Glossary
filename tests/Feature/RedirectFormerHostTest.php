<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Frühere Adresse leitet dauerhaft weiter (#304). */
class RedirectFormerHostTest extends TestCase
{
    public function test_fruehere_adresse_leitet_weiter(): void
    {
        config(['glossary.redirect_host' => 'glossary.markussx.ch', 'app.url' => 'https://glossary-dev.markussx.ch']);

        $this->get('https://glossary.markussx.ch/begriffe/sl?x=1')->assertStatus(301)
            ->assertHeader('Location', 'https://glossary-dev.markussx.ch/begriffe/sl?x=1');
        $this->get('https://glossary-dev.markussx.ch/up')->assertOk();
    }

    public function test_ohne_einstellung_keine_weiterleitung(): void
    {
        config(['glossary.redirect_host' => '']);
        $this->get('https://glossary.markussx.ch/up')->assertOk();
    }
}
