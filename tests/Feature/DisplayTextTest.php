<?php

namespace Tests\Feature;

use App\Glossary\FieldGuide;
use App\Support\DataModel;
use App\Support\Settings;
use Tests\TestCase;

/** Anzeigetexte ohne Entscheidnummern (#172): «DEC-xxx» ist für Nutzer nicht von Interesse. */
class DisplayTextTest extends TestCase
{
    public function test_keine_entscheidnummern_in_anzeigetexten(): void
    {
        $texts = [
            'Hilfe' => json_encode([FieldGuide::FIELDS, FieldGuide::EXTRAS, FieldGuide::TABS]),
            'Einstellungen' => json_encode(Settings::DEFINITIONS),
            'Datenmodell' => json_encode(DataModel::DESCRIPTIONS),
            'Versionen' => file_get_contents(base_path('CHANGELOG.md')),
            'API-Beschreibung' => file_get_contents(base_path('openapi/glossary-v1.json')),
        ];
        foreach ($texts as $name => $text) {
            $this->assertDoesNotMatchRegularExpression('/DEC-\\d+/', (string) $text, "{$name} enthält eine Entscheidnummer");
        }
    }
}
