<?php

namespace App\Http\Controllers\Admin;

use App\Candidates\CandidateService;
use App\Http\Controllers\Controller;
use App\Models\TermCandidate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Administration › Kandidaten (#480, DEC-042 M10): gemeldete Wörter sichten, als Entwurf anlegen oder verwerfen. */
class CandidateAdminController extends Controller
{
    public function __construct(private readonly CandidateService $candidates) {}

    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), ['new', 'accepted', 'rejected'], true) ? (string) $request->query('status') : 'new';
        $rows = TermCandidate::query()->with('term:id,official_abbreviation,official_name,code')->where('status', $status)
            ->orderByDesc('count')->orderByDesc('last_seen_at')->limit(500)->get()
            ->map(fn (TermCandidate $c): array => [
                'id' => $c->id,
                'wort' => $c->word,
                'art' => $c->kind,
                'kanal' => $c->channel,
                'anzahl' => $c->count,
                'begriff' => $c->term !== null ? ['id' => $c->term->id, 'titel' => $c->term->title()] : null,
                'adresse' => $c->last_url,
                'kommentare' => $c->comments ?? [],
                'angemeldet' => $c->reported_by_id !== null,
                'zuletzt' => $c->last_seen_at?->toIso8601String(),
                'grund' => $c->reason,
            ])->all();

        return Inertia::render('admin/candidates', [
            'status' => $status,
            'kandidaten' => $rows,
            'anzahl' => TermCandidate::query()->where('status', 'new')->count(),
        ]);
    }

    public function decide(Request $request, TermCandidate $candidate): RedirectResponse
    {
        $data = $request->validate([
            'entscheid' => ['required', 'in:accepted,rejected'],
            'grund' => ['nullable', 'string', 'max:500'],
        ]);
        try {
            $this->candidates->decide($candidate, (string) $data['entscheid'], $data['grund'] ?? null, $this->currentUser($request));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['entscheid' => $e->getMessage()]);
        }

        return back()->with('status', $data['entscheid'] === 'rejected' ? "«{$candidate->word}» verworfen – kommt nicht wieder." : "«{$candidate->word}» erledigt.");
    }
}
