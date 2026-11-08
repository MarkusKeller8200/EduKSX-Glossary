<?php

namespace Tests\Feature;

use App\Glossary\ApiPresenter;
use App\Glossary\ExcelFormat;
use App\Glossary\FieldGuide;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Hilfeseite mit den Feldern eines Begriffs (#135). */
class HelpTest extends TestCase
{
    public function test_seite_ohne_anmeldung(): void
    {
        $this->get('/hilfe')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('help')->has('felder', count(FieldGuide::FIELDS))->has('weiteres'));
    }

    public function test_jede_excel_spalte_und_jedes_api_feld_ist_erklaert(): void
    {
        $excel = [];
        $api = [];
        foreach (FieldGuide::FIELDS as $f) {
            foreach (explode(' / ', (string) $f['excel']) as $e) {
                $excel[] = $e;
            }
            foreach (explode(' / ', (string) $f['api']) as $a) {
                $api[] = $a;
            }
        }
        foreach (ExcelFormat::header() as $column) {
            $this->assertContains($column, $excel, "Excel-Spalte «{$column}» fehlt in der Hilfe");
        }
        // abgeleitete API-Felder ohne eigene Zeile
        $derived = ['abbreviation', 'name', 'aliases', 'display_label', 'is_full_term', 'web_url'];
        foreach (array_diff(ApiPresenter::PUBLIC_TERM_FIELDS, $derived) as $field) {
            $this->assertContains($field, $api, "API-Feld «{$field}» fehlt in der Hilfe");
        }
    }
}
