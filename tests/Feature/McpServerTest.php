<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use App\Mcp\GlossaryServer;
use App\Mcp\Prompts\ExplainTextPrompt;
use App\Mcp\Resources\TermResource;
use App\Mcp\Tools\ExplainText;
use App\Mcp\Tools\GetTerm;
use App\Mcp\Tools\ListCategories;
use App\Mcp\Tools\ResolveAbbreviation;
use App\Mcp\Tools\SearchTerms;
use App\Models\Category;
use App\Models\Term;
use App\Support\Settings;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** MCP-Server Phase 1 (#261, DEC-029): öffentlich lesen, gleiche Daten wie die API v1. */
final class McpServerTest extends TestCase
{
    private function begriff(string $abk, string $name, array $extra = [], bool $publish = true): Term
    {
        $glossary = app(GlossaryService::class);
        $term = $glossary->save(null, ['official_abbreviation' => $abk, 'official_name' => $name, 'short_description' => "Kurz {$name}"] + $extra, null, true);

        return $publish ? $glossary->publish($term, null) : $term;
    }

    /** über die Vorgabe, damit eine gesetzte Umgebungsvariable den Test nicht beeinflusst */
    private function einschalten(): void
    {
        config(['glossary.mcp_enabled' => true]);
        Settings::flush();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Category::query()->create(['key' => 'schule', 'label' => 'Schule', 'name' => 'Schule', 'sort_order' => 1]);
        $this->begriff('SL', 'Schulleitung', ['categories' => ['schule'], 'alternative_aliases' => ['Schulleiterin']]);
        $this->begriff('KLP', 'Klassenlehrperson', ['research_basis' => 'ai_assisted']);
        $this->begriff('LP', 'Lehrperson');
        $this->begriff('LP', 'Lehrplan');
        $this->begriff('GEHEIM', 'Entwurf, nicht veröffentlicht', [], false);
    }

    public function test_werkzeuge_lesend_und_benannt_nach_standard(): void
    {
        $this->einschalten();
        $tools = collect($this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], ['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-11-25'])
            ->assertOk()->json('result.tools'));
        $names = $tools->pluck('name')->sort()->values()->all();
        $this->assertSame(['glossary_explain_text', 'glossary_get_term', 'glossary_list_categories', 'glossary_resolve_abbreviation', 'glossary_search_terms'], $names);
        foreach ($tools as $tool) {
            $this->assertTrue($tool['annotations']['readOnlyHint'] ?? false, $tool['name'].' nicht als lesend markiert');
        }
    }

    public function test_suchen_nur_veroeffentlichte_und_mit_filter(): void
    {
        GlossaryServer::tool(SearchTerms::class, ['query' => 'schul'])->assertOk()
            ->assertSee('SL – Schulleitung')->assertDontSee('GEHEIM')
            ->assertStructuredContentPath('terms.0.abbreviation', 'SL')
            ->assertStructuredContentPath('license.name', 'CC BY 4.0');
        GlossaryServer::tool(SearchTerms::class, ['query' => 'geheim'])->assertSee('Kein Begriff gefunden.');
        GlossaryServer::tool(SearchTerms::class, ['category' => 'Schule'])->assertStructuredContentCount(1, 'terms');
    }

    public function test_abkuerzung_aufloesen_mehrdeutig_und_ki_hinweis(): void
    {
        GlossaryServer::tool(ResolveAbbreviation::class, ['abbreviation' => 'lp'])->assertOk()
            ->assertStructuredContentPath('ambiguous', true)->assertStructuredContentCount(2, 'terms');
        GlossaryServer::tool(ResolveAbbreviation::class, ['abbreviation' => 'Schulleiterin'])->assertStructuredContentPath('terms.0.abbreviation', 'SL');
        GlossaryServer::tool(ResolveAbbreviation::class, ['abbreviation' => 'KLP'])->assertSee('KI-unterstützt, noch nicht geprüft');
        GlossaryServer::tool(ResolveAbbreviation::class, ['abbreviation' => 'GEHEIM'])->assertSee('nicht erfasst');
    }

    public function test_begriff_nach_code_permalink_und_slug(): void
    {
        $sl = Term::query()->where('official_abbreviation', 'SL')->firstOrFail();
        GlossaryServer::tool(GetTerm::class, ['reference' => $sl->code])->assertOk()->assertStructuredContentPath('term.code', $sl->code);
        GlossaryServer::tool(GetTerm::class, ['reference' => url('/g/'.$sl->code)])->assertStructuredContentPath('term.name', 'Schulleitung');
        GlossaryServer::tool(GetTerm::class, ['reference' => $sl->slug])->assertSee('Schulleitung');
        $draft = Term::query()->where('official_abbreviation', 'GEHEIM')->firstOrFail();
        GlossaryServer::tool(GetTerm::class, ['reference' => $draft->code])->assertHasErrors();
    }

    public function test_text_erklaeren_ohne_speichern(): void
    {
        Log::spy();
        $brief = 'Liebe Eltern, die SL und die KLP laden ein. Die SSA ist auch dabei. Kontakt: lp@schule.ch';
        GlossaryServer::tool(ExplainText::class, ['text' => $brief])->assertOk()
            ->assertSee('«SL» (1×): SL – Schulleitung')
            ->assertSee('Nicht im Glossary: SSA')
            ->assertDontSee('Lehrperson');   // «lp» klein ist keine Abkürzung
        GlossaryServer::tool(ExplainText::class, ['text' => 'Die Schulleitung informiert.', 'names' => true])->assertSee('Schulleitung');
        Log::shouldNotHaveReceived('info');
    }

    public function test_kategorien_ressource_und_prompt(): void
    {
        GlossaryServer::tool(ListCategories::class)->assertOk()->assertSee('Schule (`schule`)');
        $sl = Term::query()->where('official_abbreviation', 'SL')->firstOrFail();
        GlossaryServer::resource(TermResource::class, ['code' => $sl->code])->assertOk()->assertSee('Schulleitung');
        GlossaryServer::prompt(ExplainTextPrompt::class, ['text' => 'Die SL informiert.'])->assertOk()->assertSee('glossary_explain_text');
    }

    public function test_http_nur_eingeschaltet_und_mit_begrenzer(): void
    {
        $init = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-11-25', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'test', 'version' => '1']]];
        $this->postJson('/mcp', $init, ['Accept' => 'application/json, text/event-stream'])->assertNotFound();

        $this->einschalten();
        $response = $this->postJson('/mcp', $init, ['Accept' => 'application/json, text/event-stream'])->assertOk();
        $this->assertStringContainsString('EduKSX-Glossary', (string) $response->getContent());
        $this->assertNull($response->headers->get('Set-Cookie'));

        $list = ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'];
        $headers = ['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-11-25'];
        $this->postJson('/mcp', $list, $headers)->assertOk()->assertSee('glossary_search_terms');
        for ($i = 0; $i < 60; $i++) {
            $last = $this->postJson('/mcp', $list, $headers);
        }
        $last->assertStatus(429);
    }
}
