<?php

namespace App\Sync;

use App\Glossary\GlossaryService;
use App\Models\Category;
use App\Models\Source;
use App\Models\Term;
use App\Models\TermRevision;
use Carbon\CarbonImmutable;

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
     * Stand dieser Instanz (#503): Begriffe mit Revision, Prüfsumme, Zeitpunkt der letzten inhaltlichen Änderung
     * (Revision) und ob der Arbeitsstand veröffentlicht ist; Listen mit Prüfsumme und Zeitpunkt der Änderung.
     *
     * @return array{terms: array<string, array{0: int, 1: string, 2: string|null, 3: bool}>, categories: array<string, array{0: string, 1: string|null}>, sources: array<string, array{0: string, 1: string|null}>}
     */
    public function state(): array
    {
        $all = Term::query()->current()->with(['categories', 'sources', 'aliases', 'translations', 'termRelations'])->get();
        // Zeitpunkt und ID der jeweils aktuellen Revision in einer Abfrage
        $revisions = [];
        foreach ($all->chunk(500) as $chunk) {
            foreach (TermRevision::query()->whereIn('term_id', $chunk->pluck('id')->all())->get(['id', 'term_id', 'revision', 'created_at']) as $r) {
                $revisions[$r->term_id.':'.$r->revision] = $r;
            }
        }
        $terms = [];
        foreach ($all as $term) {
            $current = $revisions[$term->id.':'.$term->revision] ?? null;
            $terms[(string) $term->id] = [
                (int) $term->revision,
                self::hash($this->term($term)),
                ($current !== null ? $current->created_at : $term->updated_at)?->toIso8601String(),
                $current !== null && $term->published_revision_id === $current->id,
            ];
        }
        ksort($terms);

        return ['terms' => $terms, 'categories' => $this->lists(Category::class), 'sources' => $this->lists(Source::class)];
    }

    /** Prüfsumme aus einem Eintrag des Stands bzw. der Basis (Begriff: [Revision, Prüfsumme, …], Liste: [Prüfsumme, …] oder Text). */
    public static function hashOf(mixed $entry, bool $term): ?string
    {
        if ($entry === null) {
            return null;
        }
        if (! is_array($entry)) {
            return (string) $entry;
        }
        $value = $term ? ($entry[1] ?? null) : ($entry[0] ?? null);

        return $value === null ? null : (string) $value;
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
     * @return array<string, array{0: string, 1: string|null}>
     */
    private function lists(string $model): array
    {
        $times = $model::query()->pluck('updated_at', 'key');
        $out = [];
        foreach ($this->listItems($model) as $key => $row) {
            $time = $times[$key] ?? null;
            $out[$key] = [self::hash($row), $time === null ? null : CarbonImmutable::parse((string) $time)->toIso8601String()];
        }

        return $out;
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
