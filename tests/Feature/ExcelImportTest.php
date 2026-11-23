<?php

namespace Tests\Feature;

use App\Glossary\ExcelFormat;
use App\Glossary\GlossaryImporter;
use App\Glossary\GlossaryService;
use App\Models\Category;
use App\Models\ImportBatch;
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

    public function test_vorschau_zeigt_alle_werte_und_bisherigen_stand(): void
    {
        $this->post('/admin/import/'.$this->token($this->preview([$this->row()])));

        $this->preview([$this->row(['Kurzbeschreibung' => 'Führt die Schule.'])])
            ->assertInertia(fn (Assert $page) => $page
                ->where('plan.0.werte.Bezeichnung offiziell', 'Schulleitung')
                ->where('plan.0.werte.Kurzbeschreibung', 'Führt die Schule.')
                ->where('plan.0.vorher.Kurzbeschreibung', 'Leitet die Schule.')
                ->where('auswahl', null));
    }

    public function test_zeilen_abwaehlen_felder_abwaehlen_und_werte_ueberschreiben(): void
    {
        $this->post('/admin/import/'.$this->token($this->preview([$this->row()])));
        $rows = [
            $this->row(['Kurzbeschreibung' => 'Führt die Schule.', 'Notiz' => 'neu']),                          // Zeile 2
            $this->row(['ID' => '', 'Abkürzung offiziell' => 'LP', 'Bezeichnung offiziell' => 'Lehrperson']),   // Zeile 3
            $this->row(['ID' => '', 'Abkürzung offiziell' => 'SSA', 'Bezeichnung offiziell' => 'Schulsozialarbeit', 'URL' => 'https://example.org']),   // Zeile 4
        ];
        $token = $this->token($this->preview($rows));

        $this->post("/admin/import/{$token}", ['zeilen' => [
            '2' => ['aus' => false, 'felder_aus' => ['Kurzbeschreibung'], 'werte' => []],            // bisheriger Wert bleibt
            '3' => ['aus' => true, 'felder_aus' => [], 'werte' => []],                               // nicht importieren
            '4' => ['aus' => false, 'felder_aus' => ['URL'], 'werte' => ['Kurzbeschreibung' => 'Von Hand.']],
        ]])->assertRedirect('/admin/begriffe');

        $sl = Term::query()->findOrFail(self::INTRANET_ID);
        $this->assertSame('Leitet die Schule.', $sl->short_description);
        $this->assertSame('neu', $sl->note);
        $this->assertFalse(Term::query()->where('official_abbreviation', 'LP')->exists());
        $ssa = Term::query()->where('official_abbreviation', 'SSA')->firstOrFail();
        $this->assertSame('Von Hand.', $ssa->short_description);
        $this->assertNull($ssa->url);
    }

    public function test_erneut_pruefen_mit_ueberschriebenen_werten(): void
    {
        $token = $this->token($this->preview([$this->row(['Status' => 'gibt es nicht'])]));
        $this->post("/admin/import/{$token}/pruefen", ['zeilen' => ['2' => ['aus' => false, 'felder_aus' => [], 'werte' => ['Status' => 'aktuell']]]])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/import-preview')
                ->where('token', $token)->where('fehler', 0)->where('neu', 1)
                ->where('auswahl.2.werte.Status', 'aktuell'));
        $this->assertSame(0, Term::query()->count());   // nichts gespeichert, Vorschau bleibt gültig
        $this->post("/admin/import/{$token}", ['zeilen' => ['2' => ['aus' => false, 'felder_aus' => [], 'werte' => ['Status' => 'aktuell']]]])
            ->assertRedirect('/admin/begriffe');
        $this->assertSame(1, Term::query()->count());
    }

    public function test_ganze_liste_als_ki_unterstuetzt_markieren(): void
    {
        // bestehender, geprüfter Begriff wird nicht zurückgestuft
        $this->post('/admin/import/'.$this->token($this->preview([$this->row()])));
        $sl = Term::query()->findOrFail(self::INTRANET_ID);
        $sl->forceFill(['research_basis' => 'human_reviewed'])->save();

        $rows = [
            $this->row(['Kurzbeschreibung' => 'Führt die Schule.']),
            $this->row(['ID' => '', 'Abkürzung offiziell' => 'LP', 'Bezeichnung offiziell' => 'Lehrperson']),
        ];
        $token = $this->token($this->preview($rows));
        $this->post("/admin/import/{$token}/pruefen", ['ki' => true])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('ki', true)
                ->where('plan.1.aenderungen.Recherche', 'KI-unterstützt')
                ->missing('plan.0.aenderungen.Recherche'));
        $this->post("/admin/import/{$token}", ['ki' => true])->assertRedirect('/admin/begriffe');

        $this->assertSame('ai_assisted', Term::query()->where('official_abbreviation', 'LP')->firstOrFail()->research_basis);
        $this->assertSame('human_reviewed', $sl->refresh()->research_basis);
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
        // Format Version 2 (DEC-020): ohne eigene Spalten und «Intern genutzt in», mit «Alternative Schreibweisen»
        $this->assertCount(29, ExcelFormat::header());   // 16 + Recherche (#479) + 12 Übersetzungsspalten (#291)
        $this->assertContains('Alternative Schreibweisen', ExcelFormat::header());
    }

    public function test_import_protokoll(): void
    {
        $token = $this->token($this->preview([$this->row(), $this->row(['ID' => '', 'Abkürzung offiziell' => '', 'Bezeichnung offiziell' => ''])]));
        $this->post("/admin/import/{$token}")->assertRedirect('/admin/begriffe');

        $batch = ImportBatch::query()->sole();
        $this->assertSame('glossar-2026-11-07.xlsx', $batch->file_name);
        $this->assertSame([1, 1, 2], [$batch->created_count, $batch->error_count, $batch->rows]);
        $this->assertSame(3, $batch->errors[0]['zeile'] ?? null);
        $this->get('/admin/import')->assertInertia(fn (Assert $p) => $p->where('protokoll.0.datei', 'glossar-2026-11-07.xlsx'));
    }

    public function test_csv_export_schuetzt_formeln(): void
    {
        app(GlossaryService::class)->save(null, ['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung', 'short_description' => '=1+1'], null, true);

        $response = $this->get('/admin/export?format=csv')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = (string) $response->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringContainsString('"Abkürzung offiziell";', $csv);
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

    /** Übersetzungen im Excel (#291): Export, Import, fehlende Spalten lassen den Bestand. */
    public function test_uebersetzungen_im_excel(): void
    {
        $glossary = app(GlossaryService::class);
        $term = $glossary->save(null, ['official_abbreviation' => 'KLP', 'official_name' => 'Klassenlehrperson',
            'translations' => ['fr' => ['name' => 'Maître de classe'], 'it' => ['name' => 'Docente di classe']]], null, true);

        $row = ExcelFormat::row($term);
        $this->assertSame('Maître de classe', $row['Bezeichnung (FR)']);
        $this->assertSame('', $row['Bezeichnung (EN)']);

        $importer = app(GlossaryImporter::class);
        $result = $importer->run([['ID' => $term->id, 'Bezeichnung offiziell' => 'Klassenlehrperson', 'Bezeichnung (FR)' => 'Maîtresse de classe', 'Abkürzung (EN)' => 'CT']], false, null, true, false);
        $this->assertSame(1, $result['geaendert']);
        $this->assertSame(['Bezeichnung (FR)', 'Abkürzung (EN)'], array_keys($result['plan'][0]['aenderungen']));

        $translations = $glossary->snapshot($term->fresh() ?? $term)['translations'];
        $this->assertSame('Maîtresse de classe', $translations['fr']['name']);
        $this->assertSame('Docente di classe', $translations['it']['name']);   // keine IT-Spalte → bleibt
        $this->assertSame('CT', $translations['en']['abbreviation']);
    }
}
