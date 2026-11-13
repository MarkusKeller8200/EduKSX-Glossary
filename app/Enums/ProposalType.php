<?php

namespace App\Enums;

/**
 * Art eines Vorschlags.
 * Werte englisch (KONZEPT-KONVENTIONEN K2, #275), Anzeige über label(); Werte bis 0.11.0 über legacyMap().
 */
enum ProposalType: string
{
    use EnumValues;

    case New = 'new';
    case Change = 'change';

    public function label(): string
    {
        return match ($this) {
            self::New => 'neuer Begriff',
            self::Change => 'Korrektur',
        };
    }

    /** @return array<string, string> Wert bis 0.11.0 → Wert */
    public static function legacyMap(): array
    {
        return [
            'new' => 'new',
            'change' => 'change',
        ];
    }
}
