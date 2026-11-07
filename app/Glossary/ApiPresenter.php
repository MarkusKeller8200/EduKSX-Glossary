<?php

namespace App\Glossary;

use App\Models\Category;
use App\Models\PublicationRelease;
use App\Models\Source;
use App\Models\Term;
use App\Models\TermPublicationEvent;
use App\Models\TermRevision;

/**
 * Darstellung für die öffentliche API v1 (#25, docs/KONZEPT-API.md, openapi/glossary-v1.yaml). Englische
 * Feldnamen, stabile IDs; nur veröffentlichte Revisionen, ohne redaktionelle Notiz und «Intern genutzt in».
 */
final class ApiPresenter
{
    /** @var array<string, array{key: string, label: string, name: string, url: string|null}>|null */
    private ?array $categories = null;

    /** @var array<string, array{key: string, label: string, name: string, url: string|null}>|null */
    private ?array $sources = null;

    /**
     * Alle öffentlich sichtbaren Begriffe (veröffentlicht, im Abbild aktiv), sortiert nach Abkürzung/Bezeichnung.
     *
     * @return list<array<string, mixed>>
     */
    public function terms(): array
    {
        $terms = [];
        foreach (Term::query()->published()->with('publishedRevision')->get() as $term) {
            $revision = $term->publishedRevision;
            if ($revision !== null && ($revision->snapshot['is_active'] ?? true) === true) {
                $terms[] = $this->term($revision);
            }
        }
        usort($terms, fn (array $a, array $b): int => strcasecmp(
            (string) ($a['abbreviation'] ?: $a['name']),
            (string) ($b['abbreviation'] ?: $b['name']),
        ));

        return $terms;
    }

    /**
     * Ein veröffentlichter, aktiver Begriff nach UUID oder Slug; null, wenn nicht öffentlich.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $idOrSlug): ?array
    {
        $term = Term::query()->published()->where(fn ($q) => $q->where('id', $idOrSlug)->orWhere('slug', $idOrSlug))
            ->with('publishedRevision')->first();
        $revision = $term?->publishedRevision;
        if ($revision === null || ($revision->snapshot['is_active'] ?? true) !== true) {
            return null;
        }

        return $this->term($revision);
    }

    /**
     * Begriff aus einer (veröffentlichten) Revision.
     *
     * @return array<string, mixed>
     */
    public function term(TermRevision $revision): array
    {
        $s = $revision->snapshot;
        $str = fn (string $key): ?string => isset($s[$key]) && $s[$key] !== '' ? (string) $s[$key] : null;
        $abbreviation = $str('own_abbreviation') ?? $str('official_abbreviation');

        return [
            'id' => (string) $s['id'],
            'slug' => (string) $s['slug'],
            'abbreviation' => $abbreviation,
            'name' => $str('own_name') ?? $str('official_name'),
            'official_abbreviation' => $str('official_abbreviation'),
            'official_name' => $str('official_name'),
            'own_abbreviation' => $str('own_abbreviation'),
            'own_name' => $str('own_name'),
            'aliases' => array_values(array_map('strval', array_filter([...(array) ($s['official_aliases'] ?? []), $s['own_alias'] ?? null]))),
            'short_description' => $str('short_description'),
            'description' => $str('description'),
            'url' => $str('url'),
            'categories' => $this->refs($this->categories(), $s['categories'] ?? []),
            'sources' => $this->refs($this->sources(), $s['sources'] ?? []),
            'status' => (string) ($s['lifecycle_status'] ?? 'aktuell'),
            'is_active' => (bool) ($s['is_active'] ?? true),
            'valid_from' => $str('valid_from'),
            'valid_until' => $str('valid_until'),
            'revision' => $revision->revision,
            'published_at' => $revision->published_at?->toIso8601ZuluString(),
            'web_url' => url('/begriffe/'.$s['slug']),
        ];
    }

    /**
     * Aktueller Datenstand (Release 0, wenn noch nie veröffentlicht wurde).
     *
     * «cursor» ist das letzte Ereignis im Change-Feed: Wer den Vollstand holt, fragt danach mit
     * /api/v1/changes?since=<cursor> weiter (#29).
     *
     * @return array{release: int, cursor: int, published_at: string|null, checksum: string, term_count: int}
     */
    public function release(): array
    {
        $release = PublicationRelease::query()->latest('id')->first();

        return [
            'release' => $release !== null ? $release->id : 0,
            'cursor' => (int) TermPublicationEvent::query()->max('id'),
            'published_at' => $release?->created_at->toIso8601ZuluString(),
            'checksum' => $release !== null ? $release->checksum : hash('sha256', '[]'),
            'term_count' => $release !== null ? $release->term_count : 0,
        ];
    }

    /** @return list<array{key: string, label: string, name: string, url: string|null}> */
    public function categoryList(): array
    {
        return array_values($this->categories());
    }

    /** @return list<array{key: string, label: string, name: string, url: string|null}> */
    public function sourceList(): array
    {
        return array_values($this->sources());
    }

    /**
     * @param  array<string, array{key: string, label: string, name: string, url: string|null}>  $map
     * @return list<array{key: string, label: string, name: string, url: string|null}>
     */
    private function refs(array $map, mixed $keys): array
    {
        $out = [];
        foreach ((array) $keys as $key) {
            $out[] = $map[(string) $key] ?? ['key' => (string) $key, 'label' => (string) $key, 'name' => (string) $key, 'url' => null];
        }

        return $out;
    }

    /** @return array<string, array{key: string, label: string, name: string, url: string|null}> */
    private function categories(): array
    {
        return $this->categories ??= $this->list(Category::class);
    }

    /** @return array<string, array{key: string, label: string, name: string, url: string|null}> */
    private function sources(): array
    {
        return $this->sources ??= $this->list(Source::class);
    }

    /**
     * @param  class-string<Category|Source>  $model
     * @return array<string, array{key: string, label: string, name: string, url: string|null}>
     */
    private function list(string $model): array
    {
        $out = [];
        foreach ($model::query()->orderBy('sort_order')->orderBy('name')->get() as $item) {
            $out[$item->key] = ['key' => $item->key, 'label' => $item->displayLabel(), 'name' => $item->name, 'url' => $item->url];
        }

        return $out;
    }
}
