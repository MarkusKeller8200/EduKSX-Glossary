<?php

namespace App\Http\Controllers\Admin;

use App\Glossary\GlossaryService;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Term;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Liste der Begriffe in der Administration (Arbeitsstand). Bearbeiten in der Tabelle folgt mit #14. */
class TermController extends Controller
{
    public function index(): Response
    {
        $terms = Term::query()->with(['categories', 'publishedRevision'])->get()
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

    /** Ausgewählte Begriffe veröffentlichen (#23); unveränderte werden übersprungen. */
    public function publish(Request $request, GlossaryService $glossary): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:5000'], 'ids.*' => ['string']]);
        $user = $request->user();
        $terms = Term::query()->whereIn('id', $data['ids'])->get();
        $count = $glossary->publishMany($terms, $user instanceof User ? $user : null);
        $skipped = $terms->count() - $count;

        return back()->with('status', "{$count} Begriffe veröffentlicht".($skipped > 0 ? ", {$skipped} waren schon auf dem veröffentlichten Stand." : '.'));
    }
}
