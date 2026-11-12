<?php

namespace App\Enums;

/**
 * Art einer Revision.
 * Werte englisch (KONZEPT-KONVENTIONEN K2, #275), Anzeige über label(); Werte bis 0.11.0 über legacyMap().
 */
enum ChangeType: string
{
    use EnumValues;

    case Created = 'created';
    case Updated = 'updated';
    case Merged = 'merged';
    case Restored = 'restored';
    case Published = 'published';
    case Unpublished = 'unpublished';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'angelegt',
            self::Updated => 'geändert',
            self::Merged => 'zusammengeführt',
            self::Restored => 'wiederhergestellt',
            self::Published => 'veröffentlicht',
            self::Unpublished => 'zurückgezogen',
        };
    }

    /** @return array<string, string> Wert bis 0.11.0 → Wert */
    public static function legacyMap(): array
    {
        return [
            'created' => 'created',
            'updated' => 'updated',
            'merged' => 'merged',
            'restored' => 'restored',
            'published' => 'published',
            'unpublished' => 'unpublished',
        ];
    }
}
