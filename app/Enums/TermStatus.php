<?php

namespace App\Enums;

/**
 * Status eines Begriffs (lifecycle_status, API «status»).
 * Werte englisch (KONZEPT-KONVENTIONEN K2, #275), Anzeige über label(); Werte bis 0.11.0 über legacyMap().
 */
enum TermStatus: string
{
    use EnumValues;

    case Current = 'current';
    case Historical = 'historical';
    case UnderReview = 'under_review';

    /**
     * Wert aus einem Abbild oder einer Eingabe: auch Werte bis 0.11.0 (Revisionen bleiben unverändert, DEC-022) und
     * deutsche Labels; leer → aktuell.
     */
    public static function normalize(?string $value): string
    {
        return (self::fromAny($value) ?? self::Current)->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Current => 'aktuell',
            self::Historical => 'historisch',
            self::UnderReview => 'zu prüfen',
        };
    }

    /** @return array<string, string> Wert bis 0.11.0 → Wert */
    public static function legacyMap(): array
    {
        return [
            'aktuell' => 'current',
            'historisch' => 'historical',
            'zu_pruefen' => 'under_review',
        ];
    }
}
