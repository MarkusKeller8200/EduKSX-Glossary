<?php

namespace App\Enums;

/**
 * Art der Institution eines Kontos.
 * Werte englisch (KONZEPT-KONVENTIONEN K2, #275), Anzeige über label(); Werte bis 0.11.0 über legacyMap().
 */
enum InstitutionType: string
{
    use EnumValues;

    case School = 'school';
    case SchoolDistrict = 'school_district';
    case Municipality = 'municipality';
    case Canton = 'canton';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::School => 'Schule',
            self::SchoolDistrict => 'Schulgemeinde',
            self::Municipality => 'Gemeinde',
            self::Canton => 'Kanton',
            self::Other => 'andere',
        };
    }

    /** @return array<string, string> Wert bis 0.11.0 → Wert */
    public static function legacyMap(): array
    {
        return [
            'schule' => 'school',
            'schulgemeinde' => 'school_district',
            'gemeinde' => 'municipality',
            'kanton' => 'canton',
            'andere' => 'other',
        ];
    }
}
