<?php

namespace App\Glossary;

use App\Models\Category;
use App\Models\Source;
use App\Models\Term;
use Illuminate\Support\Collection;

/**
 * Öffentliche Sicht auf das Glossary (#24): ausschliesslich veröffentlichte Revisionen aktiver Begriffe – nie der
 * Arbeitsstand. Nicht öffentlich: redaktionelle Notiz, «Intern genutzt in», Verlauf. Grundlage auch für die
 * spätere API (docs/KONZEPT-API.md).
 */
final class PublicGlossary
{
    /**
     * Kurzform für Liste und Suche.
     *
     * @return list<array{slug: string, abkuerzung: string, bezeichnung: string, kurz: string, kategorien: list<string>, status: string, suche: string}>
     */
    public function list(): array
    {
        $categories = $this->labels(Category::class);

        return $this->published()
            ->map(function (array $s) use ($categories): array {
                $abk = (string) ($s['own_abbreviation'] ?: $s['official_abbreviation']);
                $name = (string) ($s['own_name'] ?: $s['official_name']);

                return [
                    'slug' => (string) $s['slug'],
                    'abkuerzung' => $abk,
                    'bezeichnung' => $name,
                    'kurz' => (string) $s['short_description'],
                    'kategorien' => $this->mapLabels($categories, $s['categories']),
                    'status' => Term::STATUS[(string) $s['lifecycle_status']] ?? (string) $s['lifecycle_status'],
                    'suche' => mb_strtolower(implode(' ', array_filter([
                        $abk, $name, $s['official_abbreviation'], $s['official_name'], $s['short_description'],
                        implode(' ', (array) $s['official_aliases']), $s['own_alias'], $s['search_terms'],
                    ]))),
                ];
            })
            ->sortBy(fn (array $t): string => mb_strtolower($t['abkuerzung'] ?: $t['bezeichnung']))
            ->values()
            ->all();
    }

    /**
     * Ein veröffentlichter Begriff mit allen öffentlichen Angaben; null, wenn nicht veröffentlicht oder inaktiv.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array
    {
        $term = Term::query()->published()->where('slug', $slug)->with('publishedRevision')->first();
        $revision = $term?->publishedRevision;
        if ($term === null || $revision === null) {
            return null;
        }
        $s = $revision->snapshot;
        $categories = $this->labels(Category::class);
        $sources = Source::query()->whereIn('key', array_map('strval', (array) $s['sources']))->orderBy('sort_order')->get();
        $abk = (string) ($s['own_abbreviation'] ?: $s['official_abbreviation']);

        // Andere Bedeutungen derselben Abkürzung (mehrdeutig, Regel aus dem Intranet)
        $others = $abk === '' ? [] : array_values(array_filter(
            $this->list(),
            fn (array $t): bool => $t['slug'] !== $s['slug'] && mb_strtolower($t['abkuerzung']) === mb_strtolower($abk),
        ));

        return [
            'slug' => $s['slug'],
            'abkuerzung' => $abk,
            'bezeichnung' => (string) ($s['own_name'] ?: $s['official_name']),
            'abkuerzung_offiziell' => $s['official_abbreviation'],
            'bezeichnung_offiziell' => $s['official_name'],
            'aliase' => array_values(array_filter([...(array) $s['official_aliases'], $s['own_alias']])),
            'kurz' => $s['short_description'],
            'beschreibung' => $s['description'],
            'url' => $s['url'],
            'kategorien' => $this->mapLabels($categories, $s['categories']),
            'herkunft' => $sources->map(fn (Source $source): array => [
                'label' => $source->displayLabel(),
                'name' => $source->name,
                'url' => $source->url,
            ])->all(),
            'status' => Term::STATUS[(string) $s['lifecycle_status']] ?? (string) $s['lifecycle_status'],
            'gueltig_ab' => $s['valid_from'],
            'gueltig_bis' => $s['valid_until'],
            'revision' => $revision->revision,
            'veroeffentlicht' => $revision->published_at?->toDateString(),
            'andere' => array_map(fn (array $t): array => ['slug' => $t['slug'], 'bezeichnung' => $t['bezeichnung']], $others),
        ];
    }

    /**
     * @param  array<string, string>  $labels
     * @return list<string>
     */
    private function mapLabels(array $labels, mixed $keys): array
    {
        $out = [];
        foreach ((array) $keys as $key) {
            $out[] = $labels[(string) $key] ?? (string) $key;
        }

        return $out;
    }

    /**
     * Abbilder der veröffentlichten Revisionen aktiver Begriffe.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function published(): Collection
    {
        return Term::query()->published()->with('publishedRevision')->get()
            ->map(fn (Term $t): array => $t->publishedRevision === null ? [] : $t->publishedRevision->snapshot)
            ->filter(fn (array $s): bool => $s !== [])
            ->values()
            ->toBase();
    }

    /**
     * Schlüssel → Label einer Liste.
     *
     * @param  class-string<Category|Source>  $model
     * @return array<string, string>
     */
    private function labels(string $model): array
    {
        $labels = [];
        foreach ($model::query()->orderBy('sort_order')->get() as $item) {
            $labels[$item->key] = $item->displayLabel();
        }

        return $labels;
    }
}
