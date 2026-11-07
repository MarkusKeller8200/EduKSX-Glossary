<?php

namespace App\Glossary;

use App\Models\Category;
use App\Models\PublicationRelease;
use App\Models\Source;
use App\Models\Term;
use App\Models\TermPublicationEvent;
use App\Models\TermRevision;

/**
 * Darstellung für die öffentliche API v1 (#25, docs/KONZEPT-API.md, openapi/glossary-v1.json). Englische
 * Feldnamen, stabile IDs; nur veröffentlichte Revisionen, ohne redaktionelle Notiz, ohne persönliche Darstellung,
 * Altwerte, Journal oder Akteure (DEC-018, DEC-020). «abbreviation» und «name» sind die gemeinsamen Werte.
 */
final class ApiPresenter
{
    /**
     * Positivliste der öffentlichen Felder eines Begriffs (#43, DEC-018). Nur diese Felder verlassen die
     * Veröffentlichungsgrenze – API, Vollstand, Change-Feed und GitHub Pages. Neue Felder bewusst hier ergänzen.
     */
    public const PUBLIC_TERM_FIELDS = [
        'id', 'slug', 'abbreviation', 'name', 'official_abbreviation', 'official_name', 'official_aliases',
        'alternative_aliases', 'aliases', 'display_label', 'is_full_term', 'is_ambiguous', 'meaning_count',
        'short_description', 'description', 'url', 'categories', 'sources', 'status', 'is_active', 'valid_from',
        'valid_until', 'revision', 'published_at', 'web_url',
    ];

    /**
     * Vom Gesamtstand abhängige Felder: nur in Liste, Einzelbegriff und Vollstand, nicht im Change-Feed (ein altes
     * Ereignis würde sonst Zahlen eines späteren Stands zeigen).
     */
    public const CONTEXT_FIELDS = ['is_ambiguous', 'meaning_count'];

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
        $revisions = $this->visible();
        $display = $this->display($revisions);
        $terms = [];
        foreach ($revisions as $revision) {
            $terms[] = $this->withContext($this->term($revision), $revision, $display);
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

        return $this->withContext($this->term($revision), $revision, $this->display($this->visible()));
    }

    /**
     * Veröffentlichte Revisionen der öffentlich sichtbaren Begriffe.
     *
     * @return list<TermRevision>
     */
    private function visible(): array
    {
        $revisions = [];
        foreach (Term::query()->published()->current()->with('publishedRevision')->get() as $term) {
            $revision = $term->publishedRevision;
            if ($revision !== null && ($revision->snapshot['is_active'] ?? true) === true) {
                $revisions[] = $revision;
            }
        }

        return $revisions;
    }

    /** @param  list<TermRevision>  $revisions */
    private function display(array $revisions): GlossaryDisplay
    {
        return new GlossaryDisplay(array_map(fn (TermRevision $r): array => $r->snapshot, $revisions));
    }

    /**
     * Felder, die vom Gesamtstand abhängen, ergänzen (Reihenfolge wie in PUBLIC_TERM_FIELDS).
     *
     * @param  array<string, mixed>  $term
     * @return array<string, mixed>
     */
    private function withContext(array $term, TermRevision $revision, GlossaryDisplay $display): array
    {
        $term['is_ambiguous'] = $display->isAmbiguous($revision->snapshot);
        $term['meaning_count'] = $display->meaningCount($revision->snapshot);
        $ordered = [];
        foreach (self::PUBLIC_TERM_FIELDS as $field) {
            if (array_key_exists($field, $term)) {
                $ordered[$field] = $term[$field];
            }
        }

        return $ordered;
    }

    /**
     * Begriff aus einer (veröffentlichten) Revision, ohne die vom Gesamtstand abhängigen Felder (CONTEXT_FIELDS).
     * Ältere Abbilder können Altfelder (own_*, used_in) enthalten; sie werden nie ausgegeben.
     *
     * @return array<string, mixed>
     */
    public function term(TermRevision $revision): array
    {
        $s = $revision->snapshot;
        $str = fn (string $key): ?string => isset($s[$key]) && $s[$key] !== '' ? (string) $s[$key] : null;
        $official = array_values(array_map('strval', array_filter((array) ($s['official_aliases'] ?? []))));
        $alternative = array_values(array_map('strval', array_filter((array) ($s['alternative_aliases'] ?? []))));

        $term = [
            'id' => (string) $s['id'],
            'slug' => (string) $s['slug'],
            'abbreviation' => $str('official_abbreviation'),
            'name' => $str('official_name'),
            'official_abbreviation' => $str('official_abbreviation'),
            'official_name' => $str('official_name'),
            'official_aliases' => $official,
            'alternative_aliases' => $alternative,
            'aliases' => array_values(array_unique([...$official, ...$alternative])),
            'display_label' => GlossaryDisplay::displayLabel($s),
            'is_full_term' => GlossaryDisplay::isFullTerm($s),
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

        return array_intersect_key($term, array_flip(array_diff(self::PUBLIC_TERM_FIELDS, self::CONTEXT_FIELDS)));
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

    /**
     * Lizenz der Daten (DEC-016) für Einstieg und Vollstand.
     *
     * @return array{name: string, url: string, attribution: string}
     */
    public function license(): array
    {
        /** @var array{name: string, url: string, attribution: string} $license */
        $license = config('glossary.license');

        return $license;
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
