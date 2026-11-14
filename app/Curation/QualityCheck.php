<?php

namespace App\Curation;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Prüfbedarf bestehender Begriffe (Konzept Einsammeln P1, Prototyp): feste Regeln über veröffentlichte, nicht
 * zusammengeführte Begriffe. Nichts wird gespeichert; die Liste entsteht bei jedem Aufruf.
 */
final class QualityCheck
{
    public const MONTHS = 24;

    public const SHORT_MAX = 200;

    public const LIMIT = 50;

    /**
     * @return list<array{key: string, label: string, hint: string, count: int, items: list<array{id: string, title: string, detail: string|null}>}>
     */
    public static function run(): array
    {
        $old = now()->subMonths(self::MONTHS);
        $rules = [
            ['ohne-kurzbeschreibung', 'Ohne Kurzbeschreibung', 'Die Kurzbeschreibung erscheint in Suche, Einbindung und Tooltip.',
                fn (Builder $q) => $q->where(fn (Builder $w) => $w->whereNull('t.short_description')->orWhere('t.short_description', '')), null],
            ['kurzbeschreibung-lang', 'Kurzbeschreibung länger als '.self::SHORT_MAX.' Zeichen', 'Für Tooltips kürzen, Details in die Beschreibung.',
                fn (Builder $q) => $q->whereRaw('length(t.short_description) > ?', [self::SHORT_MAX]), null],
            ['ohne-herkunft', 'Ohne Herkunft', 'Jeder Begriff braucht eine nachvollziehbare Quelle.',
                fn (Builder $q) => $q->whereNotExists(fn (Builder $s) => $s->selectRaw('1')->from('term_sources as ts')->whereColumn('ts.term_id', 't.id')), null],
            ['herkunft-alt', 'Herkunft vor mehr als '.self::MONTHS.' Monaten abgerufen', 'Quelle erneut prüfen und «abgerufen am» nachführen.',
                fn (Builder $q) => $q->whereExists(fn (Builder $s) => $s->selectRaw('1')->from('term_sources as ts')->whereColumn('ts.term_id', 't.id')
                    ->whereNotNull('ts.retrieved_on')->where('ts.retrieved_on', '<', $old->toDateString()))
                    ->whereNotExists(fn (Builder $s) => $s->selectRaw('1')->from('term_sources as ts2')->whereColumn('ts2.term_id', 't.id')
                        ->where('ts2.retrieved_on', '>=', $old->toDateString())), null],
            ['abgelaufen', 'Gültig bis überschritten, aber aktiv', 'Begriff zurückziehen oder Gültigkeit verlängern.',
                fn (Builder $q) => $q->where('t.is_active', true)->whereNotNull('t.valid_until')->where('t.valid_until', '<', now()->toDateString()),
                'valid_until'],
            ['unveraendert', 'Seit '.self::MONTHS.' Monaten unverändert', 'Inhalt prüfen; ist er noch richtig, genügt künftig «geprüft, unverändert» (Stufe 2).',
                fn (Builder $q) => $q->where('t.updated_at', '<', $old), 'updated_at'],
            ['ohne-kategorie', 'Ohne Kategorie', 'Kategorien helfen beim Filtern und bei der Zuständigkeit (Paten).',
                fn (Builder $q) => $q->whereNotExists(fn (Builder $s) => $s->selectRaw('1')->from('term_categories as tc')->whereColumn('tc.term_id', 't.id')), null],
        ];

        $out = [];
        foreach ($rules as [$key, $label, $hint, $where, $detail]) {
            $query = DB::table('terms as t')->whereNotNull('t.published_revision_id')->whereNull('t.merged_into_id');
            $where($query);
            $count = (clone $query)->count();
            $items = $query->orderBy('t.official_abbreviation')->orderBy('t.official_name')->limit(self::LIMIT)
                ->get(['t.id', 't.official_abbreviation', 't.official_name', 't.valid_until', 't.updated_at']);
            $out[] = [
                'key' => $key,
                'label' => $label,
                'hint' => $hint,
                'count' => $count,
                'items' => $items->map(fn (object $t): array => [
                    'id' => (string) $t->id,
                    'title' => trim(($t->official_abbreviation ? $t->official_abbreviation.' – ' : '').$t->official_name, ' –'),
                    'detail' => $detail === null || $t->{$detail} === null ? null : substr((string) $t->{$detail}, 0, 10),
                ])->all(),
            ];
        }

        return $out;
    }
}
