<?php

namespace App\Glossary;

use App\Enums\Locale;
use App\Enums\TermStatus;
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
/**
 * @phpstan-type ListItem array{key: string, abbreviation: string|null, label: string, name: string, description: string|null, url: string|null, color: string|null, translations: array<string, array<string, string|null>>}
 */
final class ApiPresenter
{
    /**
     * Positivliste der öffentlichen Felder eines Begriffs (#43, DEC-018). Nur diese Felder verlassen die
     * Veröffentlichungsgrenze – API, Vollstand, Change-Feed und GitHub Pages. Neue Felder bewusst hier ergänzen.
     */
    public const PUBLIC_TERM_FIELDS = [
        'id', 'slug', 'code', 'abbreviation', 'name', 'official_abbreviation', 'official_name', 'official_aliases',
        'alternative_aliases', 'aliases', 'search_terms', 'display_label', 'is_full_term', 'is_ambiguous', 'meaning_count',
        'short_description', 'description', 'url', 'categories', 'sources', 'status', 'is_active', 'valid_from',
        'valid_until', 'translations', 'relations', 'revision', 'published_at', 'updated_at', 'web_url', 'permalink',
    ];

    /** Nur mit ?lang= (X1, #282): verlangte und je Feld gelieferte Sprache; steht nach den übrigen Feldern. */
    public const LOCALIZED_FIELDS = ['language', 'locale'];

    /** Übersetzbare Felder eines Begriffs in der API (X1, #282): API-Feld → Feld der Übersetzung. */
    public const TRANSLATED_TERM_FIELDS = [
        'abbreviation' => 'abbreviation', 'name' => 'name', 'short_description' => 'short_description', 'description' => 'description',
    ];

    /** Übersetzbare Felder einer Kategorie oder Herkunft (X1, #282). */
    public const TRANSLATED_LIST_FIELDS = ['name', 'label', 'description'];

    /**
     * Vom Gesamtstand abhängige Felder: nur in Liste, Einzelbegriff und Vollstand, nicht im Change-Feed (ein altes
     * Ereignis würde sonst Zahlen eines späteren Stands zeigen).
     */
    public const CONTEXT_FIELDS = ['is_ambiguous', 'meaning_count', 'relations'];

    /** @var array<string, array{id: string, slug: string, display_label: string}>|null öffentlich sichtbare Begriffe nach ID */
    private ?array $targets = null;

    /** @var array<string, ListItem>|null */
    private ?array $categories = null;

    /** @var array<string, ListItem>|null */
    private ?array $sources = null;

    /**
     * Alle öffentlich sichtbaren Begriffe (veröffentlicht, im Abbild aktiv), sortiert nach Abkürzung/Bezeichnung.
     *
     * @return list<array<string, mixed>>
     */
    public function terms(): array
    {
        // je Datenstand zwischengespeichert (#137)
        return app(PublicCache::class)->remember('api.terms', fn (): array => $this->buildTerms());
    }

    /** @return list<array<string, mixed>> */
    private function buildTerms(): array
    {
        $revisions = $this->visible();
        $display = $this->display($revisions);
        $terms = [];
        foreach ($revisions as $revision) {
            $terms[] = $this->withContext($this->term($revision), $revision, $display);
        }
        // feste Reihenfolge: Anzeigebezeichnung, dann ID (Cursor der Liste, #321)
        usort($terms, [TermListCursor::class, 'compare']);

        return $terms;
    }

    /**
     * Ein veröffentlichter, aktiver Begriff nach UUID oder Slug; null, wenn nicht öffentlich.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $idOrSlug): ?array
    {
        // UUID, Slug oder Code des Permalinks (#159)
        $code = Permalink::normalize($idOrSlug);
        $term = Term::query()->published()->where(fn ($q) => $q->idOrSlug($idOrSlug)
            ->when($code !== null, fn ($q) => $q->orWhere('code', $code)))
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
        $term['relations'] = $this->relations($revision->snapshot['relations'] ?? []);
        $ordered = [];
        foreach (self::PUBLIC_TERM_FIELDS as $field) {
            if (array_key_exists($field, $term)) {
                $ordered[$field] = $term[$field];
            }
        }

        return $ordered;
    }

    /**
     * Beziehungen (X2, #283) zu öffentlich sichtbaren Begriffen; zusammengeführte zeigen auf den verbleibenden.
     * Hängt vom Gesamtstand ab (Ziel veröffentlicht?), deshalb nicht im Change-Feed.
     *
     * @return list<array{type: string, id: string, slug: string, display_label: string}>
     */
    private function relations(mixed $relations): array
    {
        if ($this->targets === null) {
            $this->targets = [];
            foreach ($this->visible() as $revision) {
                $s = $revision->snapshot;
                $this->targets[(string) $s['id']] = ['id' => (string) $s['id'], 'slug' => (string) $s['slug'], 'display_label' => GlossaryDisplay::displayLabel($s)];
            }
        }
        $out = [];
        foreach ((array) $relations as $relation) {
            $relation = (array) $relation;
            $id = (string) ($relation['term_id'] ?? '');
            if (! isset($this->targets[$id])) {
                $id = (string) Term::query()->find($id)?->mergeTarget()?->id;
            }
            if (isset($this->targets[$id])) {
                $out[] = ['type' => (string) ($relation['type'] ?? '')] + $this->targets[$id];
            }
        }

        return $out;
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

        // Permalink (#154): Code steht am Begriff, nicht im Abbild
        $code = $this->codes()[(string) $s['id']] ?? null;

        $term = [
            'id' => (string) $s['id'],
            'slug' => (string) $s['slug'],
            'code' => $code,
            'abbreviation' => $str('official_abbreviation'),
            'name' => $str('official_name'),
            'official_abbreviation' => $str('official_abbreviation'),
            'official_name' => $str('official_name'),
            'official_aliases' => $official,
            'alternative_aliases' => $alternative,
            'aliases' => array_values(array_unique([...$official, ...$alternative])),
            // Suchbegriffe (öffentlich, nur für die Suche; z. B. «Suchbegriffe» im Intranet)
            'search_terms' => array_values(array_map('strval', array_filter((array) ($s['search_aliases'] ?? [])))),
            'display_label' => GlossaryDisplay::displayLabel($s),
            'is_full_term' => GlossaryDisplay::isFullTerm($s),
            'short_description' => $str('short_description'),
            'description' => $str('description'),
            'url' => $str('url'),
            'categories' => $this->refs($this->categories(), $s['categories'] ?? []),
            'sources' => $this->refs($this->sources(), $s['sources'] ?? []),
            'status' => TermStatus::normalize($s['status'] ?? $s['lifecycle_status'] ?? null),
            'is_active' => (bool) ($s['is_active'] ?? true),
            'valid_from' => $str('valid_from'),
            'valid_until' => $str('valid_until'),
            // Übersetzungen (X1, #282) aus dem Abbild: nur veröffentlichte Werte
            'translations' => self::publicTranslations($s['translations'] ?? []),
            'revision' => $revision->revision,
            'published_at' => $revision->published_at?->toIso8601ZuluString(),
            // Sync-Vertrag (#62): Zeitpunkt der veröffentlichten Revision (Stand der Inhalte)
            'updated_at' => $revision->created_at->toIso8601ZuluString(),
            'web_url' => url('/begriffe/'.$s['slug']),
            'permalink' => $code !== null ? Permalink::url($code) : null,
        ];

        return array_intersect_key($term, array_flip(array_diff(self::PUBLIC_TERM_FIELDS, self::CONTEXT_FIELDS)));
    }

    /**
     * Kompakter Index für Renderer und Browser-Erweiterung (#159, DEC-026): nur, was zum Erkennen und für die
     * Erklärbox nötig ist.
     *
     * @return list<array<string, mixed>>
     */
    public function index(): array
    {
        return app(PublicCache::class)->remember('api.index', function (): array {
            $index = [];
            foreach ($this->terms() as $t) {
                $index[] = [
                    'code' => $t['code'],
                    'abbreviation' => $t['abbreviation'],
                    'name' => $t['name'],
                    'aliases' => $t['aliases'],
                    'short_description' => $t['short_description'],
                    'is_ambiguous' => (bool) $t['is_ambiguous'],
                    'categories' => array_values(array_map(fn (mixed $c): string => is_array($c) ? (string) ($c['label'] ?? '') : '', (array) $t['categories'])),
                    'permalink' => $t['permalink'],
                ];
            }

            return $index;
        });
    }

    /**
     * Codes der Permalinks je Begriff-ID (#154).
     *
     * @return array<string, string>
     */
    private function codes(): array
    {
        // je Datenstand zwischengespeichert: neue Begriffe erscheinen erst mit einem neuen Datenstand
        /** @var array<string, string> $codes */
        $codes = app(PublicCache::class)->remember('codes', function (): array {
            $codes = [];
            foreach (Term::query()->whereNotNull('code')->pluck('code', 'id') as $id => $code) {
                $codes[(string) $id] = (string) $code;
            }

            return $codes;
        });

        return $codes;
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

    /** @return list<ListItem> */
    public function categoryList(): array
    {
        return array_values($this->categories());
    }

    /** @return list<ListItem> */
    public function sourceList(): array
    {
        return array_values($this->sources());
    }

    /**
     * @param  array<string, ListItem>  $map
     * @return list<ListItem>
     */
    private function refs(array $map, mixed $keys): array
    {
        $out = [];
        foreach ((array) $keys as $key) {
            $out[] = $map[(string) $key] ?? [
                'key' => (string) $key, 'abbreviation' => null, 'label' => (string) $key, 'name' => (string) $key,
                'description' => null, 'url' => null, 'color' => null, 'translations' => [],
            ];
        }

        return $out;
    }

    /** @return array<string, ListItem> */
    private function categories(): array
    {
        return $this->categories ??= $this->list(Category::class);
    }

    /** @return array<string, ListItem> */
    private function sources(): array
    {
        return $this->sources ??= $this->list(Source::class);
    }

    /**
     * @param  class-string<Category|Source>  $model
     * @return array<string, ListItem>
     */
    private function list(string $model): array
    {
        $out = [];
        foreach ($model::query()->with('translations')->orderBy('sort_order')->orderBy('name')->get() as $item) {
            $translations = [];
            foreach (Locale::translated() as $locale) {
                $row = $item->translations->firstWhere('locale', $locale);
                if ($row !== null) {
                    foreach (self::TRANSLATED_LIST_FIELDS as $field) {
                        $translations[$locale][$field] = $row->{$field};
                    }
                }
            }
            // ohne redaktionelle Notiz; Farbe = Name aus der Palette wie im Intranet (colors.ts)
            $out[$item->key] = [
                'key' => $item->key, 'abbreviation' => $item->abbreviation, 'label' => $item->displayLabel(),
                'name' => $item->name, 'description' => $item->description, 'url' => $item->url, 'color' => $item->color,
                'translations' => $translations,
            ];
        }

        return $out;
    }

    /**
     * Übersetzungen aus dem Abbild, nur bekannte Sprachen und Felder.
     *
     * @return array<string, array<string, string|null>>
     */
    private static function publicTranslations(mixed $translations): array
    {
        $out = [];
        foreach (Locale::translated() as $locale) {
            $row = ((array) $translations)[$locale] ?? null;
            if (! is_array($row)) {
                continue;
            }
            foreach (self::TRANSLATED_TERM_FIELDS as $field) {
                $value = $row[$field] ?? null;
                $out[$locale][$field] = is_scalar($value) && (string) $value !== '' ? (string) $value : null;
            }
        }

        return $out;
    }

    /**
     * Begriff in einer Sprache (?lang=, X1, #282): übersetzte Felder ersetzen die deutschen; fehlt eine Übersetzung,
     * bleibt der deutsche Wert. «language» nennt die verlangte und je Feld die gelieferte Sprache. Die offiziellen
     * Felder (official_*) bleiben deutsch, die Kategorien und Herkünfte werden mit übersetzt.
     *
     * @param  array<string, mixed>  $term
     * @return array<string, mixed>
     */
    public static function localizeTerm(array $term, string $locale): array
    {
        $row = (array) (((array) ($term['translations'] ?? []))[$locale] ?? []);
        $fields = [];
        foreach (array_keys(self::TRANSLATED_TERM_FIELDS) as $field) {
            $value = $row[$field] ?? null;
            if ($locale !== Locale::De->value && is_string($value) && $value !== '') {
                $term[$field] = $value;
                $fields[$field] = $locale;
            } else {
                $fields[$field] = Locale::De->value;
            }
        }
        if ($fields['abbreviation'] === $locale || $fields['name'] === $locale) {
            $term['display_label'] = (string) ($term['abbreviation'] ?: $term['name']);
        }
        foreach (['categories', 'sources'] as $list) {
            if (isset($term[$list]) && is_array($term[$list])) {
                $term[$list] = array_map(fn (array $item): array => self::localizeItem($item, $locale), $term[$list]);
            }
        }
        $term['language'] = ['requested' => $locale, 'fields' => $fields];
        $term['locale'] = $fields['name'];   // Sprache des Haupttexts; ohne Übersetzung «de» (#326)

        return $term;
    }

    /**
     * Kategorie oder Herkunft in einer Sprache; ohne Übersetzung der deutsche Wert.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function localizeItem(array $item, string $locale): array
    {
        $row = (array) (((array) ($item['translations'] ?? []))[$locale] ?? []);
        $translated = false;
        foreach (self::TRANSLATED_LIST_FIELDS as $field) {
            $value = $row[$field] ?? null;
            if ($locale !== Locale::De->value && is_string($value) && $value !== '') {
                $item[$field] = $value;
                $translated = true;
            }
        }
        $item['locale'] = $translated ? $locale : Locale::De->value;

        return $item;
    }
}
