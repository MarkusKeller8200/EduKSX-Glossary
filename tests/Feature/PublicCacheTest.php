<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Zwischenspeicher der öffentlichen Sicht (#137): schneller, aber immer der aktuelle Datenstand. */
class PublicCacheTest extends TestCase
{
    public function test_weniger_abfragen_und_sofort_aktuell(): void
    {
        $glossary = app(GlossaryService::class);
        Category::query()->create(['key' => 'per', 'abbreviation' => 'PER', 'name' => 'Personal', 'label' => 'Personal']);
        $glossary->publish($glossary->save(null, ['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung', 'categories' => ['per']], null, true), null);

        $this->getJson('/api/v1/terms')->assertOk()->assertJsonCount(1, 'data');
        DB::enableQueryLog();
        $this->getJson('/api/v1/terms')->assertOk();
        $cached = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThan(10, $cached, 'Zweiter Abruf sollte aus dem Zwischenspeicher kommen');

        // neue Veröffentlichung: sofort sichtbar
        $glossary->publish($glossary->save(null, ['official_abbreviation' => 'LP', 'official_name' => 'Lehrperson'], null, true), null);
        $this->getJson('/api/v1/terms')->assertJsonCount(2, 'data');
        $this->get('/')->assertInertia(fn (Assert $p) => $p->has('begriffe', 2));

        // Liste geändert (Label): sofort sichtbar
        Category::query()->where('key', 'per')->update(['label' => 'Personalwesen']);
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('begriffe.1.kategorien.0', 'Personalwesen'));
    }
}
