<?php

namespace Tests\Feature;

use EduKSX\Core\Platform\Manifest;
use Tests\TestCase;

/** Plugins für Gastgeber (#338): Steckbrief mit ui, Web-Komponenten unter /ui/v1/. */
final class PluginsUiTest extends TestCase
{
    public function test_steckbrief_mit_ui_besteht_das_schema(): void
    {
        $this->assertSame([], Manifest::errors());
        $this->getJson('/.well-known/eduksx-module.json')->assertOk()
            ->assertJsonPath('ui.elements', '/ui/v1/glossary.js')
            ->assertJsonPath('ui.widgets.0.element', 'glossary-search')
            ->assertJsonPath('ui.widgets.0.audience', 'public')
            ->assertJsonPath('ui.search.url', '/api/v1/terms?q={query}');
    }

    public function test_script_vorhanden_und_suche_oeffentlich(): void
    {
        $script = (string) file_get_contents(public_path('ui/v1/glossary.js'));
        $this->assertStringContainsString('glossary-search', $script);
        $this->getJson('/api/v1/terms?q=SL&limit=8', ['Origin' => 'https://intranet.example'])
            ->assertOk()->assertHeader('Access-Control-Allow-Origin', '*');
    }
}
