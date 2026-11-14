<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Proposal;
use App\Models\ProposalEvent;
use App\Models\Term;
use App\Proposals\ProposalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Vorschläge der Community (#113, DEC-024): neuer Begriff oder Korrektur einreichen (Recht «propose», ab Benutzer),
 * eigene Vorschläge mit Stand, Rückfragen beantworten, zurückziehen. Nur eigene Vorschläge sichtbar.
 */
class ProposalController extends Controller
{
    public function __construct(private readonly ProposalService $proposals) {}

    public function create(Request $request): Response
    {
        $term = $this->published($request->query('begriff'));
        $snapshot = $term?->publishedRevision->snapshot ?? [];
        $werte = [];
        // Listenfelder immer als Liste: ältere Abbilder (vor DEC-020) kennen z. B. alternative_aliases nicht (#133)
        foreach (array_keys(ProposalService::FIELDS) as $field) {
            $v = $snapshot[$field] ?? null;
            $werte[$field] = in_array($field, ProposalService::LIST_FIELDS, true)
                ? (is_array($v) ? array_values(array_map('strval', $v)) : [])
                : (is_scalar($v) ? (string) $v : '');
        }

        return Inertia::render('proposals/create', [
            'begriff' => $term === null ? null : [
                'slug' => $term->slug,
                'titel' => Term::titleFrom($snapshot),
                'revision' => $term->publishedRevision?->revision,
            ],
            'werte' => $werte,
            'felder' => ProposalService::FIELDS,
            'kategorien' => Category::query()->orderBy('sort_order')->get()
                ->map(fn (Category $c): array => ['key' => $c->key, 'label' => $c->displayLabel()])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'begriff' => ['nullable', 'string', 'max:200'],
            'werte' => ['array'],
            'begruendung' => ['nullable', 'string', 'max:2000'],
        ]);
        $term = null;
        if (($data['begriff'] ?? '') !== '') {
            $term = $this->published($data['begriff']);
            abort_if($term === null, 404);
        }
        try {
            $proposal = $this->proposals->submit($term, (array) ($data['werte'] ?? []), $data['begruendung'] ?? null, $this->currentUser($request));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['werte' => $e->getMessage()]);
        }

        // Mitglieder einer Organisation: zuerst Bestätigung durch einen Organisations-Admin (#148)
        $next = $proposal->status === Proposal::ORG_REVIEW
            ? 'ein Organisations-Admin Ihrer Organisation bestätigt ihn, danach prüft ihn die Redaktion.'
            : 'die Redaktion prüft ihn.';

        return redirect("/vorschlaege/{$proposal->id}")->with('status', "Danke! Vorschlag Nr. {$proposal->id} ist eingereicht; {$next}");
    }

    public function index(Request $request): Response
    {
        $list = Proposal::query()->where('author_id', $this->currentUser($request)->id)->with('term.publishedRevision')->latest('id')->get()
            ->map(fn (Proposal $p): array => $this->summary($p))->all();

        return Inertia::render('proposals/index', ['vorschlaege' => $list]);
    }

    public function show(Request $request, Proposal $proposal): Response
    {
        abort_unless($proposal->author_id === $this->currentUser($request)->id, 404);
        $categories = Category::query()->get()->mapWithKeys(fn (Category $c): array => [$c->key => $c->displayLabel()])->all();

        return Inertia::render('proposals/show', [
            'vorschlag' => $this->summary($proposal) + [
                'aenderungen' => array_map(fn (array $r): array => [
                    'label' => $r['label'],
                    'vorschlag' => $r['feld'] === 'categories' && is_array($r['vorschlag'])
                        ? array_map(fn (mixed $k): string => (string) ($categories[(string) $k] ?? $k), $r['vorschlag'])
                        : $r['vorschlag'],
                ], $this->proposals->compare($proposal)),
                'begruendung' => $proposal->reason,
                'ereignisse' => $proposal->events->map(fn (ProposalEvent $e): array => [
                    'typ' => $e->event_type,
                    'am' => $e->created_at->toIso8601String(),
                    // Moderation ohne Namen (nicht nötig für die einreichende Person)
                    'von' => $e->is_by_author ? 'Sie' : 'Redaktion',
                    'text' => $e->message,
                ])->all(),
            ],
        ]);
    }

    public function answer(Request $request, Proposal $proposal): RedirectResponse
    {
        $data = $request->validate(['antwort' => ['required', 'string', 'max:2000']]);
        $this->guard(fn () => $this->proposals->answer($proposal, $this->currentUser($request), (string) $data['antwort']), 'antwort');

        return back()->with('status', 'Antwort gesendet.');
    }

    public function withdraw(Request $request, Proposal $proposal): RedirectResponse
    {
        $this->guard(fn () => $this->proposals->withdraw($proposal, $this->currentUser($request)), 'vorschlag');

        return back()->with('status', 'Vorschlag zurückgezogen.');
    }

    /** @return array<string, mixed> */
    private function summary(Proposal $p): array
    {
        return [
            'id' => $p->id,
            'art' => $p->proposal_type === 'new' ? 'Neuer Begriff' : 'Korrektur',
            // nur der veröffentlichte Stand – nie Entwürfe der Redaktion
            'begriff' => $p->term?->publishedRevision === null ? null : [
                'slug' => $p->term->slug, 'titel' => Term::titleFrom($p->term->publishedRevision->snapshot),
            ],
            'titel' => $p->proposal_type === 'new'
                ? Term::titleFrom($p->changes)
                : null,
            'status' => $p->status,
            'status_label' => Proposal::STATUS[$p->status] ?? $p->status,
            'eingereicht' => $p->created_at?->toIso8601String(),
            'offen' => $p->isOpen(),
        ];
    }

    private function published(mixed $slug): ?Term
    {
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return Term::query()->published()->where('slug', $slug)->whereNull('merged_into_id')->with('publishedRevision')->first();
    }

    /** @param  callable(): void  $action */
    private function guard(callable $action, string $field): void
    {
        try {
            $action();
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }
    }
}
