<?php

namespace App\Enums;

/**
 * Rolle der handelnden Person zum Zeitpunkt.
 * Werte englisch (KONZEPT-KONVENTIONEN K2, #275), Anzeige über label(); Werte bis 0.11.0 über legacyMap().
 */
enum ActorType: string
{
    use EnumValues;

    case Community = 'community';
    case Moderator = 'moderator';
    case Admin = 'admin';
    case Developer = 'developer';
    case Service = 'service';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Community => 'Community',
            self::Moderator => 'Moderator',
            self::Admin => 'Admin',
            self::Developer => 'Developer',
            self::Service => 'Servicekonto',
            self::System => 'System',
        };
    }

    /** @return array<string, string> Wert bis 0.11.0 → Wert */
    public static function legacyMap(): array
    {
        return [
            'community' => 'community',
            'moderator' => 'moderator',
            'admin' => 'admin',
            'developer' => 'developer',
            'service' => 'service',
            'system' => 'system',
        ];
    }
}
