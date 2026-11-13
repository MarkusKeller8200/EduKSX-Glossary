<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use App\Search\SearchMisses;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Suchen ohne Treffer (X4, #285): ohne Personenbezug, Zähler, Auswertung, Löschfrist 90 Tage. */
class SucheOhneTrefferTest extends TestCase
{
    public function test_website_meldet_ohne_personenbezug(): void
    {
        $this->postJson('/suche/ohne-treffer', ['q' => '  Neue   Abkürzung '])->assertNoContent();
        $this->postJson('/suche/ohne-treffer', ['q' => 'neue abkürzung'])->assertNoContent();
        $this->postJson('/suche/ohne-treffer', ['q' => 'x'])->assertNoContent();   // zu kurz

        $rows = DB::table('search_misses')->get();
        $this->assertCount(1, $rows);
        $row = (array) $rows[0];
        $this->assertSame('neue abkürzung', $row['query']);
        $this->assertSame(2, (int) $row['count']);
        $this->assertSame(['id', 'query', 'count', 'first_seen_at', 'last_seen_at'], array_keys($row));   // keine Person, keine IP
    }

    public function test_api_meldet_nur_suchtext_ohne_filter(): void
    {
        $glossary = app(GlossaryService::class);
        $glossary->publish($glossary->save(null, ['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung'], null, true), null);

        $this->getJson('/api/v1/terms?q=schulleitung')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/terms?q=xyz-fehlt')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/terms?q=abc-fehlt&status=historical')->assertJsonPath('meta.total', 0);

        $this->assertSame(['xyz-fehlt'], DB::table('search_misses')->pluck('query')->all());
    }

    public function test_auswertung_und_frist(): void
    {
        SearchMisses::record('alt');
        DB::table('search_misses')->update(['last_seen_at' => now()->subDays(91)]);
        SearchMisses::record('neu');

        $this->actingAs($this->admin())->get('/admin/suchen-ohne-treffer')
            ->assertInertia(fn (Assert $p) => $p->component('admin/search-misses')->has('eintraege', 2));
        $this->get('/admin/suchen-ohne-treffer')->assertOk();

        Artisan::call('glossary:loeschfristen');
        $this->assertSame(['neu'], DB::table('search_misses')->pluck('query')->all());

        $id = (int) DB::table('search_misses')->value('id');
        $this->delete("/admin/suchen-ohne-treffer/{$id}")->assertRedirect();
        $this->assertSame(0, DB::table('search_misses')->count());
    }

    public function test_auswertung_nur_mit_recht(): void
    {
        $this->actingAs($this->userWithRoles(['user'], 'u@example.org'))->get('/admin/suchen-ohne-treffer')->assertForbidden();
    }
}
