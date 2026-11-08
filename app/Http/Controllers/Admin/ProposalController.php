<?php

namespace App\Http\Controllers\Admin;

use App\Glossary\OfficialFieldLocked;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Proposal;
use App\Models\ProposalEvent;
use App\Models\User;
use App\Proposals\ProposalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Moderation der Vorschläge (#113, Recht «moderate»): annehmen (Entwurf), ablehnen mit Begründung, Rückfrage. */
class ProposalController extends Controller
{
    public function __construct(private readonly ProposalService $proposals) {}

    public function index(Request $request): Response
    {
        $filter = $request->query('status') === 'alle' ? 'alle' : 'offen';
        $list = Proposal::query()->with('term')
            ->when($filter === 'offen', fn ($q) => $q->whereIn('status', Proposal::OPEN))
            ->orderByRaw("case when status = 'eingereicht' then 0 when status = 'rueckfrage' then 1 else 2 end")
            ->latest('id')->limit(500)->get()
            ->map(fn (Proposal $p): array => [
                'id' => $p->id,
                'art' => $p->kind === 'new' ? 'Neuer Begriff' : 'Korrektur',
                'titel' => $p->kind === 'new'
                    ? trim(implode(' – ', array_filter([(string) ($p->changes['official_abbreviation'] ?? ''), (string) ($p->changes['official_name'] ?? '')])))
                    : ($p->term?->title() ?? '(Begriff gelöscht)'),
                'felder' => array_map(fn (string $f): string => ProposalService::FIELDS[$f] ?? $f, array_keys($p->changes)),
                'von' => $p->author_label,
                'status' => $p->status,
                'status_label' => Proposal::STATUS[$p->status] ?? $p->status,
                'eingereicht' => $p->created_at?->toIso8601String(),
            ])->all();

        return Inertia::render('admin/proposals', ['vorschlaege' => $list, 'filter' => $filter]);
    }

    public function show(Proposal $proposal): Response
    {
        $categories = Category::query()->get()->mapWithKeys(fn (Category $c): array => [$c->key => $c->displayLabel()])->all();

        return Inertia::render('admin/proposal-show', [
            'vorschlag' => [
                'id' => $proposal->id,
                'art' => $proposal->kind,
                'begriff' => $proposal->term === null ? null : ['id' => $proposal->term->id, 'titel' => $proposal->term->title(), 'revision' => $proposal->term->revision],
                'basis' => $proposal->base_revision,
                'von' => $proposal->author_label,
                'servicekonto' => $proposal->service_account_id !== null,
                'begruendung' => $proposal->reason,
                'status' => $proposal->status,
                'status_label' => Proposal::STATUS[$proposal->status] ?? $proposal->status,
                'offen' => $proposal->isOpen(),
                'ergebnis' => $proposal->result_revision,
                'eingereicht' => $proposal->created_at?->toIso8601String(),
            ],
            'vergleich' => $this->proposals->compare($proposal),
            'kategorien' => $categories,
            'ereignisse' => $proposal->events->map(fn (ProposalEvent $e): array => [
                'typ' => $e->type,
                'am' => $e->created_at->toIso8601String(),
                'von' => $e->actor_label,
                'text' => $e->message,
                'details' => $e->details,
            ])->all(),
            'offiziellErlaubt' => Gate::allows('edit-official'),
        ]);
    }

    public function accept(Request $request, Proposal $proposal): RedirectResponse
    {
        $data = $request->validate(['felder' => ['array'], 'felder.*' => ['string'], 'notiz' => ['nullable', 'string', 'max:2000']]);
        try {
            $term = $this->proposals->accept($proposal, $this->actor($request), array_values(array_map('strval', (array) ($data['felder'] ?? []))),
                Gate::allows('edit-official'), $data['notiz'] ?? null);
        } catch (OfficialFieldLocked $e) {
            throw ValidationException::withMessages(['felder' => $e->getMessage().' Feld abwählen oder durch eine Person mit diesem Recht annehmen lassen.']);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['felder' => $e->getMessage()]);
        }

        return redirect("/admin/begriffe/{$term->id}")->with('status', "Vorschlag Nr. {$proposal->id} angenommen – Revision {$term->revision} ist ein Entwurf; veröffentlichen nach Prüfung.");
    }

    public function reject(Request $request, Proposal $proposal): RedirectResponse
    {
        $data = $request->validate(['begruendung' => ['required', 'string', 'max:2000']]);
        $this->guard(fn () => $this->proposals->reject($proposal, $this->actor($request), (string) $data['begruendung']), 'begruendung');

        return redirect('/admin/vorschlaege')->with('status', "Vorschlag Nr. {$proposal->id} abgelehnt.");
    }

    public function ask(Request $request, Proposal $proposal): RedirectResponse
    {
        $data = $request->validate(['frage' => ['required', 'string', 'max:2000']]);
        $this->guard(fn () => $this->proposals->ask($proposal, $this->actor($request), (string) $data['frage']), 'frage');

        return back()->with('status', 'Rückfrage gesendet.');
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

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
