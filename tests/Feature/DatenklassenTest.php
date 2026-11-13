<?php

namespace Tests\Feature;

use App\Console\Commands\DataClassOverview;
use App\Data\DataClass;
use App\Data\DataClasses;
use App\Glossary\GlossaryService;
use App\Http\Controllers\Admin\TermController;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Datenklassen je Spalte (W7, #276): vollständig, Übersicht aktuell, Abbilder ohne Personendaten. */
class DatenklassenTest extends TestCase
{
    public function test_jede_spalte_jeder_tabelle_hat_eine_klasse(): void
    {
        $actual = [];
        foreach (Schema::getTables() as $info) {
            $table = (string) $info['name'];
            if (str_starts_with($table, 'sqlite_') || in_array($table, DataClasses::FRAMEWORK, true)) {
                continue;
            }
            $actual[$table] = array_map('strval', Schema::getColumnListing($table));
        }
        ksort($actual);
        $mapped = array_map(fn (array $columns): array => array_keys($columns), DataClasses::MAP);
        ksort($mapped);

        $this->assertSame(array_keys($actual), array_keys($mapped), 'Tabellen in DataClasses::MAP stimmen nicht mit der Datenbank überein');
        foreach ($actual as $table => $columns) {
            sort($columns);
            $known = $mapped[$table];
            sort($known);
            $this->assertSame($columns, $known, "Spalten von «{$table}» in DataClasses::MAP unvollständig oder veraltet");
        }
    }

    public function test_uebersicht_ist_aktuell(): void
    {
        $this->assertSame(DataClassOverview::render(), (string) file_get_contents(base_path('docs/DATENKLASSEN.md')),
            'docs/DATENKLASSEN.md veraltet: php artisan glossary:datenklassen');
    }

    public function test_abbild_eines_begriffs_nur_oeffentlich_und_intern(): void
    {
        $term = app(GlossaryService::class)->save(null, ['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung'], null, true);
        $snapshot = app(GlossaryService::class)->snapshot($term);
        $columns = DataClasses::MAP['terms'];
        foreach (array_keys($snapshot) as $field) {
            if (isset($columns[$field])) {
                $this->assertContains($columns[$field], [DataClass::Public, DataClass::Internal], "Abbild enthält «{$field}»");
            }
        }
        $this->assertSame([], array_diff(array_keys(TermController::LABELS), array_keys($snapshot)));
    }

    public function test_historie_ohne_personenbezogene_werte(): void
    {
        $filtered = DataClasses::filterChanges('users', [
            'email' => ['old' => 'a@example.org', 'new' => 'b@example.org'],
            'password' => ['old' => 'x', 'new' => 'y'],
            'status' => ['old' => 'active', 'new' => 'blocked'],
        ]);
        $this->assertSame(['email' => 'changed', 'password' => 'changed', 'status' => ['old' => 'active', 'new' => 'blocked']], $filtered);
    }
}
