<?php

namespace App\Enums;

/**
 * Art einer Dublettengruppe.
 * Werte englisch (KONZEPT-KONVENTIONEN K2, #275), Anzeige über label(); Werte bis 0.11.0 über legacyMap().
 */
enum DuplicateMatchType: string
{
    use EnumValues;

    case Abbreviation = 'abbreviation';
    case Name = 'name';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Abbreviation => 'gleiche Abkürzung',
            self::Name => 'gleiche Bezeichnung',
            self::Both => 'gleiche Abkürzung und Bezeichnung',
        };
    }

    /** @return array<string, string> Wert bis 0.11.0 → Wert */
    public static function legacyMap(): array
    {
        return [
            'abbreviation' => 'abbreviation',
            'name' => 'name',
            'both' => 'both',
        ];
    }
}
