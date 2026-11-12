<?php

namespace App\Enums;

/**
 * Beleg einer Herkunft (Prüfung, #106).
 * Werte englisch (KONZEPT-KONVENTIONEN K2, #275), Anzeige über label(); Werte bis 0.11.0 über legacyMap().
 */
enum Evidence: string
{
    use EnumValues;

    case Official = 'official';
    case Documented = 'documented';
    case Secondary = 'secondary';
    case Open = 'open';

    public function label(): string
    {
        return match ($this) {
            self::Official => 'amtlich belegt',
            self::Documented => 'belegt (Fachquelle)',
            self::Secondary => 'sekundär',
            self::Open => 'offen',
        };
    }

    /** @return array<string, string> Wert bis 0.11.0 → Wert */
    public static function legacyMap(): array
    {
        return [
            'amtlich' => 'official',
            'belegt' => 'documented',
            'sekundaer' => 'secondary',
            'offen' => 'open',
        ];
    }
}
