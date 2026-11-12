<?php

namespace App\Enums;

/**
 * Status eines Kontos.
 * Werte englisch (KONZEPT-KONVENTIONEN K2, #275), Anzeige über label(); Werte bis 0.11.0 über legacyMap().
 */
enum UserStatus: string
{
    use EnumValues;

    case Active = 'active';
    case Pending = 'pending';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'aktiv',
            self::Pending => 'unbestätigt',
            self::Blocked => 'gesperrt',
        };
    }

    /** @return array<string, string> Wert bis 0.11.0 → Wert */
    public static function legacyMap(): array
    {
        return [
            'active' => 'active',
            'pending' => 'pending',
            'blocked' => 'blocked',
        ];
    }
}
