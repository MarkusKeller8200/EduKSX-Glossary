<?php

namespace App\Enums;

/**
 * Recherche eines Begriffs (#479): wie der Eintrag entstanden ist. Leer = ohne Angabe (Bestand, kein Badge).
 * «ai_assisted» setzen Formular und Excel-Import, «human_reviewed» nur die Aktion «Als geprüft bestätigen».
 */
enum ResearchBasis: string
{
    use EnumValues;

    case AiAssisted = 'ai_assisted';
    case HumanReviewed = 'human_reviewed';

    public function label(): string
    {
        return match ($this) {
            self::AiAssisted => 'KI-unterstützt',
            self::HumanReviewed => 'geprüft',
        };
    }

    /** Text des Badges */
    public function badge(): string
    {
        return match ($this) {
            self::AiAssisted => 'KI-unterstützt recherchiert',
            self::HumanReviewed => 'Von Mensch geprüft',
        };
    }

    /** @return array<string, string> keine Werte vor 0.18.0 */
    public static function legacyMap(): array
    {
        return [];
    }
}
