<?php

namespace Tests\Feature;

use App\Glossary\ListTransfer;
use App\Models\Category;
use App\Models\Source;
use App\Support\Csv;
use App\Support\SpreadsheetWriter;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Excel-Export und -Import je Liste (#101, Format wie sek-intranet#157 Variante B). */
class ListTransferTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin());
        Category::query()->create(['key' => 'per', 'abbreviation' => 'PER', 'name' => 'Personal', 'label' => 'Personal', 'color' => 'sky', 'sort_order' => 1]);
        Category::query()->create(['key' => 'sch', 'abbreviation' => 'SCH', 'name' => 'Schule', 'label' => 'Schule', 'sort_order' => 2]);
    }

    /** @param  list<list<string>>  $rows */
    private function upload(array $rows, array $header = []): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('kategorien.xlsx', SpreadsheetWriter::xlsx($header ?: ListTransfer::header(), $rows));
    }

    public function test_export_mit_allen_spalten(): void
    {
        $csv = (string) $this->get('/admin/listen/kategorien/export?format=csv')->assertOk()->getContent();
        $this->assertStringContainsString(implode(';', ListTransfer::header()), $csv);
        $this->assertStringContainsString('per;PER;Personal;Personal;sky;;;;1', $csv);
        $this->get('/admin/listen/herkunft/export')->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_export_import_aendert_nichts(): void
    {
        $file = $this->upload((new ListTransfer)->rows('kategorien'));
        $this->post('/admin/listen/kategorien/import', ['datei' => $file])->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('admin/list-import-preview')->has('plan', 0)->where('zeilen', 2));
    }

    public function test_vorschau_und_uebernehmen(): void
    {
        $rows = [
            ['per', 'PER', 'Personal und Anstellung', 'Personal', 'sky', '', '', '', '1'],   // geändert
            ['', 'FIN', 'Finanzen', 'Finanzen', 'emerald', '', 'https://example.org', '', ''],   // neu
            ['', 'X', '', '', 'pink', '', '', '', ''],                                                   // Fehler
        ];
        $response = $this->post('/admin/listen/kategorien/import', ['datei' => $this->upload($rows)])->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('plan', 3)
                ->where('plan.0.art', 'geaendert')->where('plan.0.aenderungen.Bezeichnung', 'Personal → Personal und Anstellung')
                ->where('plan.1.art', 'neu')->where('plan.2.art', 'fehler'));
        $this->assertSame('Personal', Category::query()->where('key', 'per')->value('name'));   // Vorschau speichert nichts

        $token = $response->viewData('page')['props']['token'];
        $this->post("/admin/listen/kategorien/import/{$token}")->assertRedirect('/admin/listen');
        $this->assertSame('Personal und Anstellung', Category::query()->where('key', 'per')->value('name'));
        $this->assertSame('Finanzen', Category::query()->where('key', 'fin')->value('name'));
        $this->assertSame(3, Category::query()->count());
        $this->post("/admin/listen/kategorien/import/{$token}")->assertNotFound();   // nur einmal
    }

    public function test_datei_aus_dem_intranet_ordnet_ueber_kuerzel_zu(): void
    {
        // Intranet: «ID» statt «Schlüssel», dazu Kürzel
        $header = ['ID', 'Kürzel', 'Bezeichnung', 'Label', 'Farbe', 'Beschreibung', 'URL', 'Notiz', 'Reihenfolge'];
        $rows = [['0195f6a2-7c1e-7a3b-9b2d-3f4e5a6b7c8d', 'SCH', 'Schule', 'Schule', 'teal', '', '', '', '2']];
        $this->post('/admin/listen/kategorien/import', ['datei' => $this->upload($rows, $header)])->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('plan', 1)->where('plan.0.art', 'geaendert')->where('plan.0.aenderungen.Farbe', ' → teal'));
        $this->assertSame(2, Source::query()->count() + Category::query()->count());
    }

    public function test_csv_und_ohne_recht(): void
    {
        $csv = Csv::make(ListTransfer::header(), [['', 'NEU', 'Neue Kategorie', '', '', '', '', '', '']]);
        $file = UploadedFile::fake()->createWithContent('liste.csv', $csv);
        $this->post('/admin/listen/kategorien/import', ['datei' => $file])->assertOk()->assertInertia(fn (Assert $p) => $p->where('plan.0.art', 'neu'));

        $this->actingAs($this->userWithRoles(['moderator'], 'm@example.org'));
        $this->get('/admin/listen/kategorien/export')->assertForbidden();
    }
}
