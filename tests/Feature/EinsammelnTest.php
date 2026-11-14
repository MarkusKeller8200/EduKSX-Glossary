<?php

namespace Tests\Feature;

use App\Curation\QualityCheck;
use App\Curation\TextScanner;
use App\Glossary\GlossaryService;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Einsammeln und Prüfen (Konzept docs/KONZEPT-EINSAMMELN.md, Stufe 1, Prototyp). */
final class EinsammelnTest extends TestCase
{
    private const TEXT = <<<'TXT'
        Liebe Eltern
        Ab Montag unterstützt die Schulische Heilpädagogik (SHP) die Klassen der Mittelstufe. Die SL informiert am
        Elternabend über Deutsch als Zweitsprache (DaZ) und die neue BF-Stelle. Fragen zum Lehrplan 21 (LP21) beantwortet
        die SL gerne. Der SSD bleibt wie bisher. WICHTIG: Bitte melden Sie sich bis Freitag ab.
        TXT;

    private function begriff(string $abbreviation, string $name): void
    {
        $glossary = app(GlossaryService::class);
        $glossary->publish($glossary->save(null, ['official_abbreviation' => $abbreviation, 'official_name' => $name], null, true), null);
    }

    public function test_text_findet_bekannte_und_unbekannte_kuerzel_mit_langform(): void
    {
        $this->begriff('SL', 'Schulleitung');
        $result = TextScanner::scan(self::TEXT);

        $known = array_column($result['known'], 'count', 'abbreviation');
        $this->assertSame(['SL' => 2], $known);

        $candidates = array_column($result['candidates'], 'long_form', 'abbreviation');
        $this->assertSame('Schulische Heilpädagogik', $candidates['SHP']);
        $this->assertSame('Deutsch als Zweitsprache', $candidates['DaZ']);
        $this->assertSame('Lehrplan 21', $candidates['LP21'] ?? 'fehlt', 'Ziffern im Kürzel');
        $this->assertArrayHasKey('BF', $candidates);
        $this->assertArrayHasKey('SSD', $candidates);
        $this->assertArrayNotHasKey('WICHTIG', $candidates, 'Überschrift in Grossbuchstaben');
        $this->assertArrayNotHasKey('Liebe', $candidates);
        $this->assertStringContainsString('BF-Stelle', $result['candidates'][array_search('BF', array_column($result['candidates'], 'abbreviation'), true)]['context']);
    }

    public function test_seite_nur_mit_recht_und_ohne_speichern(): void
    {
        $this->actingAs($this->userWithRoles([Role::USER], 'lea@schule.example'));
        $this->get('/admin/einsammeln')->assertForbidden();

        $this->actingAs($this->userWithRoles([Role::ADMIN], 'red@schule.example'));
        $this->get('/admin/einsammeln')->assertOk()->assertInertia(fn (Assert $p) => $p->component('admin/curation')->where('ergebnis', null));
        $tables = collect(DB::getSchemaBuilder()->getTables())->pluck('name')->all();
        $before = array_combine($tables, array_map(fn (string $t): int => DB::table($t)->count(), $tables));

        $this->post('/admin/einsammeln/text', ['text' => self::TEXT])->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('admin/curation')->has('ergebnis.candidates', 6));
        foreach ($before as $table => $count) {
            if (! in_array($table, ['sessions', 'cache', 'cache_locks'], true)) {
                $this->assertSame($count, DB::table($table)->count(), "{$table}: nichts gespeichert");
            }
        }
    }

    public function test_entwurf_vorausgefuellt_und_pruefbedarf(): void
    {
        $this->actingAs($this->userWithRoles([Role::ADMIN], 'red@schule.example'));
        $this->get('/admin/begriffe/neu?official_abbreviation=SHP&official_name=Schulische%20Heilp%C3%A4dagogik&note=Aus%20Einsammeln')
            ->assertOk()->assertInertia(fn (Assert $p) => $p->where('werte.official_abbreviation', 'SHP')
            ->where('werte.official_name', 'Schulische Heilpädagogik')->where('werte.note', 'Aus Einsammeln'));

        $this->begriff('SL', 'Schulleitung');
        $rules = array_column(QualityCheck::run(), 'count', 'key');
        $this->assertSame(1, $rules['ohne-kurzbeschreibung']);
        $this->assertSame(1, $rules['ohne-herkunft']);
        $this->assertSame(1, $rules['ohne-kategorie']);
        $this->assertSame(0, $rules['abgelaufen']);
    }
}
