<?php

namespace App\Glossary;

use App\Models\Category;
use App\Models\PublicationRelease;
use App\Models\Source;
use App\Models\Term;
use App\Models\TermLegacyValue;
use App\Models\TermPublicationEvent;
use App\Models\TermRevision;
use App\Models\TermUserOverride;
use App\Models\User;
use App\Support\Settings;
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
 * - Nur gemeinsame Fachdaten (DEC-020): keine eigenen Bezeichnungen und kein «Intern genutzt in»; persönliche
 *   Darstellung in TermUserOverride, Altwerte in TermLegacyValue. Jede Revision mit feldgenauem Journal.
 */
final class GlossaryService
{
    /** Felder, deren Wert beim Zusammenführen aus einem der Begriffe gewählt wird (#16). */
    public const MERGE_CHOICE = [
        'official_abbreviation', 'official_name', 'short_description', 'description', 'url', 'note', 'lifecycle_status',
        'valid_from', 'valid_until',
    ];

    /** Einfache Textfelder des Begriffs. */
    private const TEXT = [
        'official_abbreviation', 'official_name', 'short_description', 'description', 'note', 'url', 'search_terms',
    ];

    /** Persönliche Darstellungen beim letzten merge(), die wegen Widerspruch am alten Begriff geblieben sind. */
    public int $lastMergeConflicts = 0;

    /** Begriffe, die publishMany() wegen des Vier-Augen-Prinzips übersprungen hat (#106). */
    public int $lastFourEyesSkipped = 0;

    /**
     * Begriff anlegen (ohne $term) oder ändern. Ergebnis ist immer ein Entwurf; veröffentlicht wird mit publish().
     *
     * Felder in $data (alle optional beim Ändern): die Textfelder, lifecycle_status, is_active, valid_from,
     * valid_until, official_aliases und alternative_aliases (Listen), categories und sources (Listen von
     * Schlüsseln). Beim Anlegen optional «id» (z. B. aus dem Intranet, #13). $context kommt ins Journal (z. B.
     * ['via' => 'excel']).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $context
     *
     * @throws InvalidArgumentException bei ungültigen Werten
     * @throws OfficialFieldLocked ohne Recht für offizielle Felder
     */
    public function save(?Term $term, array $data, ?User $author, bool $canEditOfficial, ?string $authorLabel = null, string $changeType = 'updated', array $context = []): Term
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

        return DB::transaction(function () use ($term, $values, $creating, $data, $author, $authorLabel, $changeType, $context): Term {
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

            if ($creating || array_intersect_key($values, array_flip(['official_aliases', 'alternative_aliases', 'search_terms'])) !== []) {
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
                'snapshot' => $after,
                'change_set' => Journal::diff($before, $after),
                'author_id' => $author?->id,
                'author_label' => $authorLabel ?? Journal::actorLabel($author),
                'actor_type' => Journal::actorType($author),
                'context' => $context === [] ? null : $context,
            ]);

            return $term->refresh();
        });
    }

    /**
     * Aktuellen Arbeitsstand veröffentlichen (Freigabe). Ohne Änderung seit der letzten Freigabe: nichts tun.
     *
     * @throws FourEyesRequired mit Vier-Augen-Prinzip, wenn $approver die letzte Änderung gemacht hat
     */
    public function publish(Term $term, ?User $approver): Term
    {
        $this->guardFourEyes($term, $approver);

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
        $this->lastFourEyesSkipped = 0;

        return DB::transaction(function () use ($terms, $approver): int {
            $events = [];
            foreach ($terms as $term) {
                try {
                    $this->guardFourEyes($term, $approver);
                } catch (FourEyesRequired) {
                    $this->lastFourEyesSkipped++;

                    continue;
                }
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

    /**
     * Vier-Augen-Prinzip (DEC-022, Einstellung «publish.four_eyes», #106): freigeben nicht durch die Person der letzten
     * Änderung. Gilt nicht für den Serverprozess ($approver null), Zusammenführen und Wiederherstellen.
     */
    private function guardFourEyes(Term $term, ?User $approver): void
    {
        if ($approver === null || ! Settings::bool('publish.four_eyes')) {
            return;
        }
        $author = $term->revisions()->where('revision', $term->revision)->value('author_id');
        if ($author !== null && $author === $approver->id && $term->hasUnpublishedChanges()) {
            throw new FourEyesRequired($term);
        }
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
        // Gleicher Inhalt wie der öffentliche Stand (z. B. nach «Entwurf verwerfen»): nichts veröffentlichen (DEC-022)
        $published = $term->published_revision_id !== null ? TermRevision::query()->find($term->published_revision_id) : null;
        if ($published !== null && $published->snapshot == $latest->snapshot) {
            return null;
        }

        $latest->published_at = now()->toImmutable();
        $latest->approved_by = $approver?->id;
        $latest->save();

        $term->published_revision_id = $latest->id;
        $term->save();

        return TermPublicationEvent::query()->create([
            'term_id' => $term->id, 'action' => 'published', 'revision' => $latest->revision,
        ] + Journal::eventActor($approver));
    }

    /**
     * Doppelte zusammenführen (#16): $target bleibt, $others werden zur Weiterleitung auf $target.
     *
     * - Kategorien, Herkunft, offizielle und alternative Aliase werden vereinigt.
     * - Texte: je Feld der Wert des in $choices gewählten Begriffs (Feld → ID), sonst der von $target.
     * - Abkürzungen und Bezeichnungen der anderen, die nicht übernommen werden, kommen zu den Suchbegriffen.
     * - War einer der Begriffe veröffentlicht, wird das Ergebnis gleich veröffentlicht (ein Datenstand); die
     *   anderen erscheinen im Change-Feed als «merged» mit Verweis auf $target.
     * - Persönliche Darstellungen gehen auf $target über; hat dieselbe Person dort schon eine, werden nur leere
     *   Felder ergänzt und bei Widerspruch bleibt die Anpassung am alten Begriff (nichts wird still überschrieben).
     *   Altwerte gehen auf $target über.
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
                throw new InvalidArgumentException('«'.$term->title().'» wurde schon zusammengeführt.');
            }
        }

        $data = [];
        foreach (self::MERGE_CHOICE as $field) {
            $from = $all[$choices[$field] ?? $target->id] ?? throw new InvalidArgumentException("Auswahl für «{$field}» gehört nicht zu den gewählten Begriffen.");
            $value = $from->{$field};
            $data[$field] = $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value;
        }

        $categories = $sources = $official = $alternative = $search = [];
        foreach ($all as $term) {
            $term->loadMissing(['categories', 'sources', 'aliases']);
            foreach ($term->categories as $category) {
                $categories[] = (string) $category->key;
            }
            foreach ($term->sources as $source) {
                $sources[] = (string) $source->key;
            }
            $official = [...$official, ...$term->aliases->where('alias_type', 'official')->pluck('alias')->all()];
            $alternative = [...$alternative, ...$term->aliases->where('alias_type', 'alternative')->pluck('alias')->all()];
            $search = [...$search, ...(preg_split('/[;,]/', (string) $term->search_terms) ?: [])];
        }
        // Namen der anderen bleiben auffindbar
        $kept = array_map(fn ($v): string => mb_strtolower(trim((string) $v)), [
            $data['official_abbreviation'], $data['official_name'],
        ]);
        foreach ($all as $term) {
            foreach ([$term->official_abbreviation, $term->official_name] as $name) {
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
            'official_aliases' => array_values(array_unique($official)),
            'alternative_aliases' => array_values(array_unique($alternative)),
            'search_terms' => implode('; ', $searchTerms),
            'is_active' => array_filter($all, fn (Term $t): bool => $t->is_active) !== [],
        ];

        $context = ['merged_from' => array_map(fn (Term $t): string => $t->id, $others), 'choices' => $choices];

        return DB::transaction(function () use ($target, $others, $all, $data, $user, $canEditOfficial, $context): Term {
            $anyPublished = array_filter($all, fn (Term $t): bool => $t->published_revision_id !== null) !== [];
            $target = $this->save($target, $data, $user, $canEditOfficial, null, 'merged', $context);
            $conflicts = 0;

            $events = [];
            foreach ($others as $other) {
                $wasPublished = $other->published_revision_id !== null;
                $other->merged_into_id = $target->id;
                $other->merged_at = now()->toImmutable();
                $other->published_revision_id = null;
                $other->save();
                // Frühere Weiterleitungen auf diesen Begriff zeigen jetzt direkt auf $target
                Term::query()->where('merged_into_id', $other->id)->update(['merged_into_id' => $target->id]);
                $conflicts += $this->moveOverrides($other, $target);
                $this->moveLegacyValues($other, $target);
                if ($wasPublished) {
                    $events[] = TermPublicationEvent::query()->create([
                        'term_id' => $other->id, 'action' => 'merged', 'merged_into' => $target->id,
                    ] + Journal::eventActor($user));
                }
            }
            $this->lastMergeConflicts = $conflicts;
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

    /** Persönliche Darstellungen von $from auf $to übertragen; Rückgabe: Anzahl mit Widerspruch (bleiben bei $from). */
    private function moveOverrides(Term $from, Term $to): int
    {
        $conflicts = 0;
        foreach (TermUserOverride::query()->where('term_id', $from->id)->get() as $override) {
            $existing = TermUserOverride::query()->where('term_id', $to->id)->where('user_id', $override->user_id)->first();
            if ($existing === null) {
                $override->term_id = $to->id;
                $override->save();

                continue;
            }
            $conflict = false;
            foreach (TermUserOverride::FIELDS as $field) {
                $mine = $override->getAttribute($field);
                if ($mine === null || $mine === '' || $mine === []) {
                    continue;
                }
                $theirs = $existing->getAttribute($field);
                if ($theirs === null || $theirs === '' || $theirs === []) {
                    $existing->setAttribute($field, $mine);
                } elseif ($theirs !== $mine) {
                    $conflict = true;
                }
            }
            // ergänzte Werte ohne gleiche Freigabe brauchen eine neue Freigabe (#148)
            if ($existing->isDirty(TermUserOverride::FIELDS) && $existing->approved_organization_id !== $override->approved_organization_id) {
                $existing->forceFill(['approved_organization_id' => null, 'approved_by' => null, 'approved_at' => null]);
            }
            $existing->save();
            if ($conflict) {
                $conflicts++;
            } else {
                $override->delete();
            }
        }

        return $conflicts;
    }

    /** Altwerte von $from auf $to übertragen (gleiche Werte nur einmal). */
    private function moveLegacyValues(Term $from, Term $to): void
    {
        foreach (TermLegacyValue::query()->where('term_id', $from->id)->get() as $value) {
            $exists = TermLegacyValue::query()->where('term_id', $to->id)->where('field', $value->field)->where('value', $value->value)->exists();
            if ($exists) {
                $value->delete();
            } else {
                $value->term_id = $to->id;
                $value->save();
            }
        }
    }

    /**
     * Frühere Revision wiederherstellen (DEC-022): neue Revision mit deren gemeinsamen Feldern (nie Altfelder), auf
     * Wunsch gleich veröffentlicht. Die alte Revision bleibt unverändert. Ohne Abweichung entsteht keine Revision.
     *
     * @throws InvalidArgumentException unbekannte Revision, fehlende Kategorie/Herkunft, zusammengeführter Begriff
     * @throws OfficialFieldLocked ohne Recht für offizielle Felder
     */
    public function restore(Term $term, int $revision, ?User $user, bool $canEditOfficial, bool $publish = false): Term
    {
        /** @var TermRevision|null $source */
        $source = $term->revisions()->where('revision', $revision)->first();
        if ($source === null) {
            throw new InvalidArgumentException("Revision {$revision} gibt es nicht.");
        }
        $s = $source->snapshot;
        $data = [];
        foreach (self::TEXT as $field) {
            $data[$field] = $s[$field] ?? null;
        }
        $data += [
            'official_aliases' => (array) ($s['official_aliases'] ?? []),
            'alternative_aliases' => (array) ($s['alternative_aliases'] ?? []),
            'categories' => (array) ($s['categories'] ?? []),
            'sources' => (array) ($s['sources'] ?? []),
            'lifecycle_status' => (string) ($s['lifecycle_status'] ?? 'aktuell'),
            'is_active' => (bool) ($s['is_active'] ?? true),
            'valid_from' => $s['valid_from'] ?? null,
            'valid_until' => $s['valid_until'] ?? null,
        ];

        return DB::transaction(function () use ($term, $data, $user, $canEditOfficial, $publish, $revision): Term {
            $term = $this->save($term, $data, $user, $canEditOfficial, null, 'restored', ['restored_from' => $revision]);

            return $publish ? $this->publish($term, $user) : $term;
        });
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
            $event = TermPublicationEvent::query()->create(['term_id' => $term->id, 'action' => 'unpublished'] + Journal::eventActor($user));
            $this->newRelease($term->id, 'unpublished', $user, [$event]);

            return $term->refresh();
        });
    }

    /**
     * Abbild eines Begriffs für Revisionen (Grundlage von API/Export). Nur gemeinsame Felder; Listen sortiert, damit
     * die Reihenfolge allein keine Änderung ergibt.
     *
     * @return array<string, mixed>
     */
    public function snapshot(Term $term): array
    {
        $term->loadMissing(['categories', 'sources', 'aliases']);
        $aliases = function (string $type) use ($term): array {
            $list = $term->aliases->where('alias_type', $type)->pluck('alias')->map(fn ($a): string => (string) $a)->values()->all();
            sort($list);

            return $list;
        };
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
            'alternative_aliases' => $aliases('alternative'),
            'search_aliases' => $aliases('search'),
            'categories' => $keys($term->categories),
            'sources' => $keys($term->sources),
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
        foreach (['official_aliases', 'alternative_aliases'] as $field) {
            if (array_key_exists($field, $data)) {
                $values[$field] = $this->list($data[$field]);
                foreach ($values[$field] as $alias) {
                    if (mb_strlen($alias) > 120) {
                        throw new InvalidArgumentException("Schreibweise «{$alias}» höchstens 120 Zeichen.");
                    }
                }
            }
        }
        if (array_key_exists('categories', $data)) {
            $values['categories'] = $this->resolve(Category::class, $this->list($data['categories']), 'Kategorie');
        }
        if (array_key_exists('sources', $data)) {
            $values['sources'] = $this->resolve(Source::class, $this->list($data['sources']), 'Herkunft');
        }

        if ($creating) {
            $names = array_filter([$values['official_abbreviation'] ?? null, $values['official_name'] ?? null]);
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
     * Aliase neu schreiben: offizielle, alternative Schreibweisen und Suchbegriffe (aus search_terms, «;» oder «,»).
     *
     * @param  array<string, mixed>  $values
     */
    private function syncAliases(Term $term, array $values): void
    {
        $current = $term->aliases()->get();
        $official = $values['official_aliases'] ?? $current->where('alias_type', 'official')->pluck('alias')->all();
        $alternative = $values['alternative_aliases'] ?? $current->where('alias_type', 'alternative')->pluck('alias')->all();

        $term->aliases()->delete();
        foreach (array_unique((array) $official) as $alias) {
            $term->aliases()->create(['alias' => $alias, 'alias_type' => 'official', 'language' => 'de']);
        }
        foreach (array_unique((array) $alternative) as $alias) {
            $term->aliases()->create(['alias' => $alias, 'alias_type' => 'alternative', 'language' => 'de']);
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
        $base = Str::slug((string) ($term->official_name ?: $term->official_abbreviation), '-', 'de') ?: 'begriff';
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
