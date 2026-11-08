<?php

namespace App\Glossary;

use App\Models\Category;
use App\Models\Source;
use App\Support\Palette;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Excel-Export und -Import je Liste (#101), Format wie der Listen-Export des Intranets (sek-intranet#157, Variante B):
 * Spalten wie das Formular der Seite «Listen». Zuordnung über den Schlüssel, sonst über Kürzel oder Label (Dateien
 * aus dem Intranet haben statt «Schlüssel» eine «ID»). Import immer zuerst als Vorschau; nie löschen. Unveränderte
 * Zeilen erscheinen nicht – Export → Import ändert nichts.
 */
final class ListTransfer
{
    /** Spaltenname → Feld (Reihenfolge des Exports). */
    public const COLUMNS = [
        'Schlüssel' => 'key',
        'Kürzel' => 'abbreviation',
        'Bezeichnung' => 'name',
        'Label' => 'label',
        'Farbe' => 'color',
        'Beschreibung' => 'description',
        'URL' => 'url',
        'Notiz' => 'note',
        'Reihenfolge' => 'sort_order',
    ];

    /** Zusätzlich gelesen (Intranet-Export), nur zur Information. */
    public const INTRANET_ID = 'ID';

    /** URL-Teil → Model. */
    public const LISTS = ['kategorien' => Category::class, 'herkunft' => Source::class];

    private const MAX = ['key' => 50, 'abbreviation' => 30, 'name' => 255, 'label' => 60, 'description' => 2000, 'url' => 500, 'note' => 5000];

    /** @return list<string> */
    public static function header(): array
    {
        return array_keys(self::COLUMNS);
    }

    /** @return list<list<string>> */
    public function rows(string $list): array
    {
        $model = self::LISTS[$list];
        $rows = [];
        foreach ($model::query()->orderBy('sort_order')->orderBy('name')->get() as $item) {
            $row = [];
            foreach (self::COLUMNS as $field) {
                $row[] = $this->text($item->getAttribute($field));
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Vorschau: neu, geändert, Fehler. Unveränderte Zeilen fehlen.
     *
     * @param  list<array<string, string>>  $rows  Zeilen nach Spaltenname
     * @return list<array{zeile: int, art: string, titel: string, meldungen: list<string>, aenderungen: array<string, string>, id?: string, daten?: array<string, mixed>}>
     */
    public function plan(string $list, array $rows): array
    {
        $model = self::LISTS[$list];
        $items = $model::query()->get();
        $byKey = $items->keyBy('key');
        $find = function (string $value, string $field) use ($items): ?Model {
            if ($value === '') {
                return null;
            }
            $hits = $items->filter(fn (Model $i): bool => mb_strtolower((string) $i->getAttribute($field)) === mb_strtolower($value));

            return $hits->count() === 1 ? $hits->first() : null;
        };
        $seen = [];
        $newKeys = [];
        $plan = [];
        foreach ($rows as $n => $row) {
            $fields = [];
            foreach (self::COLUMNS as $column => $field) {
                if (array_key_exists($column, $row)) {
                    $fields[$field] = trim($row[$column]);
                }
            }
            $key = $fields['key'] ?? '';
            $existing = $key !== '' ? $byKey->get($key) : null;
            $existing ??= $key === '' ? ($find($fields['abbreviation'] ?? '', 'abbreviation') ?? $find($fields['label'] ?? '', 'label')) : null;
            $title = trim(($fields['abbreviation'] ?? $this->text($existing?->getAttribute('abbreviation'))).' – '.($fields['name'] ?? $this->text($existing?->getAttribute('name'))), ' –');

            $errors = [];
            $data = $this->check($fields, $existing, $errors);
            if ($existing !== null && isset($seen[(string) $existing->getKey()])) {
                $errors[] = 'Eintrag kommt in der Datei mehrfach vor (Zeile '.$seen[(string) $existing->getKey()].').';
            }
            if ($existing === null) {
                if ($key !== '' && preg_match('/^[a-z0-9-]+$/', $key) !== 1) {
                    $errors[] = 'Schlüssel nur aus Kleinbuchstaben, Ziffern und Bindestrich.';
                }
                $data['key'] = $key !== '' ? $key : $this->newKey($model, (string) (($data['abbreviation'] ?? '') ?: ($data['name'] ?? '')), $newKeys);
                if (isset($newKeys[$data['key']]) && $key !== '') {
                    $errors[] = "Schlüssel «{$key}» kommt in der Datei mehrfach vor.";
                }
                $newKeys[(string) $data['key']] = true;
            }
            if ($errors !== []) {
                $plan[] = ['zeile' => $n + 2, 'art' => 'fehler', 'titel' => $title, 'meldungen' => $errors, 'aenderungen' => []];

                continue;
            }
            if ($existing !== null) {
                $seen[(string) $existing->getKey()] = $n + 2;
                unset($data['key']);
                $changes = array_filter($data, fn (mixed $v, string $f): bool => $this->text($existing->getAttribute($f)) !== $this->text($v), ARRAY_FILTER_USE_BOTH);
                if ($changes === []) {
                    continue;
                }
                $plan[] = [
                    'zeile' => $n + 2, 'art' => 'geaendert', 'titel' => $title, 'meldungen' => [], 'id' => (string) $existing->getKey(),
                    'aenderungen' => $this->labels($changes, $existing), 'daten' => $changes,
                ];

                continue;
            }
            $plan[] = [
                'zeile' => $n + 2, 'art' => 'neu', 'titel' => $title, 'meldungen' => [],
                'aenderungen' => $this->labels(array_filter($data, fn (mixed $v): bool => $v !== null && $v !== ''), null), 'daten' => $data,
            ];
        }

        return $plan;
    }

    /**
     * Vorschau übernehmen (Zeilen mit Fehler werden übersprungen).
     *
     * @param  array<mixed>  $plan
     * @return array{neu: int, geaendert: int}
     */
    public function apply(string $list, array $plan): array
    {
        $model = self::LISTS[$list];
        $new = 0;
        $changed = 0;
        DB::transaction(function () use ($model, $plan, &$new, &$changed): void {
            $order = (int) $model::query()->max('sort_order');
            foreach ($plan as $p) {
                if (! is_array($p) || ! is_array($p['daten'] ?? null)) {
                    continue;
                }
                /** @var array<string, mixed> $data */
                $data = $p['daten'];
                if (($p['art'] ?? '') === 'neu' && ! $model::query()->where('key', $data['key'] ?? '')->exists()) {
                    $data['sort_order'] ??= ++$order;
                    $model::query()->create($data);
                    $new++;
                } elseif (($p['art'] ?? '') === 'geaendert' && ($item = $model::query()->whereKey((string) ($p['id'] ?? ''))->first()) !== null) {
                    $item->fill($data)->save();
                    $changed++;
                }
            }
        });

        return ['neu' => $new, 'geaendert' => $changed];
    }

    /**
     * Felder prüfen (Regeln wie im Formular). Fehlende Spalten behalten den bisherigen Wert.
     *
     * @param  array<string, string>  $fields
     * @param  list<string>  $errors
     * @return array<string, mixed>
     */
    private function check(array $fields, ?Model $existing, array &$errors): array
    {
        $value = fn (string $f): string => array_key_exists($f, $fields) ? $fields[$f] : $this->text($existing?->getAttribute($f));
        $label = array_flip(self::COLUMNS);
        $data = [];
        foreach (['abbreviation', 'name', 'label', 'description', 'url', 'note'] as $f) {
            $v = $value($f);
            if (mb_strlen($v) > self::MAX[$f]) {
                $errors[] = "{$label[$f]} ist zu lang (höchstens ".self::MAX[$f].' Zeichen).';
            }
            $data[$f] = $v === '' ? null : $v;
        }
        if (($data['name'] ?? null) === null) {
            $errors[] = 'Bezeichnung fehlt.';
        }
        if ($data['url'] !== null && preg_match('#^https?://\S+$#i', (string) $data['url']) !== 1) {
            $errors[] = 'Die URL muss mit http:// oder https:// beginnen.';
        }
        $color = mb_strtolower($value('color'));
        if ($color !== '' && ! in_array($color, Palette::NAMES, true)) {
            $errors[] = "Farbe «{$color}» gibt es nicht (".implode(', ', Palette::NAMES).').';
        }
        $data['color'] = $color === '' ? null : $color;
        $order = $value('sort_order');
        if ($order !== '') {
            if (preg_match('/^\d{1,5}$/', $order) !== 1) {
                $errors[] = 'Reihenfolge: ganze Zahl.';
            } else {
                $data['sort_order'] = (int) $order;
            }
        }

        return $data;
    }

    /**
     * @param  class-string<Category|Source>  $model
     * @param  array<string, true>  $taken
     */
    private function newKey(string $model, string $base, array $taken): string
    {
        $base = Str::slug($base, '-', 'de') ?: Str::lower(Str::random(6));
        $key = $base;
        for ($i = 2; isset($taken[$key]) || $model::query()->where('key', $key)->exists(); $i++) {
            $key = "{$base}-{$i}";
        }

        return $key;
    }

    /**
     * Änderungen nach Spaltenname, für die Vorschau.
     *
     * @param  array<string, mixed>  $changes
     * @return array<string, string>
     */
    private function labels(array $changes, ?Model $existing): array
    {
        $label = array_flip(self::COLUMNS);
        $out = [];
        foreach ($changes as $f => $v) {
            $out[$label[$f] ?? $f] = $existing === null ? $this->text($v) : $this->text($existing->getAttribute($f)).' → '.$this->text($v);
        }

        return $out;
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
