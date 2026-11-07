<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Source;

/**
 * Farbpalette wie im Intranet (sek-intranet `resources/js/lib/colors.ts`, #77). Gespeichert wird nur der Name;
 * die Klassen (hell/dunkel, Kontrast AA) stehen in `resources/js/lib/colors.ts`.
 */
final class Palette
{
    public const NAMES = [
        'ci-violet', 'ci-orange', 'ci-blue',   // CI-Farben MarKusSXCH (#107)
        'brand', 'slate', 'sky', 'cyan', 'teal', 'emerald', 'amber', 'orange', 'rose', 'violet', 'indigo',
    ];

    /**
     * Farbe je Label der Listen (für die Anzeige der Tags); ohne Farbe fehlt der Eintrag (Anzeige: slate).
     *
     * @return array{kategorien: array<string, string>, herkunft: array<string, string>}
     */
    public static function listColors(): array
    {
        $out = ['kategorien' => [], 'herkunft' => []];
        foreach (['kategorien' => Category::class, 'herkunft' => Source::class] as $list => $model) {
            foreach ($model::query()->whereNotNull('color')->get() as $item) {
                if (in_array($item->color, self::NAMES, true)) {
                    $out[$list][$item->displayLabel()] = (string) $item->color;
                }
            }
        }

        return $out;
    }
}
