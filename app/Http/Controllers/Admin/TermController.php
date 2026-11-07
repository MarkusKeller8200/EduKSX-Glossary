<?php

namespace App\Http\Controllers\Admin;

use App\Glossary\GlossaryService;
use App\Glossary\OfficialFieldLocked;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Source;
use App\Models\Term;
use App\Models\TermRevision;
use App\Models\User;
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
        'own_abbreviation' => 'Abkürzung eigene',
        'own_alias' => 'Alias eigene',
        'official_name' => 'Bezeichnung offiziell',
        'own_name' => 'Bezeichnung eigene',
        'short_description' => 'Kurzbeschreibung',
        'description' => 'Beschreibung',
        'url' => 'URL',
        'note' => 'Notiz',
        'categories' => 'Kategorien',
        'sources' => 'Herkunft',
        'used_in' => 'Intern genutzt in',
        'lifecycle_status' => 'Status',
        'search_terms' => 'Suchbegriffe',
        'valid_from' => 'Gültig ab',
        'valid_until' => 'Gültig bis',
        'is_active' => 'Aktiv',
    ];

    public function __construct(private readonly GlossaryService $glossary) {}

    public function index(): Response
    {
        $terms = Term::query()->current()->with(['categories', 'publishedRevision'])->get()
            ->sortBy(fn (Term $t): string => mb_strtolower($t->displayAbbreviation() ?: $t->displayName()))
            ->values()
            ->map(fn (Term $t): array => [
                'id' => $t->id,
                'abkuerzung' => $t->displayAbbreviation(),
                'bezeichnung' => $t->displayName(),
                'kategorien' => $t->categories->map(fn (Category $c): string => $c->displayLabel())->all(),
                'status' => Term::STATUS[$t->lifecycle_status] ?? $t->lifecycle_status,
                'aktiv' => $t->is_active,
                'revision' => $t->revision,
                'veroeffentlicht' => $t->publishedRevision?->revision,
            ])
            ->all();

        return Inertia::render('admin/terms', ['begriffe' => $terms]);
    }

    /** Detail: Arbeitsstand, veröffentlichter Stand, Verlauf mit geänderten Feldern. */
    public function show(Term $term): Response
    {
        $term->load(['revisions', 'publishedRevision']);
        $authors = User::query()->whereIn('id', $term->revisions->pluck('author_id')->filter()->unique()->all())->pluck('name', 'id');

        $previous = null;
        $history = [];
        foreach ($term->revisions as $revision) {
            $history[] = [
                'revision' => $revision->revision,
                'art' => $revision->change_type,
                'von' => $revision->author_label ?? ($revision->author_id !== null ? ($authors[$revision->author_id] ?? null) : null),
                'am' => $revision->created_at->toIso8601String(),
                'veroeffentlicht_am' => $revision->published_at?->toIso8601String(),
                'felder' => $this->changedFields($previous, $revision),
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
                    ? ['id' => $target->id, 'titel' => trim($target->displayAbbreviation().' – '.$target->displayName(), ' –')]
                    : null,
            ],
            'verlauf' => array_reverse($history),
            'labels' => self::LABELS,
            'listen' => $this->lists(),
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
            'begriff' => ['id' => $term->id, 'titel' => trim($term->displayAbbreviation().' – '.$term->displayName(), ' –')],
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

    public function publishOne(Request $request, Term $term): RedirectResponse
    {
        $before = $term->published_revision_id;
        $term = $this->glossary->publish($term, $this->user($request));

        return back()->with('status', $term->published_revision_id === $before ? 'War schon veröffentlicht.' : "Revision {$term->revision} veröffentlicht.");
    }

    public function unpublish(Request $request, Term $term): RedirectResponse
    {
        $this->glossary->unpublish($term, $this->user($request));

        return back()->with('status', 'Veröffentlichung zurückgezogen – der Begriff ist nicht mehr öffentlich.');
    }

    /** Ausgewählte Begriffe veröffentlichen (#23); unveränderte werden übersprungen. */
    public function publish(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:5000'], 'ids.*' => ['string']]);
        $terms = Term::query()->whereIn('id', $data['ids'])->get();
        $count = $this->glossary->publishMany($terms, $this->user($request));
        $skipped = $terms->count() - $count;

        return back()->with('status', "{$count} Begriffe veröffentlicht".($skipped > 0 ? ", {$skipped} waren schon auf dem veröffentlichten Stand." : '.'));
    }

    // ---------- intern ----------

    private function save(Request $request, ?Term $term): Term
    {
        $data = $request->validate([
            'official_abbreviation' => ['nullable', 'string', 'max:60'],
            'official_aliases' => ['nullable', 'string', 'max:500'],
            'own_abbreviation' => ['nullable', 'string', 'max:60'],
            'own_alias' => ['nullable', 'string', 'max:120'],
            'official_name' => ['nullable', 'string', 'max:255'],
            'own_name' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:20000'],
            'url' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:20000'],
            'categories' => ['array'],
            'categories.*' => ['string'],
            'sources' => ['array'],
            'sources.*' => ['string'],
            'used_in' => ['nullable', 'string', 'max:1000'],
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
            return $this->glossary->save($term, $data, $this->user($request), Gate::allows('edit-official'));
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
                'official_abbreviation' => '', 'official_aliases' => '', 'own_abbreviation' => '', 'own_alias' => '',
                'official_name' => '', 'own_name' => '', 'short_description' => '', 'description' => '', 'url' => '',
                'note' => '', 'categories' => [], 'sources' => [], 'used_in' => '', 'lifecycle_status' => 'aktuell',
                'search_terms' => '', 'valid_from' => '', 'valid_until' => '', 'is_active' => true,
            ];
        }
        $s = $this->glossary->snapshot($term);

        return [
            'official_abbreviation' => (string) $s['official_abbreviation'],
            'official_aliases' => implode('; ', (array) $s['official_aliases']),
            'own_abbreviation' => (string) $s['own_abbreviation'],
            'own_alias' => (string) $s['own_alias'],
            'official_name' => (string) $s['official_name'],
            'own_name' => (string) $s['own_name'],
            'short_description' => (string) $s['short_description'],
            'description' => (string) $s['description'],
            'url' => (string) $s['url'],
            'note' => (string) $s['note'],
            'categories' => (array) $s['categories'],
            'sources' => (array) $s['sources'],
            'used_in' => implode('; ', (array) $s['used_in']),
            'lifecycle_status' => (string) $s['lifecycle_status'],
            'search_terms' => (string) $s['search_terms'],
            'valid_from' => (string) $s['valid_from'],
            'valid_until' => (string) $s['valid_until'],
            'is_active' => (bool) $s['is_active'],
        ];
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

    private function user(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
