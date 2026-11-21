<?php

namespace App\Sync;

use App\Glossary\GlossaryService;
use App\Models\Category;
use App\Models\Source;
use App\Models\Term;

/**
 * Inhalte im Entwicklungs-Sync (#478, DEC-041 A15–A17): Abbild je Begriff bzw. Listeneintrag und dessen Prüfsumme.
 * Beide Seiten bilden Abbild und Prüfsumme mit demselben Code, deshalb sind sie nach einem Holen gleich. Der Slug
 * gehört nicht dazu (die Gegenstelle macht ihn eindeutig).
 */
final class ContentState
{
    /** Felder einer Kategorie bzw. Herkunft (A8: mit Reihenfolge und Farbe) */
    public const LIST_FIELDS = ['key', 'abbreviation', 'name', 'label', 'description', 'url', 'color', 'sort_order'];

    public function __construct(private readonly GlossaryService $glossary) {}

    /**
     * Stand dieser Instanz: Begriffe (Revision und Prüfsumme) und Listen (Prüfsumme je Schlüssel).
     *
     * @return array{terms: array<string, array{0: int, 1: string}>, categories: array<string, string>, sources: array<string, string>}
     */
    public function state(): array
    {
        $terms = [];
        foreach (Term::query()->current()->with(['categories', 'sources', 'aliases', 'translations', 'termRelations'])->get() as $term) {
            $terms[(string) $term->id] = [(int) $term->revision, self::hash($this->term($term))];
        }
        ksort($terms);

        return ['terms' => $terms, 'categories' => $this->lists(Category::class), 'sources' => $this->lists(Source::class)];
    }

    /**
     * Abbild eines Begriffs zum Hochladen (ohne Slug).
     *
     * @return array<string, mixed>
     */
    public function term(Term $term): array
    {
        $snapshot = $this->glossary->snapshot($term);
        unset($snapshot['slug'], $snapshot['search_terms']);

        return $snapshot + [
            'code' => (string) $term->code,
            'research_basis' => $term->research_basis,
            'reviewed_on' => $term->research_basis === 'human_reviewed' ? $term->reviewed_at?->toDateString() : null,
        ];
    }

    /**
     * Listeneinträge zum Hochladen.
     *
     * @param  class-string<Category|Source>  $model
     * @return array<string, array<string, mixed>>
     */
    public function listItems(string $model): array
    {
        $out = [];
        foreach ($model::query()->orderBy('key')->get() as $item) {
            $row = [];
            foreach (self::LIST_FIELDS as $field) {
                $row[$field] = $item->getAttribute($field);
            }
            $row['sort_order'] = (int) $row['sort_order'];
            $out[(string) $item->key] = $row;
        }

        return $out;
    }

    /** @param  array<string, mixed>  $data */
    public static function hash(array $data): string
    {
        return hash('sha256', (string) json_encode(self::sorted($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  class-string<Category|Source>  $model
     * @return array<string, string>
     */
    private function lists(string $model): array
    {
        return array_map(fn (array $row): string => self::hash($row), $this->listItems($model));
    }

    /** Schlüssel rekursiv sortieren (Listen bleiben in ihrer Reihenfolge). */
    private static function sorted(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::sorted(...), $value);
    }
}
