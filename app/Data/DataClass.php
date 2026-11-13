<?php

namespace App\Data;

/**
 * Datenklasse einer Spalte (B2 DB-Standard, W7, #276). Daraus folgen Historie, Export und Löschung.
 */
enum DataClass: string
{
    case Public = 'public';
    case Internal = 'internal';
    case Personal = 'personal';
    case Sensitive = 'sensitive';
    case Secret = 'secret';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'öffentlich',
            self::Internal => 'intern',
            self::Personal => 'personenbezogen',
            self::Sensitive => 'besonders schützenswert',
            self::Secret => 'geheim',
        };
    }

    /** In der Historie (changes) mit altem und neuem Wert? Sonst nur «changed». */
    public function keepsValues(): bool
    {
        return $this === self::Public || $this === self::Internal;
    }

    /** Was beim Löschen der Person geschieht. */
    public function onPersonDeleted(): string
    {
        return match ($this) {
            self::Public, self::Internal => 'bleibt',
            self::Personal => 'Bezug pseudonymisieren',
            self::Sensitive => 'sofort löschen',
            self::Secret => 'löschen',
        };
    }
}
