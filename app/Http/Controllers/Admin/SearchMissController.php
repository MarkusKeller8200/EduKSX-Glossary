<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Search\SearchMisses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Auswertung «Suchen ohne Treffer» für die Redaktion (X4, #285). */
class SearchMissController extends Controller
{
    public function index(): Response
    {
        $rows = DB::table('search_misses')->orderByDesc('count')->orderByDesc('last_seen_at')->limit(500)->get()
            ->map(fn (object $r): array => [
                'id' => (int) ((array) $r)['id'],
                'suche' => (string) ((array) $r)['query'],
                'anzahl' => (int) ((array) $r)['count'],
                'zuerst' => substr((string) ((array) $r)['first_seen_at'], 0, 10),
                'zuletzt' => substr((string) ((array) $r)['last_seen_at'], 0, 10),
            ])->all();

        return Inertia::render('admin/search-misses', ['eintraege' => $rows, 'tage' => SearchMisses::RETENTION_DAYS]);
    }

    /** Eintrag erledigt (Begriff angelegt oder kein Bedarf): entfernen. */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        DB::table('search_misses')->where('id', $id)->delete();

        return back()->with('status', 'Eintrag erledigt.');
    }
}
