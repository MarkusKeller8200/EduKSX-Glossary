<?php

namespace App\Sync;

use App\Enums\ResearchBasis;
use App\Glossary\GlossaryService;
use App\Models\Category;
use App\Models\Source;
use App\Models\Term;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Hochladen annehmen (#478, DEC-041 A16–A18), auf `master` bzw. `test`: Listen nach Schlüssel ergänzen bzw.
 * nachführen, dann je Begriff eine neue Revision als Entwurf – oder veröffentlicht, wenn verlangt und erlaubt. Passt
 * die Basisrevision nicht (Begriff seither auf dieser Instanz geändert), wird der Begriff abgelehnt («conflict»).
 */
final class UploadReceiver
{
    public function __construct(private readonly GlossaryService $glossary, private readonly ContentState $state) {}

    /**
     * Je Begriff kann `publish` verlangt werden (#503: Status wie auf der Quelle); veröffentlicht wird nur, wenn
     * `$mayPublish` gilt, sonst bleibt er Entwurf (mit Hinweis). `$publish` veröffentlicht alle (bisheriger Ablauf).
     *
     * @param  array{terms?: list<array{base_revision: int|null, term: array<string, mixed>, publish?: bool}>, categories?: list<array<string, mixed>>, sources?: list<array<string, mixed>>}  $upload
     * @return array{terms: list<array{id: string, status: string, revision: int|null, hash: string|null, code: string|null, message: string|null}>, categories: array<string, string>, sources: array<string, string>}
     */
    public function receive(array $upload, string $label, bool $publish, bool $mayPublish = true): array
    {
        $lists = DB::transaction(fn (): array => [
            'categories' => $this->lists(Category::class, $upload['categories'] ?? []),
            'sources' => $this->lists(Source::class, $upload['sources'] ?? []),
        ]);

        $results = [];
        $deferred = [];
        $items = $upload['terms'] ?? [];
        // zuerst ohne Beziehungen zu Begriffen, die es hier noch nicht gibt (neue Begriffe im selben Upload)
        $ids = array_map(fn (array $i): string => (string) ($i['term']['id'] ?? ''), $items);
        foreach ($items as $n => $item) {
            $relations = (array) ($item['term']['relations'] ?? []);
            $missing = array_filter($relations, fn (mixed $r): bool => is_array($r)
                && in_array((string) ($r['term_id'] ?? ''), $ids, true)
                && ! Term::query()->whereKey((string) ($r['term_id'] ?? ''))->exists());
            if ($missing !== []) {
                $deferred[$n] = true;
            }
            $results[$n] = $this->term($item, $label, isset($deferred[$n]));
        }
        foreach (array_keys($deferred) as $n) {
            if (in_array($results[$n]['status'], ['created', 'updated', 'unchanged'], true)) {
                $results[$n] = $this->term(['base_revision' => $results[$n]['revision'], 'term' => $items[$n]['term']], $label, false, $results[$n]['status']);
            }
        }
        foreach ($results as $n => $r) {
            $wanted = $publish || (bool) ($items[$n]['publish'] ?? false);
            if (! $wanted || ! in_array($r['status'], ['created', 'updated', 'unchanged'], true) || ($term = Term::query()->find($r['id'])) === null) {
                continue;
            }
            if (! $mayPublish) {
                $results[$n]['message'] = trim(($r['message'] ?? '').' Als Entwurf übernommen (Recht zum Veröffentlichen fehlt).');

                continue;
            }
            $before = $term->published_revision_id;
            $term = $this->glossary->publish($term, null);
            if ($term->published_revision_id !== $before) {
                $results[$n]['status'] .= '+published';
                $results[$n]['hash'] = ContentState::hash($this->state->term($term));
            }
        }

        return ['terms' => array_values($results), ...$lists];
    }

    /**
     * @param  array{base_revision?: int|null, term: array<string, mixed>}  $item
     * @return array{id: string, status: string, revision: int|null, hash: string|null, code: string|null, message: string|null}
     */
    private function term(array $item, string $label, bool $withoutRelations, ?string $statusBefore = null): array
    {
        $data = (array) $item['term'];
        $id = (string) ($data['id'] ?? '');
        $result = fn (string $status, ?Term $t = null, ?string $message = null): array => [
            'id' => $id, 'status' => $status, 'revision' => $t?->revision, 'hash' => $t !== null ? ContentState::hash($this->state->term($t->refresh())) : null,
            'code' => $t?->code, 'message' => $message,
        ];
        $base = $item['base_revision'] ?? null;
        $term = Term::query()->find($id);
        // Konfliktprüfung (A17): Basisrevision muss dem Stand dieser Instanz entsprechen
        if ($term !== null && ($base === null || (int) $base !== (int) $term->revision)) {
            return $result('conflict', $term, 'Auf der Gegenstelle seit dem letzten Holen geändert (Revision '.$term->revision.').');
        }
        if ($term === null && $base !== null) {
            return $result('conflict', null, 'Begriff gibt es auf der Gegenstelle nicht mehr.');
        }
        if ($term?->merged_into_id !== null) {
            return $result('conflict', $term, 'Begriff wurde auf der Gegenstelle zusammengeführt.');
        }

        $values = [
            'official_abbreviation' => $data['official_abbreviation'] ?? null,
            'official_name' => $data['official_name'] ?? null,
            'short_description' => $data['short_description'] ?? null,
            'description' => $data['description'] ?? null,
            'note' => $data['note'] ?? null,
            'url' => $data['url'] ?? null,
            'official_aliases' => (array) ($data['official_aliases'] ?? []),
            'alternative_aliases' => (array) ($data['alternative_aliases'] ?? []),
            'search_terms' => implode('; ', array_map('strval', (array) ($data['search_aliases'] ?? []))),
            'categories' => (array) ($data['categories'] ?? []),
            'sources' => (array) ($data['sources'] ?? []),
            'status' => (string) ($data['status'] ?? 'current'),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'valid_from' => $data['valid_from'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'translations' => (array) ($data['translations'] ?? []),
        ];
        if (! $withoutRelations) {
            $values['relations'] = (array) ($data['relations'] ?? []);
        }
        if ($term === null) {
            $values['id'] = $id;
            $values['code'] = (string) ($data['code'] ?? '');
        }
        $before = $term?->revision;
        try {
            $saved = DB::transaction(function () use ($term, $values, $data, $label): Term {
                $saved = $this->glossary->save($term, $values, null, true, $label, 'updated', ['via' => 'sync']);
                // Recherche (#479): Stand der Quelle übernehmen, auch «geprüft»
                $basis = ResearchBasis::tryFrom((string) ($data['research_basis'] ?? ''));
                if ($basis !== null && $basis->value !== $saved->research_basis) {
                    $saved->research_basis = $basis->value;
                    $saved->reviewed_at = $basis === ResearchBasis::HumanReviewed && is_string($data['reviewed_on'] ?? null)
                        ? CarbonImmutable::parse((string) $data['reviewed_on']) : null;
                    $saved->reviewed_by_id = null;
                    $saved->save();
                }

                return $saved;
            });
        } catch (InvalidArgumentException $e) {
            return $result('error', $term, $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $result('error', $term, 'Unerwarteter Fehler beim Speichern.');
        }
        $status = $statusBefore ?? ($term === null ? 'created' : ($saved->revision === $before ? 'unchanged' : 'updated'));
        $message = $term === null && isset($data['code']) && $saved->code !== $data['code'] ? 'Code auf der Gegenstelle neu vergeben: '.$saved->code : null;

        return $result($status, $saved, $message);
    }

    /**
     * Listeneinträge nach Schlüssel anlegen bzw. nachführen.
     *
     * @param  class-string<Category|Source>  $model
     * @param  list<array<string, mixed>>  $items
     * @return array<string, string> Schlüssel → Prüfsumme danach
     */
    private function lists(string $model, array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $key = (string) ($item['key'] ?? '');
            if ($key === '' || preg_match('/^[A-Za-z0-9_.-]{1,60}$/', $key) !== 1) {
                continue;
            }
            $row = $model::query()->firstOrNew(['key' => $key]);
            foreach (ContentState::LIST_FIELDS as $field) {
                if ($field !== 'key' && array_key_exists($field, $item)) {
                    $value = $item[$field];
                    $row->setAttribute($field, $field === 'sort_order' ? (int) $value : ($value === null ? null : mb_substr((string) $value, 0, $field === 'description' ? 2000 : 255)));
                }
            }
            $row->save();
            $out[$key] = ContentState::hash($this->state->listItems($model)[$key] ?? []);
        }

        return $out;
    }
}
