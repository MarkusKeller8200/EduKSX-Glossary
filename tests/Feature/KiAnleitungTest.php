<?php

namespace Tests\Feature;

use App\Console\Commands\AiGuide;
use App\Glossary\ExcelFormat;
use App\Support\SpreadsheetReader;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** KI-Anweisung (#481): Anleitung aus dem Code, Prompt mit den Spalten, Vorlage, erreichbar ohne Anmeldung. */
final class KiAnleitungTest extends TestCase
{
    public function test_anleitung_entspricht_dem_code(): void
    {
        // schlägt fehl, wenn sich Spalten, Status oder Werte im Code ändern und die Anleitung nicht nachgeführt ist
        $this->artisan('glossary:ki-anleitung', ['--check' => true])->assertSuccessful();

        $markdown = (string) file_get_contents(base_path(AiGuide::PATH));
        foreach (ExcelFormat::header() as $column) {
            $this->assertStringContainsString($column, $markdown, "Spalte «{$column}» fehlt in der Anleitung");
        }
    }

    public function test_prompt_nennt_genau_die_spalten_des_formats(): void
    {
        $prompt = AiGuide::prompt((string) file_get_contents(base_path(AiGuide::PATH)));
        $this->assertNotSame('', $prompt);
        $this->assertSame(1, preg_match('/^\s*ID; .*?(?=\n\s*-)/ms', $prompt, $m));
        $columns = array_map('trim', explode(';', (string) preg_replace('/\s+/', ' ', $m[0])));
        $this->assertSame(['ID', ...array_keys(ExcelFormat::COLUMNS)], $columns);
        $this->assertStringContainsString('KI-unterstützt', $prompt);
    }

    public function test_seite_vorlage_und_markdown_ohne_anmeldung(): void
    {
        $this->get('/mit-ki-recherchieren')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('ki-anleitung')
            ->where('prompt', fn (string $v): bool => str_contains($v, 'api/v1/index'))
            ->where('html', fn (string $v): bool => str_contains($v, '<table>') && ! str_contains($v, '<!--')));
        $this->get('/mit-ki-recherchieren.md')->assertOk()->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

        $response = $this->get('/mit-ki-recherchieren/vorlage.xlsx')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'vorlage').'.xlsx';
        file_put_contents($path, $response->getContent());
        $table = SpreadsheetReader::read($path, 'vorlage.xlsx', ExcelFormat::header());
        $this->assertSame(ExcelFormat::header(), $table['header']);
    }

    public function test_datei_nach_anleitung_laeuft_durch_die_vorschau(): void
    {
        $this->actingAs($this->admin());
        $csv = "ID;Kategorien;Abkürzung offiziell;Aliase offiziell;Alternative Schreibweisen;Bezeichnung offiziell;Kurzbeschreibung;Beschreibung;URL;Notiz;Herkunft;Status;Suchbegriffe;Gültig ab;Gültig bis;Aktiv;Recherche\n"
            .";;LLM;Large Language Model;;Grosses Sprachmodell;KI-Modell, das Texte versteht und erzeugt.;Ein grosses Sprachmodell wird mit sehr vielen Texten trainiert.;https://de.wikipedia.org/wiki/Large_Language_Model;Quelle: Wikipedia, abgerufen 2026-11-21;;aktuell;Sprachmodell;;;ja;KI-unterstützt\n";
        $file = UploadedFile::fake()->createWithContent('ki.csv', $csv);
        $this->post('/admin/import', ['datei' => $file])->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('fehler', 0)->where('neu', 1));
    }
}
