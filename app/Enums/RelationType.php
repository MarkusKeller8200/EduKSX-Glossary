<?php

namespace App\Enums;

/**
 * Art einer Beziehung zwischen Begriffen (X2, #283). Gespeichert wird die Richtung vom Begriff aus; die Gegenrichtung
 * zeigt inverseLabel() (z. B. Nachfolger ↔ Vorgänger).
 */
enum RelationType: string
{
    use EnumValues;

    case Successor = 'successor';
    case SeeAlso = 'see_also';
    case Broader = 'broader';
    case Narrower = 'narrower';

    public function label(): string
    {
        return match ($this) {
            self::Successor => 'Nachfolger',
            self::SeeAlso => 'Siehe auch',
            self::Broader => 'Oberbegriff',
            self::Narrower => 'Unterbegriff',
        };
    }

    /** Bezeichnung aus Sicht des anderen Begriffs. */
    public function inverseLabel(): string
    {
        return match ($this) {
            self::Successor => 'Vorgänger',
            self::SeeAlso => 'Siehe auch',
            self::Broader => 'Unterbegriff',
            self::Narrower => 'Oberbegriff',
        };
    }

    /** @return array<string, string> neu eingeführt in 0.12.0, keine früheren Werte */
    public static function legacyMap(): array
    {
        return [];
    }
}
