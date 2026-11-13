<?php

namespace App\Enums;

/**
 * Ereignis der Veröffentlichung (Change-Feed).
 * Werte englisch (KONZEPT-KONVENTIONEN K2, #275), Anzeige über label(); Werte bis 0.11.0 über legacyMap().
 */
enum PublicationAction: string
{
    use EnumValues;

    case Published = 'published';
    case Unpublished = 'unpublished';
    case Merged = 'merged';

    public function label(): string
    {
        return match ($this) {
            self::Published => 'veröffentlicht',
            self::Unpublished => 'zurückgezogen',
            self::Merged => 'zusammengeführt',
        };
    }

    /** @return array<string, string> Wert bis 0.11.0 → Wert */
    public static function legacyMap(): array
    {
        return [
            'published' => 'published',
            'unpublished' => 'unpublished',
            'merged' => 'merged',
        ];
    }
}
