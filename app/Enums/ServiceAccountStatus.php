<?php

namespace App\Enums;

/**
 * Status eines Servicekontos.
 * Werte englisch (KONZEPT-KONVENTIONEN K2, #275), Anzeige über label(); Werte bis 0.11.0 über legacyMap().
 */
enum ServiceAccountStatus: string
{
    use EnumValues;

    case Active = 'active';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'aktiv',
            self::Disabled => 'deaktiviert',
        };
    }

    /** @return array<string, string> Wert bis 0.11.0 → Wert */
    public static function legacyMap(): array
    {
        return [
            'active' => 'active',
            'disabled' => 'disabled',
        ];
    }
}
