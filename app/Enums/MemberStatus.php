<?php

namespace App\Enums;

/**
 * Status einer Mitgliedschaft.
 * Werte englisch (KONZEPT-KONVENTIONEN K2, #275), Anzeige über label(); Werte bis 0.11.0 über legacyMap().
 */
enum MemberStatus: string
{
    use EnumValues;

    case Active = 'active';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'aktiv',
            self::Blocked => 'gesperrt',
        };
    }

    /** @return array<string, string> Wert bis 0.11.0 → Wert */
    public static function legacyMap(): array
    {
        return [
            'active' => 'active',
            'blocked' => 'blocked',
        ];
    }
}
