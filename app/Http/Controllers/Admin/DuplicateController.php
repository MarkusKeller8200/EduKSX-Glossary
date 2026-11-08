<?php

namespace App\Http\Controllers\Admin;

use App\Glossary\Duplicates;
use App\Glossary\GlossaryService;
use App\Glossary\OfficialFieldLocked;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Source;
use App\Models\Term;
use DateTimeInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Doppelte Abkürzungen oder Begriffe (#16, wie im Intranet): Gruppen anzeigen, je Gruppe zusammenführen oder als
 * mehrdeutig belassen. Nichts geschieht automatisch.
 */
class DuplicateController extends Controller
{
    public function __construct(private readonly Duplicates $duplicates, private readonly GlossaryService $glossary) {}

    public function index(): Response
    {
        return Inertia::render('admin/duplicates', [
            'gruppen' => $this->duplicates->groups(),
            'belassen' => $this->duplicates->dismissedCount(),
        ]);
    }

    /** Gruppe als mehrdeutig belassen. */
    public function dismiss(Request $request): RedirectResponse
    {
        $data = $request->validate(['signatur' => ['required', 'string', 'size:64']]);
        try {
            $this->duplicates->dismiss($data['signatur'], $this->currentUserOrNull($request));
        } catch (InvalidArgumentException $e) {
            return back()->with('status', $e->getMessage());
        }

        return back()->with('status', 'Gruppe als mehrdeutig belassen – sie wird nicht mehr vorgeschlagen.');
    }

    /** Alle Entscheide «mehrdeutig belassen» aufheben. */
    public function reset(): RedirectResponse
    {
        $count = $this->duplicates->resetDismissed();

        return back()->with('status', "{$count} Entscheide aufgehoben – die Gruppen werden wieder vorgeschlagen.");
    }

    /** Auswahl für die Zusammenführung: welcher Begriff bleibt, welche Texte gelten. */
    public function create(Request $request): Response|RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:2', 'max:20'], 'ids.*' => ['uuid']]);
        $terms = Term::query()->current()->whereIn('id', $data['ids'])->with(['categories', 'sources', 'aliases'])->get();
        if ($terms->count() < 2) {
            return redirect('/admin/doppelte')->with('status', 'Mindestens zwei Begriffe zum Zusammenführen wählen.');
        }
        $terms = $terms->sortByDesc(fn (Term $t): int => $t->published_revision_id !== null ? 1 : 0)->values();

        $rows = $terms->map(fn (Term $t): array => [
            'id' => $t->id,
            'titel' => $t->title(),
            'veroeffentlicht' => $t->published_revision_id !== null,
            'werte' => $this->values($t),
            'kategorien' => $t->categories->map(fn (Category $c): string => $c->displayLabel())->values()->all(),
            'herkunft' => $t->sources->map(fn (Source $s): string => $s->displayLabel())->values()->all(),
            'aliase' => $t->aliases->pluck('alias')->unique()->values()->all(),
        ])->all();

        $labels = [];
        foreach (GlossaryService::MERGE_CHOICE as $field) {
            $labels[$field] = TermController::LABELS[$field];
        }

        return Inertia::render('admin/merge', [
            'begriffe' => $rows,
            'felder' => $labels,
            'offiziellErlaubt' => Gate::allows('edit-official'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ziel' => ['required', 'uuid'],
            'ids' => ['required', 'array', 'min:2', 'max:20'],
            'ids.*' => ['uuid'],
            'auswahl' => ['array'],
            'auswahl.*' => ['nullable', 'uuid'],
        ]);
        $ids = array_values(array_unique(array_map('strval', (array) $data['ids'])));
        $ziel = (string) $data['ziel'];
        if (count($ids) < 2 || ! in_array($ziel, $ids, true)) {
            throw ValidationException::withMessages(['ziel' => 'Mindestens zwei Begriffe wählen; der verbleibende muss dazugehören.']);
        }
        $terms = Term::query()->whereIn('id', $ids)->get()->keyBy('id');
        $target = $terms->get($ziel);
        if ($target === null || $terms->count() !== count($ids)) {
            throw ValidationException::withMessages(['ids' => 'Mindestens ein Begriff existiert nicht mehr.']);
        }
        $others = array_values($terms->except([$target->id])->all());

        // Ein veröffentlichter Begriff wird beim Zusammenführen neu veröffentlicht → Recht «publish» nötig (#36)
        if ($terms->contains(fn (Term $t): bool => $t->published_revision_id !== null) && ! Gate::allows('publish')) {
            throw ValidationException::withMessages(['form' => 'Veröffentlichte Begriffe zusammenführen braucht das Recht «Veröffentlichen».']);
        }

        $choices = [];
        foreach (GlossaryService::MERGE_CHOICE as $field) {
            $choice = $data['auswahl'][$field] ?? null;
            if (is_string($choice) && $choice !== '') {
                $choices[$field] = $choice;
            }
        }

        try {
            $term = $this->glossary->merge($target, $others, $choices, $this->currentUserOrNull($request), Gate::allows('edit-official'));
        } catch (OfficialFieldLocked $e) {
            throw ValidationException::withMessages(['form' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['form' => $e->getMessage()]);
        }

        $count = count($others);
        $conflicts = $this->glossary->lastMergeConflicts;

        return redirect("/admin/begriffe/{$term->id}")->with('status', ($count === 1 ? '1 Begriff' : "{$count} Begriffe")
            .' zusammengeführt (Revision '.$term->revision.').'
            .($term->published_revision_id !== null ? ' Der Stand ist veröffentlicht; alte Adressen leiten hierher weiter.' : '')
            .($conflicts > 0 ? " {$conflicts} persönliche Darstellungen widersprechen sich und bleiben beim alten Begriff." : ''));
    }

    // ---------- intern ----------

    /**
     * Anzeigewerte der wählbaren Felder.
     *
     * @return array<string, string>
     */
    private function values(Term $term): array
    {
        $values = [];
        foreach (GlossaryService::MERGE_CHOICE as $field) {
            $value = $term->{$field};
            $values[$field] = match (true) {
                $value instanceof DateTimeInterface => $value->format('Y-m-d'),
                $field === 'lifecycle_status' => Term::STATUS[(string) $value] ?? (string) $value,
                default => (string) $value,
            };
        }

        return $values;
    }
}
