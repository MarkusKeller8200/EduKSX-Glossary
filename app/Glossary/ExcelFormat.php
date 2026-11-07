<?php

namespace App\Glossary;

use App\Models\Category;
use App\Models\Source;
use App\Models\Term;
use Carbon\CarbonImmutable;

/**
 * Excel-Format des Glossary = Export des sek-intranet (#13, `Glossary::FIELDS` dort): gleiche Spaltennamen, gleiche
 * Reihenfolge, Mehrfachwerte mit «;», Kategorien/Herkunft als Label, Aktiv als ja/nein, Status als Text.
 * So lässt sich der Export des Intranets ohne Umbau importieren – und der Export des Glossary im Intranet.
 */
final class ExcelFormat
{
    /** Spaltenname → Feld im Glossary (Reihenfolge wie im Intranet). */
    public const COLUMNS = [
        'Kategorien' => 'categories',
        'Abkürzung offiziell' => 'official_abbreviation',
        'Aliase offiziell' => 'official_aliases',
        'Abkürzung eigene' => 'own_abbreviation',
        'Alias eigene' => 'own_alias',
        'Bezeichnung offiziell' => 'official_name',
        'Bezeichnung eigene' => 'own_name',
        'Kurzbeschreibung' => 'short_description',
        'Beschreibung' => 'description',
        'URL' => 'url',
        'Notiz' => 'note',
        'Herkunft' => 'sources',
        'Intern genutzt in' => 'used_in',
        'Status' => 'lifecycle_status',
        'Suchbegriffe' => 'search_terms',
        'Gültig ab' => 'valid_from',
        'Gültig bis' => 'valid_until',
        'Aktiv' => 'is_active',
    ];

    /** Spalten mit Mehrfachwerten («;»). */
    public const MULTI = ['Kategorien', 'Aliase offiziell', 'Herkunft', 'Intern genutzt in'];

    /** Offizielle Spalten (Recht «edit-official»). */
    public const OFFICIAL = ['Abkürzung offiziell', 'Aliase offiziell', 'Bezeichnung offiziell'];

    /** @return list<string> */
    public static function header(): array
    {
        return ['ID', ...array_keys(self::COLUMNS)];
    }

    /**
     * Begriff (Arbeitsstand) als Zeile: Spaltenname → Text.
     *
     * @return array<string, string>
     */
    public static function row(Term $term): array
    {
        $term->loadMissing(['categories', 'sources', 'aliases']);
        $list = fn (iterable $items): string => implode('; ', array_map(
            fn (Category|Source $i): string => $i->displayLabel(),
            [...$items],
        ));

        return [
            'ID' => $term->id,
            'Kategorien' => $list($term->categories->sortBy('sort_order')),
            'Abkürzung offiziell' => (string) $term->official_abbreviation,
            'Aliase offiziell' => implode('; ', $term->aliases->where('alias_type', 'official')->pluck('alias')->all()),
            'Abkürzung eigene' => (string) $term->own_abbreviation,
            'Alias eigene' => (string) $term->aliases->firstWhere('alias_type', 'own')?->alias,
            'Bezeichnung offiziell' => (string) $term->official_name,
            'Bezeichnung eigene' => (string) $term->own_name,
            'Kurzbeschreibung' => (string) $term->short_description,
            'Beschreibung' => (string) $term->description,
            'URL' => (string) $term->url,
            'Notiz' => (string) $term->note,
            'Herkunft' => $list($term->sources->sortBy('sort_order')),
            'Intern genutzt in' => implode('; ', $term->used_in ?? []),
            'Status' => Term::STATUS[$term->lifecycle_status] ?? $term->lifecycle_status,
            'Suchbegriffe' => (string) $term->search_terms,
            'Gültig ab' => (string) $term->valid_from?->toDateString(),
            'Gültig bis' => (string) $term->valid_until?->toDateString(),
            'Aktiv' => $term->is_active ? 'ja' : 'nein',
        ];
    }

    /**
     * Zeile aus Excel → Daten für GlossaryService::save. Nur Spalten, die in der Datei vorkommen.
     *
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    public static function data(array $row): array
    {
        $data = [];
        foreach (self::COLUMNS as $column => $field) {
            if (! array_key_exists($column, $row)) {
                continue;
            }
            $value = trim($row[$column]);
            $data[$field] = match (true) {
                in_array($column, self::MULTI, true) => self::list($value),
                $column === 'Aktiv' => ! in_array(mb_strtolower($value), ['nein', 'n', 'no', '0', 'false', 'falsch'], true),
                $column === 'Status' => $value === '' ? 'aktuell' : $value,
                in_array($column, ['Gültig ab', 'Gültig bis'], true) => self::date($value),
                default => $value,
            };
        }

        return $data;
    }

    /**
     * Zeile für den Vergleich normalisieren (Leerzeichen, Reihenfolge der Mehrfachwerte, ja/nein, Datum).
     *
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    public static function normalize(array $row): array
    {
        $out = [];
        foreach (self::COLUMNS as $column => $field) {
            if (! array_key_exists($column, $row)) {
                continue;
            }
            $value = trim($row[$column]);
            if (in_array($column, self::MULTI, true)) {
                $items = self::list($value);
                sort($items);
                $value = implode('; ', $items);
            } elseif ($column === 'Aktiv') {
                $value = self::data([$column => $value])['is_active'] ? 'ja' : 'nein';
            } elseif (in_array($column, ['Gültig ab', 'Gültig bis'], true)) {
                $value = self::date($value);
            } elseif ($column === 'Status' && $value === '') {
                $value = 'aktuell';
            }
            $out[$column] = $value;
        }

        return $out;
    }

    /** @return list<string> */
    public static function list(string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(';', $value)), fn (string $v): bool => $v !== '')));
    }

    /** JJJJ-MM-TT, TT.MM.JJJJ oder Excel-Datumszahl → JJJJ-MM-TT; leer bleibt leer, Unbekanntes unverändert. */
    public static function date(string $value): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $m) === 1) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        if (preg_match('/^\d{4,6}(\.0+)?$/', $value) === 1) {
            return CarbonImmutable::create(1899, 12, 30)?->addDays((int) $value)->toDateString() ?? $value;
        }

        return $value;
    }
}
