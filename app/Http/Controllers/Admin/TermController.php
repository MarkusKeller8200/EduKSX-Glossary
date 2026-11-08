<?php

namespace App\Http\Controllers\Admin;

use App\Glossary\FourEyesRequired;
use App\Glossary\GlossaryService;
use App\Glossary\OfficialFieldLocked;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Source;
use App\Models\Term;
use App\Models\TermRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Redaktion (#14): Liste, Detail mit Verlauf, Formular, Veröffentlichen/Zurückziehen. Alle Änderungen laufen über
 * GlossaryService (Regeln, Revisionen). Bearbeiten direkt in der Tabelle (data-grid aus dem Intranet) folgt.
 */
class TermController extends Controller
{
    /** Formularfelder → Bezeichnung (wie Spalten im Excel/Intranet). */
    public const LABELS = [
        'official_abbreviation' => 'Abkürzung offiziell',
        'official_aliases' => 'Aliase offiziell',
        'alternative_aliases' => 'Alternative Schreibweisen',
        'official_name' => 'Bezeichnung offiziell',
        'short_description' => 'Kurzbeschreibung',
        'description' => 'Beschreibung',
        'url' => 'URL',
        'note' => 'Notiz',
        'categories' => 'Kategorien',
        'sources' => 'Herkunft',
        'lifecycle_status' => 'Status',
        'search_terms' => 'Suchbegriffe',
        'valid_from' => 'Gültig ab',
        'valid_until' => 'Gültig bis',
        'is_active' => 'Aktiv',
    ];

    public function __construct(private readonly GlossaryService $glossary) {}

    public function index(): Response
    {
        $terms = Term::query()->current()->with(['categories', 'sources', 'publishedRevision', 'latestRevision'])->get()
            ->sortBy(fn (Term $t): string => mb_strtolower($t->displayAbbreviation() ?: $t->displayName()))
            ->values()
            ->map(fn (Term $t): array => [
                'id' => $t->id,
                'abkuerzung' => $t->displayAbbreviation(),
                'bezeichnung' => $t->displayName(),
                'kurz' => (string) $t->short_description,
                'kategorien' => $t->categories->map(fn (Category $c): string => $c->displayLabel())->all(),
                'herkunft' => $t->sources->map(fn (Source $c): string => $c->displayLabel())->values()->all(),
                'status' => Term::STATUS[$t->lifecycle_status] ?? $t->lifecycle_status,
                'aktiv' => $t->is_active,
                'revision' => $t->revision,
                'veroeffentlicht' => $t->publishedRevision?->revision,
                'entwurf' => $t->hasUnpublishedChanges(),
                'angelegt' => $t->created_at?->toIso8601String(),
                'geaendert' => $t->updated_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('admin/terms', ['begriffe' => $terms, 'offiziellErlaubt' => Gate::allows('edit-official')]);
    }

    /** Weitere Felder im Journal, die nicht im Formular stehen. */
    public const JOURNAL_LABELS = ['slug' => 'Adresse (Slug)', 'search_aliases' => 'Suchbegriffe (Aliase)'];

    /** Detail: Arbeitsstand, veröffentlichter Stand, Verlauf (Journal) mit altem und neuem Wert je Feld. */
    public function show(Term $term): Response
    {
        $term->load(['revisions', 'publishedRevision', 'latestRevision']);
        $authors = User::query()->whereIn('id', $term->revisions->pluck('author_id')->filter()->unique()->all())->pluck('name', 'id');

        $previous = null;
        $history = [];
        foreach ($term->revisions as $revision) {
            $history[] = [
                'revision' => $revision->revision,
                'art' => $revision->change_type,
                'von' => $revision->author_label ?? ($revision->author_id !== null ? ($authors[$revision->author_id] ?? null) : null),
                'rolle' => $revision->actor_type,
                'quelle' => is_string($revision->context['via'] ?? null) ? $revision->context['via'] : null,
                'am' => $revision->created_at->toIso8601String(),
                'veroeffentlicht_am' => $revision->published_at?->toIso8601String(),
                'felder' => $this->changedFields($previous, $revision),
                'aenderungen' => $this->changes($revision),
            ];
            $previous = $revision;
        }

        return Inertia::render('admin/term-show', [
            'begriff' => $this->formData($term) + [
                'id' => $term->id,
                'slug' => $term->slug,
                'revision' => $term->revision,
                'veroeffentlicht' => $term->publishedRevision?->revision,
                'ungespeichert' => $term->hasUnpublishedChanges(),
                'zusammengefuehrt_in' => ($target = $term->mergeTarget()) !== null
                    ? ['id' => $target->id, 'titel' => $target->title()]
                    : null,
                'altwerte' => $term->legacyValues()->open()->count(),
                // Quellenprüfung (#69): redaktionell, ohne Revision
                'herkunft_pruefung' => $term->sources()->orderBy('sort_order')->get()->map(function (Source $src): array {
                    $pivot = $src->getRelationValue('pivot');
                    $get = fn (string $key): string => $pivot instanceof Pivot && is_scalar($pivot->getAttribute($key)) ? (string) $pivot->getAttribute($key) : '';

                    return [
                        'source_id' => $src->id,
                        'label' => $src->displayLabel(),
                        'name' => $src->name,
                        'retrieved_at' => substr($get('retrieved_at'), 0, 10),
                        'evidence' => $get('evidence'),
                        'note' => $get('note'),
                    ];
                })->all(),
            ],
            'verlauf' => array_reverse($history),
            'labels' => self::LABELS,
            'listen' => $this->lists(),
            'belegstatus' => SourceCheckController::EVIDENCE,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/term-form', [
            'begriff' => null,
            'werte' => $this->formData(null),
            'listen' => $this->lists(),
            'offiziellErlaubt' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $term = $this->save($request, null);

        return redirect("/admin/begriffe/{$term->id}")->with('status', 'Begriff angelegt (Entwurf).');
    }

    public function edit(Term $term): Response
    {
        return Inertia::render('admin/term-form', [
            'begriff' => ['id' => $term->id, 'titel' => $term->title()],
            'werte' => $this->formData($term),
            'listen' => $this->lists(),
            'offiziellErlaubt' => Gate::allows('edit-official'),
        ]);
    }

    public function update(Request $request, Term $term): RedirectResponse
    {
        $before = $term->revision;
        $term = $this->save($request, $term);

        return redirect("/admin/begriffe/{$term->id}")->with('status', $term->revision === $before
            ? 'Keine Änderungen.'
            : 'Gespeichert als Revision '.$term->revision.' (Entwurf – zum Veröffentlichen freigeben).');
    }

    /** Bearbeiten in der Zelle (#67): ein Feld, gleiche Regeln wie das Formular (Revision, offizielle Felder). */
    public function updateField(Request $request, Term $term): RedirectResponse
    {
        $data = $request->validate([
            'feld' => ['required', 'string', 'in:short_description,official_abbreviation,official_name'],
            'wert' => ['nullable', 'string', 'max:255'],
        ]);
        $field = (string) $data['feld'];
        $before = $term->revision;
        try {
            $term = $this->glossary->save($term, [$field => $data['wert'] ?? ''], $this->currentUserOrNull($request), Gate::allows('edit-official'), null, 'updated', ['via' => 'tabelle']);
        } catch (OfficialFieldLocked $e) {
            throw ValidationException::withMessages(['wert' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['wert' => $e->getMessage()]);
        }

        return back()->with('status', $term->revision === $before
            ? 'Keine Änderung.'
            : self::LABELS[$field].' gespeichert (Revision '.$term->revision.', Entwurf).');
    }

    /** Frühere Revision wiederherstellen, optional gleich veröffentlichen; «Entwurf verwerfen» (DEC-022). */
    public function restore(Request $request, Term $term): RedirectResponse
    {
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'veroeffentlichen' => ['boolean'],
        ]);
        $publish = $request->boolean('veroeffentlichen');
        if ($publish && ! Gate::allows('publish')) {
            throw ValidationException::withMessages(['form' => 'Veröffentlichen braucht das Recht «Veröffentlichen».']);
        }
        $before = $term->revision;
        $publishedBefore = $term->published_revision_id;
        try {
            $term = $this->glossary->restore($term, (int) $data['revision'], $this->currentUserOrNull($request), Gate::allows('edit-official'), $publish);
        } catch (OfficialFieldLocked $e) {
            throw ValidationException::withMessages(['form' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['form' => $e->getMessage()]);
        }

        if ($term->revision === $before) {
            return back()->with('status', "Revision {$data['revision']} entspricht schon dem Arbeitsstand"
                .($term->published_revision_id !== $publishedBefore ? " – Revision {$term->revision} veröffentlicht." : ' – nichts geändert.'));
        }

        return back()->with('status', "Revision {$data['revision']} als Revision {$term->revision} wiederhergestellt"
            .($publish ? ' und veröffentlicht.' : ($term->hasUnpublishedChanges() ? ' (Entwurf).' : ' – entspricht dem veröffentlichten Stand.')));
    }

    public function publishOne(Request $request, Term $term): RedirectResponse
    {
        $before = $term->published_revision_id;
        try {
            $term = $this->glossary->publish($term, $this->currentUserOrNull($request));
        } catch (FourEyesRequired $e) {
            return back()->withErrors(['veroeffentlichen' => $e->getMessage()])->with('status', $e->getMessage());
        }

        return back()->with('status', $term->published_revision_id === $before ? 'War schon veröffentlicht.' : "Revision {$term->revision} veröffentlicht.");
    }

    public function unpublish(Request $request, Term $term): RedirectResponse
    {
        $this->glossary->unpublish($term, $this->currentUserOrNull($request));

        return back()->with('status', 'Veröffentlichung zurückgezogen – der Begriff ist nicht mehr öffentlich.');
    }

    /** Ausgewählte Begriffe veröffentlichen (#23); unveränderte werden übersprungen. */
    public function publish(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:5000'], 'ids.*' => ['string']]);
        $terms = Term::query()->whereIn('id', $data['ids'])->get();
        $count = $this->glossary->publishMany($terms, $this->currentUserOrNull($request));
        $fourEyes = $this->glossary->lastFourEyesSkipped;
        $skipped = $terms->count() - $count - $fourEyes;

        return back()->with('status', "{$count} Begriffe veröffentlicht"
            .($skipped > 0 ? ", {$skipped} waren schon auf dem veröffentlichten Stand" : '')
            .($fourEyes > 0 ? ", {$fourEyes} mit eigener letzter Änderung übersprungen (Vier-Augen-Prinzip)" : '').'.');
    }

    // ---------- intern ----------

    private function save(Request $request, ?Term $term): Term
    {
        $data = $request->validate([
            'official_abbreviation' => ['nullable', 'string', 'max:60'],
            'official_aliases' => ['nullable', 'string', 'max:500'],
            'alternative_aliases' => ['nullable', 'string', 'max:1000'],
            'official_name' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:20000'],
            'url' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:20000'],
            'categories' => ['array'],
            'categories.*' => ['string'],
            'sources' => ['array'],
            'sources.*' => ['string'],
            'lifecycle_status' => ['required', 'string'],
            'search_terms' => ['nullable', 'string', 'max:2000'],
            'valid_from' => ['nullable', 'date_format:Y-m-d'],
            'valid_until' => ['nullable', 'date_format:Y-m-d'],
            'is_active' => ['boolean'],
        ]);
        $data['categories'] ??= [];
        $data['sources'] ??= [];
        $data['is_active'] = $request->boolean('is_active');

        try {
            return $this->glossary->save($term, $data, $this->currentUserOrNull($request), Gate::allows('edit-official'));
        } catch (OfficialFieldLocked $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['form' => $e->getMessage()]);
        }
    }

    /**
     * Werte für das Formular (Listen als Text mit «;», wie im Excel).
     *
     * @return array<string, mixed>
     */
    private function formData(?Term $term): array
    {
        if ($term === null) {
            return [
                'official_abbreviation' => '', 'official_aliases' => '', 'alternative_aliases' => '', 'official_name' => '',
                'short_description' => '', 'description' => '', 'url' => '', 'note' => '', 'categories' => [],
                'sources' => [], 'lifecycle_status' => 'aktuell',
                'search_terms' => '', 'valid_from' => '', 'valid_until' => '', 'is_active' => true,
            ];
        }
        $s = $this->glossary->snapshot($term);

        return [
            'official_abbreviation' => (string) $s['official_abbreviation'],
            'official_aliases' => implode('; ', (array) $s['official_aliases']),
            'alternative_aliases' => implode('; ', (array) $s['alternative_aliases']),
            'official_name' => (string) $s['official_name'],
            'short_description' => (string) $s['short_description'],
            'description' => (string) $s['description'],
            'url' => (string) $s['url'],
            'note' => (string) $s['note'],
            'categories' => (array) $s['categories'],
            'sources' => (array) $s['sources'],
            'lifecycle_status' => (string) $s['lifecycle_status'],
            'search_terms' => (string) $s['search_terms'],
            'valid_from' => (string) $s['valid_from'],
            'valid_until' => (string) $s['valid_until'],
            'is_active' => (bool) $s['is_active'],
        ];
    }

    /**
     * Journal einer Revision: Feld (Bezeichnung) → alter und neuer Wert als Text. Ältere Revisionen ohne
     * change_set liefern nichts (dann gilt «felder»).
     *
     * @return list<array{feld: string, alt: string, neu: string}>
     */
    private function changes(TermRevision $revision): array
    {
        $labels = self::LABELS + self::JOURNAL_LABELS;
        $text = fn (mixed $v): string => match (true) {
            $v === null => '',
            is_bool($v) => $v ? 'ja' : 'nein',
            is_array($v) => implode('; ', array_map(fn ($i): string => is_scalar($i) ? (string) $i : (string) json_encode($i), $v)),
            is_scalar($v) => (string) $v,
            default => (string) json_encode($v),
        };
        $out = [];
        foreach ($revision->change_set ?? [] as $field => $change) {
            $out[] = ['feld' => $labels[$field] ?? $field, 'alt' => $text($change['old'] ?? null), 'neu' => $text($change['new'] ?? null)];
        }

        return $out;
    }

    /**
     * Felder, die sich gegenüber der vorherigen Revision geändert haben (Bezeichnungen).
     *
     * @return list<string>
     */
    private function changedFields(?TermRevision $previous, TermRevision $current): array
    {
        if ($previous === null) {
            return [];
        }
        $fields = [];
        foreach (self::LABELS as $key => $label) {
            if (($previous->snapshot[$key] ?? null) != ($current->snapshot[$key] ?? null)) {
                $fields[] = $label;
            }
        }

        return $fields;
    }

    /**
     * Auswahllisten für das Formular.
     *
     * @return array{kategorien: list<array{key: string, label: string}>, herkunft: list<array{key: string, label: string}>, status: array<string, string>}
     */
    private function lists(): array
    {
        return [
            'kategorien' => $this->options(Category::query()->orderBy('sort_order')->orderBy('name')->get()),
            'herkunft' => $this->options(Source::query()->orderBy('sort_order')->orderBy('name')->get()),
            'status' => Term::STATUS,
        ];
    }

    /**
     * @param  iterable<Category|Source>  $items
     * @return list<array{key: string, label: string}>
     */
    private function options(iterable $items): array
    {
        $options = [];
        foreach ($items as $item) {
            $label = $item->displayLabel();
            $options[] = ['key' => $item->key, 'label' => $label.($item->name !== $label ? ' – '.$item->name : '')];
        }

        return $options;
    }
}
