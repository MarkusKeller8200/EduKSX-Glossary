<?php

namespace App\Glossary;

use Illuminate\Http\Request;

/**
 * Eingaben einer angepassten Darstellung prüfen und bereinigen (persönlich DEC-020, Organisation #148): leere Felder
 * werden NULL, Schreibweisen mit «;» getrennt, ohne Doppel.
 */
final class DisplayInput
{
    /** @return array<string, string|list<string>|null> */
    public static function from(Request $request): array
    {
        $data = $request->validate([
            'abbreviation_override' => ['nullable', 'string', 'max:60'],
            'name_override' => ['nullable', 'string', 'max:255'],
            'short_description_override' => ['nullable', 'string', 'max:160'],
            'description_override' => ['nullable', 'string', 'max:20000'],
            'aliases_override' => ['nullable', 'string', 'max:1000'],
        ]);

        $values = [];
        foreach (['abbreviation_override', 'name_override', 'short_description_override', 'description_override'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $values[$field] = $value === '' ? null : $value;
        }
        $aliases = array_values(array_unique(array_filter(
            array_map(fn (string $a): string => mb_substr(trim($a), 0, 120), explode(';', (string) ($data['aliases_override'] ?? ''))),
            fn (string $a): bool => $a !== '',
        )));
        $values['aliases_override'] = $aliases === [] ? null : $aliases;

        return $values;
    }
}
