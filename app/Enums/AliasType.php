<?php

namespace App\Enums;

/**
 * Art eines Alias.
 * Werte englisch (KONZEPT-KONVENTIONEN K2, #275), Anzeige über label(); Werte bis 0.11.0 über legacyMap().
 */
enum AliasType: string
{
    use EnumValues;

    case Official = 'official';
    case Alternative = 'alternative';
    case Search = 'search';

    public function label(): string
    {
        return match ($this) {
            self::Official => 'offizieller Alias',
            self::Alternative => 'alternative Schreibweise',
            self::Search => 'Suchbegriff',
        };
    }

    /** @return array<string, string> Wert bis 0.11.0 → Wert */
    public static function legacyMap(): array
    {
        return [
            'official' => 'official',
            'alternative' => 'alternative',
            'search' => 'search',
        ];
    }
}
