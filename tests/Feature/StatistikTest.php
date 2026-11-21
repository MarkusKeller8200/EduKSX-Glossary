<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use App\Models\Role;
use App\Models\Term;
use App\Statistics\Usage;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Statistik ohne Cookies (#495): Tageszähler je Kanal, ohne Personenbezug. */
final class StatistikTest extends TestCase
{
    private const BROWSER = ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/140.0'];

    private function begriff(): Term
    {
        $g = app(GlossaryService::class);

        return $g->publish($g->save(null, ['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung'], null, true), null);
    }

    private function zaehler(string $channel, string $metric, string $subject = ''): int
    {
        return (int) DB::table('usage_counts')->where(compact('channel', 'metric', 'subject'))->sum('count');
    }

    public function test_website_und_erweiterung_getrennt(): void
    {
        $term = $this->begriff();
        $this->get("/begriffe/{$term->slug}", self::BROWSER)->assertOk();
        $this->get("/begriffe/{$term->slug}", self::BROWSER)->assertOk();
        // «Im Glossary öffnen» aus der Erweiterung: Permalink mit ?via=erweiterung
        $this->get("/g/{$term->code}?via=erweiterung", self::BROWSER)->assertRedirect("/begriffe/{$term->slug}?via=erweiterung");
        $this->get("/begriffe/{$term->slug}?via=erweiterung", self::BROWSER)->assertOk();
        $this->get("/begriffe/{$term->slug}?via=einbindung", self::BROWSER)->assertOk();

        $this->assertSame(2, $this->zaehler('web', 'term_view', $term->code));
        $this->assertSame(1, $this->zaehler('extension', 'term_open', $term->code));
        $this->assertSame(1, $this->zaehler('embed', 'term_open', $term->code));
        $this->assertSame(1, DB::table('usage_counts')->where('channel', 'web')->count());   // eine Zeile je Tag, nur hochgezählt
    }

    public function test_index_je_kanal_und_einbindung_mit_domain(): void
    {
        $this->begriff();
        $this->getJson('/api/v1/index?client=extension', self::BROWSER)->assertOk();
        $this->getJson('/api/v1/index?client=embed', self::BROWSER + ['Origin' => 'https://www.schule.ch'])->assertOk();
        $this->getJson('/api/v1/index', ['User-Agent' => 'GuzzleHttp/7'])->assertOk();
        $this->getJson('/api/v1/terms', ['User-Agent' => 'python-requests/2.32'])->assertOk();

        $this->assertSame(1, $this->zaehler('extension', 'index'));
        $this->assertSame(1, $this->zaehler('embed', 'index', 'schule.ch'));
        $this->assertSame(1, $this->zaehler('api', 'endpoint', 'index'));
        $this->assertSame(1, $this->zaehler('api', 'endpoint', 'terms'));
    }

    public function test_bots_und_vorabrufe_zaehlen_nicht(): void
    {
        $term = $this->begriff();
        $this->get("/begriffe/{$term->slug}", ['User-Agent' => 'Googlebot/2.1'])->assertOk();
        $this->get("/begriffe/{$term->slug}", self::BROWSER + ['Sec-Purpose' => 'prefetch'])->assertOk();
        $this->assertSame(0, DB::table('usage_counts')->count());
    }

    public function test_kein_personenbezug_und_kein_cookie_fuer_die_statistik(): void
    {
        $term = $this->begriff();
        $response = $this->get("/begriffe/{$term->slug}", self::BROWSER + ['REMOTE_ADDR' => '203.0.113.7']);
        $this->assertSame(['id', 'day', 'channel', 'metric', 'subject', 'count'], array_keys((array) DB::table('usage_counts')->first()));
        $this->assertStringNotContainsString('203.0.113', json_encode(DB::table('usage_counts')->get()) ?: '');
        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertContains($cookie->getName(), ['XSRF-TOKEN', config('session.cookie')], 'unerwartetes Cookie '.$cookie->getName());
        }
    }

    public function test_mcp_werkzeug_ohne_eingaben(): void
    {
        config(['glossary.mcp_enabled' => true]);
        $this->begriff();
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'glossary_explain_text', 'arguments' => ['text' => 'Geheimer Elternbrief SL']]],
            ['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-11-25'])->assertOk();
        $this->assertSame(1, $this->zaehler('mcp', 'tool', 'glossary_explain_text'));
        $this->assertStringNotContainsString('Elternbrief', json_encode(DB::table('usage_counts')->get()) ?: '');
    }

    public function test_seite_statistik_und_loeschfrist(): void
    {
        $term = $this->begriff();
        Usage::hit('web', 'term_view', $term->code);
        Usage::hit('extension', 'index');
        DB::table('usage_counts')->insert(['day' => now()->subMonths(26)->toDateString(), 'channel' => 'web', 'metric' => 'term_view', 'subject' => 'ALT', 'count' => 3]);

        $this->actingAs($this->admin())->get('/admin/statistik?tage=30')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('admin/statistics')
            ->where('begriffe.0.code', $term->code)
            ->where('begriffe.0.web', 1)
            ->where('installationen.0.anzahl', 1));
        $this->actingAs($this->userWithRoles([Role::USER], 'u@schule.example'))->get('/admin/statistik')->assertForbidden();

        $this->artisan('glossary:loeschfristen')->assertSuccessful();
        $this->assertSame(0, DB::table('usage_counts')->where('subject', 'ALT')->count());
    }
}
