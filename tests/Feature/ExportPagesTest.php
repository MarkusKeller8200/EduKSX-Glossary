<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use App\Models\Category;
use Tests\TestCase;

/** Reduzierte Lesefassung für GitHub Pages (#27). */
class ExportPagesTest extends TestCase
{
    private const PATH = 'storage/framework/testing/begriffe.json';

    protected function tearDown(): void
    {
        @unlink(base_path(self::PATH));
        parent::tearDown();
    }

    public function test_exportiert_nur_veroeffentlichte_begriffe_ohne_interne_felder(): void
    {
        $glossary = app(GlossaryService::class);
        Category::query()->create(['key' => 'per', 'abbreviation' => 'PER', 'name' => 'Personal', 'label' => 'Personal']);
        $sl = $glossary->save(null, [
            'official_abbreviation' => 'SL',
            'official_name' => 'Schulleitung',
            'short_description' => 'Leitet die Schule.',
            'note' => 'intern',
            'categories' => ['per'],
        ], null, true);
        $glossary->publish($sl, null);
        $glossary->save(null, ['official_abbreviation' => 'LP', 'official_name' => 'Lehrperson'], null, true);

        @mkdir(dirname(base_path(self::PATH)), 0777, true);
        $this->artisan('glossary:export-pages', ['--path' => self::PATH])->assertSuccessful();

        $data = json_decode((string) file_get_contents(base_path(self::PATH)), true);
        $this->assertIsArray($data);
        $this->assertSame('https://glossary.eduksx.ch', $data['full_url']);
        $this->assertGreaterThan(0, $data['release']);
        $this->assertCount(1, $data['terms']);
        $this->assertSame('SL', $data['terms'][0]['abbreviation']);
        $this->assertSame('Leitet die Schule.', $data['terms'][0]['short_description']);
        $this->assertSame('Personal', $data['terms'][0]['categories'][0]['label']);
        $this->assertArrayNotHasKey('note', $data['terms'][0]);
        $this->assertArrayNotHasKey('description', $data['terms'][0]);
    }
}
