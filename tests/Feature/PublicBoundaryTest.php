<?php

namespace Tests\Feature;

use App\Console\Commands\ExportPages;
use App\Glossary\ApiPresenter;
use App\Glossary\GlossaryService;
use App\Glossary\PublicGlossary;
use App\Models\Category;
use App\Models\Term;
use App\Models\TermUserOverride;
use Tests\TestCase;

/**
 * Veröffentlichungsgrenze (#43, DEC-018, Abnahmetest 6): Öffentliche Ausgaben enthalten nur freigegebene Felder des
 * veröffentlichten Stands – nie Notizen, Altwerte («Intern genutzt in»), persönliche Darstellungen, Journal,
 * Entwürfe, unveröffentlichte Begriffe oder Kontodaten (DEC-020).
 */
class PublicBoundaryTest extends TestCase
{
    /** Kennzeichen, die in keiner öffentlichen Ausgabe vorkommen dürfen. */
    private const SECRETS = [
        'GEHEIM-NOTIZ', 'GEHEIM-INTERN', 'GEHEIM-ENTWURF', 'GEHEIM-UNVEROEFFENTLICHT', 'geheim-admin@example.org',
        'GEHEIM-PERSOENLICH', 'Test admin', 'change_set', 'actor_',
    ];

    private const PAGES_PATH = 'storage/framework/testing/grenze.json';

    private Term $term;

    protected function setUp(): void
    {
        parent::setUp();
        $glossary = app(GlossaryService::class);
        Category::query()->create(['key' => 'per', 'abbreviation' => 'PER', 'name' => 'Personal', 'label' => 'Personal']);
        $admin = $this->userWithRoles(['admin'], 'geheim-admin@example.org');

        // veröffentlicht mit internen Feldern, danach ein unveröffentlichter Entwurf
        $term = $glossary->save(null, [
            'official_abbreviation' => 'SL', 'official_name' => 'Schulleitung', 'short_description' => 'Leitet die Schule.',
            'note' => 'GEHEIM-NOTIZ', 'categories' => ['per'],
        ], $admin, true);
        $term = $glossary->publish($term, $admin);
        $override = new TermUserOverride(['name_override' => 'GEHEIM-PERSOENLICH']);
        $override->term_id = $term->id;
        $override->user_id = $admin->id;
        $override->save();
        $this->term = $glossary->save($term, ['short_description' => 'GEHEIM-ENTWURF'], null, true);

        // nie veröffentlicht
        $glossary->save(null, ['official_abbreviation' => 'XX', 'official_name' => 'GEHEIM-UNVEROEFFENTLICHT'], null, true);
    }

    protected function tearDown(): void
    {
        @unlink(base_path(self::PAGES_PATH));
        parent::tearDown();
    }

    public function test_keine_internen_daten_in_oeffentlichen_ausgaben(): void
    {
        $outputs = [
            '/' => $this->get('/')->assertOk()->getContent(),
            '/begriffe/'.$this->term->slug => $this->get('/begriffe/'.$this->term->slug)->assertOk()->getContent(),
        ];
        foreach (['/api/v1', '/api/v1/terms', '/api/v1/terms/'.$this->term->id, '/api/v1/categories', '/api/v1/sources',
            '/api/v1/releases/latest', '/api/v1/changes', '/api/v1/exports?format=json'] as $path) {
            $outputs[$path] = $this->getJson($path)->assertOk()->getContent();
        }

        @mkdir(dirname(base_path(self::PAGES_PATH)), 0777, true);
        $this->artisan('glossary:export-pages', ['--path' => self::PAGES_PATH])->assertSuccessful();
        $outputs['site/begriffe.json'] = (string) file_get_contents(base_path(self::PAGES_PATH));

        foreach ($outputs as $where => $content) {
            foreach (self::SECRETS as $secret) {
                $this->assertStringNotContainsString($secret, (string) $content, "«{$secret}» erscheint in {$where}");
            }
        }
        // der veröffentlichte Stand ist sichtbar
        $this->assertStringContainsString('Leitet die Schule.', (string) $outputs['/api/v1/exports?format=json']);
    }

    public function test_felder_entsprechen_der_positivliste(): void
    {
        $terms = app(ApiPresenter::class)->terms();
        $this->assertCount(1, $terms);
        $this->assertSame(ApiPresenter::PUBLIC_TERM_FIELDS, array_keys($terms[0]));

        $detail = app(PublicGlossary::class)->find($this->term->slug);
        $this->assertNotNull($detail);
        $this->assertSame(PublicGlossary::PUBLIC_DETAIL_FIELDS, array_keys($detail));

        $this->assertSame([], array_diff(ExportPages::FIELDS, ApiPresenter::PUBLIC_TERM_FIELDS));

        // im Change-Feed ohne die vom Gesamtstand abhängigen Felder
        $change = $this->getJson('/api/v1/changes', ['Accept-Language' => 'de'])->json('data.0.term');
        $this->assertSame(array_values(array_diff(ApiPresenter::PUBLIC_TERM_FIELDS, ApiPresenter::CONTEXT_FIELDS)), array_keys($change));
    }
}
