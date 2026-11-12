<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use App\Models\Category;
use App\Models\Term;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Öffentliche Begriffsliste und Detailseite (#24): nur veröffentlichte Revisionen. */
class PublicGlossaryTest extends TestCase
{
    private GlossaryService $glossary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->glossary = app(GlossaryService::class);
        Category::query()->create(['key' => 'per', 'abbreviation' => 'PER', 'name' => 'Personal', 'label' => 'Personal']);
    }

    /** @param  array<string, mixed>  $data */
    private function term(array $data, bool $publish = true): Term
    {
        $term = $this->glossary->save(null, $data, null, true);

        return $publish ? $this->glossary->publish($term, null) : $term;
    }

    public function test_startseite_zeigt_nur_veroeffentlichte(): void
    {
        $this->term(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung', 'categories' => ['per']]);
        $this->term(['official_abbreviation' => 'LP', 'official_name' => 'Lehrperson'], publish: false);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('home')
            ->has('begriffe', 1)
            ->where('begriffe.0.abkuerzung', 'SL')
            ->where('begriffe.0.kategorien', ['Personal']));
    }

    /** Kacheln und Tabelle auf der Startseite (#213): alle öffentlichen Werte, keine internen. */
    public function test_startseite_liefert_oeffentliche_werte_fuer_kacheln(): void
    {
        $this->term([
            'official_abbreviation' => 'SL', 'official_name' => 'Schulleitung', 'short_description' => 'Leitet die Schule.',
            'description' => 'Führt die Schule pädagogisch und organisatorisch.', 'url' => 'https://example.ch/sl',
            'note' => 'intern', 'official_aliases' => ['Schulleiterin'],
        ]);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('home')
            ->where('begriffe.0.beschreibung', 'Führt die Schule pädagogisch und organisatorisch.')
            ->where('begriffe.0.url', 'https://example.ch/sl')
            ->where('begriffe.0.aliase', ['Schulleiterin'])
            ->where('begriffe.0.code', fn ($code): bool => is_string($code) && strlen($code) === 6)
            ->missing('begriffe.0.notiz')
            ->missing('begriffe.0.note')
            ->missing('begriffe.0.revision'));
    }

    public function test_detailseite_zeigt_veroeffentlichten_stand(): void
    {
        $term = $this->term(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung', 'short_description' => 'Alt.', 'note' => 'intern']);
        $this->glossary->save($term, ['short_description' => 'Neu, noch nicht freigegeben.'], null, true);

        $this->get('/begriffe/schulleitung')->assertOk()->assertInertia(fn (Assert $page) => $page->component('term')
            ->where('begriff.kurz', 'Alt.')
            ->missing('begriff.notiz')
            ->missing('begriff.note'));
    }

    public function test_entwurf_hat_keine_detailseite(): void
    {
        $this->term(['official_abbreviation' => 'LP', 'official_name' => 'Lehrperson'], publish: false);

        $this->get('/begriffe/lehrperson')->assertNotFound();
    }

    public function test_mehrdeutige_abkuerzung_verweist_auf_andere_bedeutung(): void
    {
        $this->term(['official_abbreviation' => 'SV', 'official_name' => 'Schulverwaltung']);
        $this->term(['official_abbreviation' => 'SV', 'official_name' => 'Schülervertretung']);

        $this->get('/begriffe/schulverwaltung')->assertInertia(fn (Assert $page) => $page
            ->has('begriff.andere', 1)
            ->where('begriff.andere.0.bezeichnung', 'Schülervertretung'));
    }

    public function test_zurueckgezogen_ist_nicht_mehr_oeffentlich(): void
    {
        $term = $this->term(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung']);
        $this->glossary->unpublish($term, null);

        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('begriffe', 0));
        $this->get('/begriffe/schulleitung')->assertNotFound();
    }
}
