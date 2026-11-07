<?php

namespace App\Glossary;

use App\Models\Category;
use App\Models\PublicationRelease;
use App\Models\Source;
use App\Models\Term;
use App\Models\TermPublicationEvent;
use App\Models\TermRevision;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Service-Schicht des Glossary (#12, docs/KONZEPT-DATENMODELL.md): einzige Stelle, die Begriffe ändert. Website,
 * Redaktion, Excel-Import, API und später MCP rufen diese Methoden auf – gleiche Regeln, gleicher Verlauf.
 *
 * - Jede inhaltliche Änderung erzeugt eine unveränderliche Revision (Verlauf).
 * - Öffentlich ist nur die veröffentlichte Revision; Änderungen danach bleiben Entwurf bis zur nächsten Freigabe.
 * - Offizielle Felder nur mit Recht «edit-official» (leer → Wert setzen ist erlaubt, wie im Intranet).
 * - Jede Veröffentlichung bzw. jeder Rückzug erzeugt einen neuen Datenstand (PublicationRelease).
 */
final class GlossaryService
{
    /** Felder, deren Wert beim Zusammenführen aus einem der Begriffe gewählt wird (#16). */
    public const MERGE_CHOICE = [
        'official_abbreviation', 'own_abbreviation', 'official_name', 'own_name', 'short_description', 'description',
        'url', 'note', 'lifecycle_status', 'valid_from', 'valid_until',
    ];

    /** Einfache Textfelder des Begriffs. */
    private const TEXT = [
        'official_abbreviation', 'own_abbreviation', 'official_name', 'own_name', 'short_description', 'description',
        'note', 'url', 'search_terms',
    ];

    /**
     * Begriff anlegen (ohne $term) oder ändern. Ergebnis ist immer ein Entwurf; veröffentlicht wird mit publish().
     *
     * Felder in $data (alle optional beim Ändern): die Textfelder, lifecycle_status, is_active, valid_from,
     * valid_until, used_in (Liste), official_aliases (Liste), own_alias, categories und sources (Listen von
     * Schlüsseln). Beim Anlegen optional «id» (z. B. aus dem Intranet, #13).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException bei ungültigen Werten
     * @throws OfficialFieldLocked ohne Recht für offizielle Felder
     */
    public function save(?Term $term, array $data, ?User $author, bool $canEditOfficial, ?string $authorLabel = null, string $changeType = 'updated'): Term
    {
        if ($term?->merged_into_id !== null) {
            throw new InvalidArgumentException('Begriff wurde zusammengeführt und kann nicht mehr geändert werden.');
        }
        $creating = $term === null;
        $term ??= new Term;
        $values = $this->normalize($data, $creating);

        if (! $creating && ! $canEditOfficial) {
            $this->guardOfficial($term, $values);
        }

        return DB::transaction(function () use ($term, $values, $creating, $data, $author, $authorLabel, $changeType): Term {
            if ($creating && isset($data['id'])) {
                $id = (string) $data['id'];
                if (! Str::isUuid($id)) {
                    throw new InvalidArgumentException("ID «{$id}» ist keine UUID.");
                }
                if (Term::query()->whereKey($id)->exists()) {
                    throw new InvalidArgumentException("Begriff mit ID {$id} gibt es schon.");
                }
                $term->id = $id;
            }

            $before = $creating ? null : $this->snapshot($term);

            $term->fill(array_intersect_key($values, array_flip($term->getFillable())));
            if ($creating) {
                $term->slug = $this->uniqueSlug($term);
            }
            $term->save();

            if ($creating || array_intersect_key($values, array_flip(['official_aliases', 'own_alias', 'search_terms'])) !== []) {
                $this->syncAliases($term, $values);
            }
            if (array_key_exists('categories', $values)) {
                $term->categories()->sync($values['categories']);
            }
            if (array_key_exists('sources', $values)) {
                $term->sources()->sync($values['sources']);
            }

            $term->unsetRelations();
            $after = $this->snapshot($term);
            if (! $creating && $after === $before) {
                return $term;   // nichts geändert → keine Revision
            }

            $term->revision++;
            $term->save();
            $term->revisions()->create([
                'revision' => $term->revision,
                'change_type' => $creating ? 'created' : $changeType,
                'snapshot' => $this->snapshot($term),
                'author_id' => $author?->id,
                'author_label' => $authorLabel ?? $author?->name,
            ]);

            return $term->refresh();
        });
    }

    /** Aktuellen Arbeitsstand veröffentlichen (Freigabe). Ohne Änderung seit der letzten Freigabe: nichts tun. */
    public function publish(Term $term, ?User $approver): Term
    {
        return DB::transaction(function () use ($term, $approver): Term {
            $event = $this->markPublished($term, $approver);
            if ($event !== null) {
                $this->newRelease($term->id, 'published', $approver, [$event]);
            }

            return $term->refresh();
        });
    }

    /**
     * Mehrere Begriffe in einem Schritt veröffentlichen (#23): ein gemeinsamer Datenstand statt einer pro Begriff.
     *
     * @param  iterable<Term>  $terms
     * @return int Anzahl tatsächlich veröffentlichter Begriffe (unveränderte werden übersprungen)
     */
    public function publishMany(iterable $terms, ?User $approver): int
    {
        return DB::transaction(function () use ($terms, $approver): int {
            $events = [];
            foreach ($terms as $term) {
                $event = $this->markPublished($term, $approver);
                if ($event !== null) {
                    $events[] = $event;
                }
            }
            if ($events !== []) {
                $this->newRelease(null, 'published', $approver, $events);
            }

            return count($events);
        });
    }

    /** Neueste Revision freigeben und Ereignis für den Change-Feed schreiben; null, wenn schon veröffentlicht. */
    private function markPublished(Term $term, ?User $approver): ?TermPublicationEvent
    {
        /** @var TermRevision|null $latest */
        $latest = $term->revisions()->where('revision', $term->revision)->first();
        if ($latest === null) {
            throw new InvalidArgumentException('Begriff hat noch keine Revision.');
        }
        if ($term->published_revision_id === $latest->id) {
            return null;
        }

        $latest->published_at = now()->toImmutable();
        $latest->approved_by = $approver?->id;
        $latest->save();

        $term->published_revision_id = $latest->id;
        $term->save();

        return TermPublicationEvent::query()->create([
            'term_id' => $term->id, 'action' => 'published', 'revision' => $latest->revision,
        ]);
    }

    /**
     * Doppelte zusammenführen (#16): $target bleibt, $others werden zur Weiterleitung auf $target.
     *
     * - Kategorien, Herkunft, «Intern genutzt in» und offizielle Aliase werden vereinigt.
     * - Texte: je Feld der Wert des in $choices gewählten Begriffs (Feld → ID), sonst der von $target.
     * - Abkürzungen und Bezeichnungen der anderen, die nicht übernommen werden, kommen zu den Suchbegriffen.
     * - War einer der Begriffe veröffentlicht, wird das Ergebnis gleich veröffentlicht (ein Datenstand); die
     *   anderen erscheinen im Change-Feed als «merged» mit Verweis auf $target.
     *
     * @param  list<Term>  $others
     * @param  array<string, string>  $choices
     *
     * @throws InvalidArgumentException bei ungültiger Auswahl
     * @throws OfficialFieldLocked ohne Recht für offizielle Felder
     */
    public function merge(Term $target, array $others, array $choices, ?User $user, bool $canEditOfficial): Term
    {
        if ($others === []) {
            throw new InvalidArgumentException('Mindestens zwei Begriffe zum Zusammenführen wählen.');
        }
        $all = [$target->id => $target];
        foreach ($others as $other) {
            if ($other->id === $target->id || isset($all[$other->id])) {
                throw new InvalidArgumentException('Jeder Begriff darf nur einmal vorkommen.');
            }
            $all[$other->id] = $other;
        }
        foreach ($all as $term) {
            if ($term->merged_into_id !== null) {
                throw new InvalidArgumentException('«'.$this->title($term).'» wurde schon zusammengeführt.');
            }
        }

        $data = [];
        foreach (self::MERGE_CHOICE as $field) {
            $from = $all[$choices[$field] ?? $target->id] ?? throw new InvalidArgumentException("Auswahl für «{$field}» gehört nicht zu den gewählten Begriffen.");
            $value = $from->{$field};
            $data[$field] = $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value;
        }

        $categories = $sources = $usedIn = $official = $search = [];
        $own = null;
        foreach ($all as $term) {
            $term->loadMissing(['categories', 'sources', 'aliases']);
            foreach ($term->categories as $category) {
                $categories[] = (string) $category->key;
            }
            foreach ($term->sources as $source) {
                $sources[] = (string) $source->key;
            }
            $usedIn = [...$usedIn, ...($term->used_in ?? [])];
            $official = [...$official, ...$term->aliases->where('alias_type', 'official')->pluck('alias')->all()];
            $own ??= $term->aliases->firstWhere('alias_type', 'own')?->alias;
            $search = [...$search, ...(preg_split('/[;,]/', (string) $term->search_terms) ?: [])];
        }
        // Namen der anderen bleiben auffindbar
        $kept = array_map(fn ($v): string => mb_strtolower(trim((string) $v)), [
            $data['official_abbreviation'], $data['own_abbreviation'], $data['official_name'], $data['own_name'],
        ]);
        foreach ($all as $term) {
            foreach ([$term->official_abbreviation, $term->own_abbreviation, $term->official_name, $term->own_name] as $name) {
                $name = trim((string) $name);
                if ($name !== '' && ! in_array(mb_strtolower($name), $kept, true)) {
                    $search[] = $name;
                }
            }
        }
        $searchTerms = [];
        foreach ($search as $item) {
            $item = trim((string) $item);
            if ($item !== '' && ! in_array(mb_strtolower($item), array_map('mb_strtolower', $searchTerms), true)) {
                $searchTerms[] = $item;
            }
        }

        $data += [
            'categories' => array_values(array_unique($categories)),
            'sources' => array_values(array_unique($sources)),
            'used_in' => array_values(array_unique($usedIn)),
            'official_aliases' => array_values(array_unique($official)),
            'own_alias' => $own,
            'search_terms' => implode('; ', $searchTerms),
            'is_active' => array_filter($all, fn (Term $t): bool => $t->is_active) !== [],
        ];

        return DB::transaction(function () use ($target, $others, $all, $data, $user, $canEditOfficial): Term {
            $anyPublished = array_filter($all, fn (Term $t): bool => $t->published_revision_id !== null) !== [];
            $target = $this->save($target, $data, $user, $canEditOfficial, null, 'merged');

            $events = [];
            foreach ($others as $other) {
                $wasPublished = $other->published_revision_id !== null;
                $other->merged_into_id = $target->id;
                $other->merged_at = now()->toImmutable();
                $other->published_revision_id = null;
                $other->save();
                // Frühere Weiterleitungen auf diesen Begriff zeigen jetzt direkt auf $target
                Term::query()->where('merged_into_id', $other->id)->update(['merged_into_id' => $target->id]);
                if ($wasPublished) {
                    $events[] = TermPublicationEvent::query()->create([
                        'term_id' => $other->id, 'action' => 'merged', 'merged_into' => $target->id,
                    ]);
                }
            }
            if ($anyPublished) {
                $event = $this->markPublished($target, $user);
                if ($event !== null) {
                    $events[] = $event;
                }
            }
            if ($events !== []) {
                $this->newRelease($target->id, 'merged', $user, $events);
            }

            return $target->refresh();
        });
    }

    private function title(Term $term): string
    {
        return trim($term->displayAbbreviation().' – '.$term->displayName(), ' –');
    }

    /** Veröffentlichung zurückziehen: Begriff ist danach nicht mehr öffentlich (Tombstone im Change-Feed). */
    public function unpublish(Term $term, ?User $user): Term
    {
        if ($term->published_revision_id === null) {
            return $term;
        }

        return DB::transaction(function () use ($term, $user): Term {
            $term->published_revision_id = null;
            $term->save();
            $event = TermPublicationEvent::query()->create(['term_id' => $term->id, 'action' => 'unpublished']);
            $this->newRelease($term->id, 'unpublished', $user, [$event]);

            return $term->refresh();
        });
    }

    /**
     * Abbild eines Begriffs für Revisionen (und später API/Export). Listen als Schlüssel, sortiert.
     *
     * @return array<string, mixed>
     */
    public function snapshot(Term $term): array
    {
        $term->loadMissing(['categories', 'sources', 'aliases']);
        $aliases = fn (string $type): array => $term->aliases->where('alias_type', $type)->pluck('alias')->values()->all();
        /**
         * @param  iterable<Category|Source>  $items
         * @return list<string>
         */
        $keys = function (iterable $items): array {
            $keys = [];
            foreach ($items as $item) {
                $keys[] = (string) $item->key;
            }
            sort($keys);

            return $keys;
        };

        $snapshot = [];
        foreach (self::TEXT as $field) {
            $snapshot[$field] = $term->{$field};
        }

        return [
            'id' => $term->id,
            'slug' => $term->slug,
            ...$snapshot,
            'official_aliases' => $aliases('official'),
            'own_alias' => $aliases('own')[0] ?? null,
            'search_aliases' => $aliases('search'),
            'categories' => $keys($term->categories),
            'sources' => $keys($term->sources),
            'used_in' => $term->used_in ?? [],
            'lifecycle_status' => $term->lifecycle_status,
            'is_active' => $term->is_active,
            'valid_from' => $term->valid_from?->toDateString(),
            'valid_until' => $term->valid_until?->toDateString(),
        ];
    }

    /** Prüfsumme über alle veröffentlichten Revisionen (gleiche Daten → gleiche Prüfsumme). */
    public function checksum(): string
    {
        $rows = Term::query()->published()->with('publishedRevision')->orderBy('id')->get()
            ->map(fn (Term $t): array => [$t->id, $t->publishedRevision?->revision, $t->publishedRevision?->snapshot])
            ->all();

        return hash('sha256', (string) json_encode($rows, JSON_UNESCAPED_UNICODE));
    }

    // ---------- intern ----------

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, bool $creating): array
    {
        $values = [];
        foreach (self::TEXT as $field) {
            if (array_key_exists($field, $data)) {
                $value = trim((string) ($data[$field] ?? ''));
                $values[$field] = $value === '' ? null : $value;
            }
        }

        if (isset($values['short_description']) && mb_strlen($values['short_description']) > 160) {
            throw new InvalidArgumentException('Kurzbeschreibung höchstens 160 Zeichen.');
        }
        if (isset($values['url']) && preg_match('#^https?://\S+$#i', $values['url']) !== 1) {
            throw new InvalidArgumentException('URL muss mit http:// oder https:// beginnen.');
        }
        if (array_key_exists('lifecycle_status', $data)) {
            $status = (string) $data['lifecycle_status'];
            $status = array_search($status, Term::STATUS, true) ?: $status;   // «zu prüfen» → zu_pruefen
            if (! array_key_exists($status, Term::STATUS)) {
                throw new InvalidArgumentException("Status «{$status}» ist unbekannt.");
            }
            $values['lifecycle_status'] = $status;
        }
        if (array_key_exists('is_active', $data)) {
            $values['is_active'] = (bool) $data['is_active'];
        }
        foreach (['valid_from', 'valid_until'] as $field) {
            if (array_key_exists($field, $data)) {
                $date = trim((string) ($data[$field] ?? ''));
                if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
                    throw new InvalidArgumentException("Datum «{$date}» im Format JJJJ-MM-TT angeben.");
                }
                $values[$field] = $date === '' ? null : $date;
            }
        }
        if (array_key_exists('used_in', $data)) {
            $values['used_in'] = $this->list($data['used_in']);
        }
        if (array_key_exists('official_aliases', $data)) {
            $values['official_aliases'] = $this->list($data['official_aliases']);
        }
        if (array_key_exists('own_alias', $data)) {
            $alias = trim((string) ($data['own_alias'] ?? ''));
            $values['own_alias'] = $alias === '' ? null : $alias;
        }
        if (array_key_exists('categories', $data)) {
            $values['categories'] = $this->resolve(Category::class, $this->list($data['categories']), 'Kategorie');
        }
        if (array_key_exists('sources', $data)) {
            $values['sources'] = $this->resolve(Source::class, $this->list($data['sources']), 'Herkunft');
        }

        if ($creating) {
            $names = array_filter([
                $values['official_abbreviation'] ?? null, $values['own_abbreviation'] ?? null,
                $values['official_name'] ?? null, $values['own_name'] ?? null,
            ]);
            if ($names === []) {
                throw new InvalidArgumentException('Abkürzung oder Bezeichnung angeben.');
            }
        }

        return $values;
    }

    /**
     * Offizielle Felder: ohne Recht nur leere Felder setzen, nie ändern oder leeren.
     *
     * @param  array<string, mixed>  $values
     */
    private function guardOfficial(Term $term, array $values): void
    {
        foreach (['official_abbreviation', 'official_name'] as $field) {
            if (array_key_exists($field, $values) && $term->{$field} !== null && $values[$field] !== $term->{$field}) {
                throw new OfficialFieldLocked($field);
            }
        }
        if (array_key_exists('official_aliases', $values)) {
            $current = $term->aliases()->where('alias_type', 'official')->pluck('alias')->sort()->values()->all();
            /** @var list<string> $new */
            $new = $values['official_aliases'];
            sort($new);
            if ($current !== [] && $current !== $new) {
                throw new OfficialFieldLocked('official_aliases');
            }
        }
    }

    /**
     * Aliase neu schreiben: offizielle, eigener Alias und Suchbegriffe (aus search_terms, durch «;» oder «,»).
     *
     * @param  array<string, mixed>  $values
     */
    private function syncAliases(Term $term, array $values): void
    {
        $current = $term->aliases()->get();
        $official = $values['official_aliases'] ?? $current->where('alias_type', 'official')->pluck('alias')->all();
        $own = array_key_exists('own_alias', $values) ? $values['own_alias'] : $current->firstWhere('alias_type', 'own')?->alias;

        $term->aliases()->delete();
        foreach (array_unique((array) $official) as $alias) {
            $term->aliases()->create(['alias' => $alias, 'alias_type' => 'official', 'language' => 'de']);
        }
        if (is_string($own) && $own !== '') {
            $term->aliases()->create(['alias' => $own, 'alias_type' => 'own', 'language' => 'de']);
        }
        foreach ($this->list(preg_split('/[;,]/', (string) $term->search_terms) ?: []) as $alias) {
            $term->aliases()->create(['alias' => $alias, 'alias_type' => 'search', 'language' => 'de']);
        }
    }

    /**
     * Neuer Datenstand; die Ereignisse dieses Schritts werden ihm zugeordnet.
     *
     * @param  list<TermPublicationEvent>  $events
     */
    private function newRelease(?string $termId, string $action, ?User $user, array $events): void
    {
        $release = PublicationRelease::query()->create([
            'term_id' => $termId,
            'action' => $action,
            'checksum' => $this->checksum(),
            'term_count' => Term::query()->published()->count(),
            'published_by' => $user?->id,
        ]);
        TermPublicationEvent::query()->whereKey(array_map(fn (TermPublicationEvent $e): int => $e->id, $events))
            ->update(['release_id' => $release->id]);
    }

    /** Slug aus Bezeichnung bzw. Abkürzung, eindeutig mit -2, -3 … */
    private function uniqueSlug(Term $term): string
    {
        $base = Str::slug((string) ($term->official_name ?: $term->own_name ?: $term->displayAbbreviation()), '-', 'de') ?: 'begriff';
        $slug = $base;
        for ($i = 2; Term::query()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    /**
     * Liste aus Array oder Text mit «;» – getrimmt, ohne Leere und Doppelte.
     *
     * @return list<string>
     */
    private function list(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(';', (string) $value);
        $items = array_map(fn ($v): string => trim((string) $v), $items);

        return array_values(array_unique(array_filter($items, fn (string $v): bool => $v !== '')));
    }

    /**
     * Schlüssel, Kürzel oder Label einer Liste → IDs; Unbekanntes ist ein Fehler.
     *
     * @param  class-string<Category|Source>  $model
     * @param  list<string>  $refs
     * @return list<string>
     */
    private function resolve(string $model, array $refs, string $what): array
    {
        $ids = [];
        foreach ($refs as $ref) {
            $id = $model::query()
                ->where('key', $ref)
                ->orWhere('abbreviation', $ref)
                ->orWhere('label', $ref)
                ->orWhere('name', $ref)
                ->value('id');
            if ($id === null) {
                throw new InvalidArgumentException("{$what} «{$ref}» ist unbekannt.");
            }
            $ids[] = (string) $id;
        }

        return array_values(array_unique($ids));
    }
}
