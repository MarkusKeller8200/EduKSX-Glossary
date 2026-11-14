<?php

namespace App\Data;

/**
 * Anonymisierte Kopien für dev und test (DB-Standard B12, #313): ersetzt alle Felder der Klassen personenbezogen,
 * besonders schützenswert und geheim nach Datenklasse (DataClasses) – Schlüssel, Verweise und Mengen bleiben gleich.
 *
 * Ersatzwerte sind erfunden und je Zeile eindeutig (z. B. für die eindeutige E-Mail-Adresse): `person-<nr>@example.invalid`,
 * «Person <nr>», «anonymisiert». Zeitpunkte und Merkmale mit Personenbezug werden leer, wo erlaubt.
 */
final class Anonymizer
{
    /** Spalten, die Verweise sind und bleiben (auch wenn als personenbezogen klassiert). */
    private const KEEP = '/^(id|.*_id)$/';

    /**
     * Eine Zeile anonymisieren.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function row(string $table, array $row, int $number): array
    {
        if ($table === 'migrations') {
            return $row;
        }
        foreach ($row as $column => $value) {
            if ($value === null || preg_match(self::KEEP, $column) === 1) {
                continue;
            }
            $class = DataClasses::of($table, $column);
            if ($class === DataClass::Public || $class === DataClass::Internal) {
                continue;
            }
            $row[$column] = self::replacement($column, $value, $number);
        }

        return $row;
    }

    private static function replacement(string $column, mixed $value, int $number): mixed
    {
        return match (true) {
            is_bool($value) => $value,
            is_int($value), is_float($value) => $value,
            str_contains($column, 'email') => "person-{$number}@example.invalid",
            in_array($column, ['name', 'first_name', 'last_name', 'community_name', 'actor_label', 'author_label', 'user_label'], true) => "Person {$number}",
            str_ends_with($column, '_at') || str_ends_with($column, '_on') => null,
            str_ends_with($column, '_override') && is_string($value) && str_starts_with($value, '[') => '[]',
            in_array($column, ['old_values', 'new_values', 'changes'], true) => '{}',
            in_array($column, ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'token_hash'], true) => 'anonymisiert-'.$number,
            in_array($column, ['issuer', 'subject', 'entra_tenant_id', 'tenant_id'], true) => "anonymisiert-{$column}-{$number}",
            default => "anonymisiert {$number}",
        };
    }
}
