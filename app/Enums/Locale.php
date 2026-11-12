<?php

namespace App\Enums;

/**
 * Sprache von Inhalten (ISO 639-1); Deutsch ist die Grundsprache.
 * Werte englisch (KONZEPT-KONVENTIONEN K2, #275), Anzeige über label(); Werte bis 0.11.0 über legacyMap().
 */
enum Locale: string
{
    use EnumValues;

    case De = 'de';
    case Fr = 'fr';
    case It = 'it';
    case En = 'en';

    public function label(): string
    {
        return match ($this) {
            self::De => 'Deutsch',
            self::Fr => 'Französisch',
            self::It => 'Italienisch',
            self::En => 'Englisch',
        };
    }

    /** @return array<string, string> Wert bis 0.11.0 → Wert */
    public static function legacyMap(): array
    {
        return [
            'de' => 'de',
            'fr' => 'fr',
            'it' => 'it',
            'en' => 'en',
        ];
    }
}
