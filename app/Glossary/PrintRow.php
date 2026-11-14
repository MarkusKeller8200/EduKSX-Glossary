<?php

namespace App\Glossary;

use App\Models\Category;
use App\Models\Source;
use App\Models\Term;

/**
 * Zeile für den PDF-Export (#112, Druckansicht im Browser wie im Intranet). Öffentlich nur aus ApiPresenter::terms
 * (veröffentlichte Felder, ohne Notiz); in der Redaktion aus dem Arbeitsstand, dann mit Notiz.
 *
 * @phpstan-type Row array{id: string, slug: string, abk: string, bez: string, aliase: list<string>, kurz: string,
 *     beschreibung: string, url: string, kategorien: list<string>, herkunft: list<string>, status: string,
 *     gueltig_ab: string, gueltig_bis: string, bedeutungen: int, notiz?: string}
 */
final class PrintRow
{
    /**
     * @param  array<string, mixed>  $term  Begriff aus ApiPresenter::terms()
     * @return Row
     */
    public static function fromPublic(array $term): array
    {
        $labels = fn (mixed $refs): array => array_values(array_map(
            fn (mixed $r): string => is_array($r) ? (string) ($r['label'] ?? $r['key'] ?? '') : (string) $r,
            (array) $refs,
        ));
        $status = (string) ($term['status'] ?? '');

        return [
            'id' => (string) ($term['id'] ?? ''),
            'slug' => (string) ($term['slug'] ?? ''),
            'abk' => (string) ($term['official_abbreviation'] ?? ''),
            'bez' => (string) ($term['official_name'] ?? ''),
            'aliase' => array_values(array_map('strval', (array) ($term['aliases'] ?? []))),
            'kurz' => (string) ($term['short_description'] ?? ''),
            'beschreibung' => (string) ($term['description'] ?? ''),
            'url' => (string) ($term['url'] ?? ''),
            'kategorien' => $labels($term['categories'] ?? []),
            'herkunft' => $labels($term['sources'] ?? []),
            'status' => Term::STATUS[$status] ?? $status,
            'gueltig_ab' => (string) ($term['valid_from'] ?? ''),
            'gueltig_bis' => (string) ($term['valid_until'] ?? ''),
            'bedeutungen' => (int) ($term['meaning_count'] ?? 1),
        ];
    }

    /** @return Row */
    public static function fromTerm(Term $term): array
    {
        $term->loadMissing(['categories', 'sources', 'aliases']);
        $labels = fn (iterable $items): array => array_values(array_map(
            fn (Category|Source $i): string => $i->displayLabel(),
            [...$items],
        ));

        return [
            'id' => $term->id,
            'slug' => $term->slug,
            'abk' => (string) $term->official_abbreviation,
            'bez' => (string) $term->official_name,
            'aliase' => array_values(array_map('strval', $term->aliases->whereIn('alias_type', ['official', 'alternative'])->pluck('alias')->all())),
            'kurz' => (string) $term->short_description,
            'beschreibung' => (string) $term->description,
            'url' => (string) $term->url,
            'kategorien' => $labels($term->categories->sortBy('sort_order')),
            'herkunft' => $labels($term->sources->sortBy('sort_order')),
            'status' => Term::STATUS[$term->status] ?? $term->status,
            'gueltig_ab' => (string) $term->valid_from?->toDateString(),
            'gueltig_bis' => (string) $term->valid_until?->toDateString(),
            'bedeutungen' => 1,
            'notiz' => (string) $term->note,
        ];
    }
}
