<?php

namespace App\Enums;

/**
 * Gemeinsame Hilfen der Enums (#275): Werteliste für Validierung und CHECK-Regeln, Labels für die Anzeige und die
 * Übernahme von Werten bis 0.11.0 bzw. deutscher Labels (Excel-Import).
 */
trait EnumValues
{
    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }

    /** @return array<string, string> Wert → deutsches Label */
    public static function labels(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }

    /** SQL-Ausdruck für die CHECK-Regel einer Spalte (Werte sind feste, englische Schlüssel). */
    public static function check(string $column, bool $nullable = false): string
    {
        $list = implode(', ', array_map(fn (string $v): string => "'".$v."'", self::values()));

        return ($nullable ? "\"{$column}\" is null or " : '')."\"{$column}\" in ({$list})";
    }

    /** Wert, bisheriger Wert (bis 0.11.0) oder deutsches Label → Fall; sonst null. */
    public static function fromAny(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = trim($value);
        $case = self::tryFrom($value) ?? self::tryFrom(self::legacyMap()[$value] ?? '');
        if ($case !== null) {
            return $case;
        }
        foreach (self::cases() as $c) {
            if (mb_strtolower($c->label()) === mb_strtolower($value)) {
                return $c;
            }
        }

        return null;
    }
}
