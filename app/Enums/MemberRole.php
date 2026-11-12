<?php

namespace App\Enums;

/**
 * Rolle in einer Organisation.
 * Werte englisch (KONZEPT-KONVENTIONEN K2, #275), Anzeige über label(); Werte bis 0.11.0 über legacyMap().
 */
enum MemberRole: string
{
    use EnumValues;

    case Admin = 'admin';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Organisations-Admin',
            self::Member => 'Mitglied',
        };
    }

    /** @return array<string, string> Wert bis 0.11.0 → Wert */
    public static function legacyMap(): array
    {
        return [
            'admin' => 'admin',
            'member' => 'member',
        ];
    }
}
