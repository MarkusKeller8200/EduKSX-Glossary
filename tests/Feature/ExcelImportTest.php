<?php

namespace Tests\Feature;

use App\Glossary\ExcelFormat;
use App\Models\Category;
use App\Models\Source;
use App\Models\Term;
use App\Support\SpreadsheetWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Excel-Import und -Export im Format des Intranets (#13, #15). */
class ExcelImportTest extends TestCase
{
    private const INTRANET_ID = '0195f6a2-7c1e-7a3b-9b2d-3f4e5a6b7c8d';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin());
        Category::query()->create(['key' => 'per', 'abbreviation' => 'PER', 'name' => 'Personal', 'label' => 'Personal']);
        Source::query()->create(['key' => 'vsa', 'abbreviation' => 'VSA', 'name' => 'Volksschulamt des Kantons Zürich', 'label' => 'VSA']);
    }

    /**
     * Datei wie der Export des Intranets (gleiche Spalten).
     *
     * @param  list<array<string, string>>  $rows
     */
    private function file(array $rows): UploadedFile
    {
        $header = ExcelFormat::header();
        $lines = array_map(fn (array $r): array => array_map(fn (string $c): string => $r[$c] ?? '', $header), $rows);

        return UploadedFile::fake()->createWithContent('glossar-2026-11-07.xlsx', SpreadsheetWriter::xlsx($header, $lines));
    }

    /** @return array<string, string> */
    private function row(array $extra = []): array
    {
        return [
            'ID' => self::INTRANET_ID,
            'Kategorien' => 'Personal',
            'Abkürzung offiziell' => 'SL',
            'Bezeichnung offiziell' => 'Schulleitung',
            'Kurzbeschreibung' => 'Leitet die Schule.',
            'Herkunft' => 'VSA',
            'Status' => 'aktuell',
            'Aktiv' => 'ja',
            ...$extra,
        ];
    }

    /** @param  list<array<string, string>>  $rows */
    private function preview(array $rows, bool $createLists = false): TestResponse
    {
        return $this->post('/admin/import', ['datei' => $this->file($rows), 'listen_anlegen' => $createLists]);
    }

    private function token(TestResponse $response): string
    {
        return (string) $response->viewData('page')['props']['token'];
    }

    public function test_vorschau_speichert_nichts(): void
    {
        $this->preview([$this->row()])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/import-preview')
                ->where('neu', 1)->where('fehler', 0)->where('plan.0.art', 'neu'));

        $this->assertSame(0, Term::query()->count());
    }

    public function test_uebernehmen_behaelt_die_id_aus_dem_intranet(): void
    {
        $token = $this->token($this->preview([$this->row(), $this->row(['ID' => '', 'Abkürzung offiziell' => 'LP', 'Bezeichnung offiziell' => 'Lehrperson'])]));
        $this->post("/admin/import/{$token}")->assertRedirect('/admin/begriffe');

        $this->assertSame(2, Term::query()->count());
        $term = Term::query()->findOrFail(self::INTRANET_ID);
        $this->assertSame('Schulleitung', $term->official_name);
        $this->assertNull($term->published_revision_id);   // Entwurf
        $this->assertSame(['per'], $term->categories->pluck('key')->all());
    }

    public function test_zweiter_import_aendert_nichts(): void
    {
        $this->post('/admin/import/'.$this->token($this->preview([$this->row()])));

        $this->preview([$this->row()])
            ->assertInertia(fn (Assert $page) => $page->where('neu', 0)->where('geaendert', 0)->where('plan', []));
    }

    public function test_aenderung_wird_angezeigt(): void
    {
        $this->post('/admin/import/'.$this->token($this->preview([$this->row()])));

        $this->preview([$this->row(['Kurzbeschreibung' => 'Führt die Schule.'])])
            ->assertInertia(fn (Assert $page) => $page->where('geaendert', 1)
                ->where('plan.0.aenderungen', ['Kurzbeschreibung' => 'Führt die Schule.']));
    }

    public function test_unbekannte_kategorie_ist_fehler_oder_wird_angelegt(): void
    {
        $row = $this->row(['Kategorien' => 'Personal; Recht']);

        $this->preview([$row])->assertInertia(fn (Assert $page) => $page->where('fehler', 1)->where('neu', 0));

        $token = $this->token($this->preview([$row], createLists: true)
            ->assertInertia(fn (Assert $page) => $page->where('neu', 1)->where('listen.Kategorien', ['Recht'])));
        $this->assertSame(1, Category::query()->count());   // Vorschau legt nichts an

        $this->post("/admin/import/{$token}");
        $this->assertSame(2, Category::query()->count());
    }

    public function test_zeile_ohne_abkuerzung_und_bezeichnung_ist_fehler(): void
    {
        $this->preview([$this->row(['ID' => '', 'Abkürzung offiziell' => '', 'Bezeichnung offiziell' => ''])])
            ->assertInertia(fn (Assert $page) => $page->where('fehler', 1));
    }

    public function test_export_und_vorlage_haben_die_spalten_des_intranets(): void
    {
        $this->get('/admin/export?vorlage=1')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertSame('ID', ExcelFormat::header()[0]);
        $this->assertCount(19, ExcelFormat::header());
    }

    public function test_datum_aus_excel(): void
    {
        $this->assertSame('2026-08-01', ExcelFormat::date('01.08.2026'));
        $this->assertSame('2026-08-01', ExcelFormat::date('46235'));
        $this->assertSame('', ExcelFormat::date(''));
    }

    public function test_nur_mit_anmeldung(): void
    {
        auth()->logout();
        $this->get('/admin/import')->assertRedirect('/anmelden');
        $this->get('/admin/export')->assertRedirect('/anmelden');
    }
}
