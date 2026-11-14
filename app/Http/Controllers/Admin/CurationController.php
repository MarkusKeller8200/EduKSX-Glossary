<?php

namespace App\Http\Controllers\Admin;

use App\Curation\QualityCheck;
use App\Curation\TextScanner;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Einsammeln und Prüfen (Konzept docs/KONZEPT-EINSAMMELN.md, Stufe 1, Prototyp): Text prüfen (E1), Gesucht aus den
 * Suchen ohne Treffer (E3), Prüfbedarf nach Regeln (P1). Recht «edit-terms». Der geprüfte Text wird nicht gespeichert.
 */
class CurationController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->page(null, '');
    }

    public function scan(Request $request): Response
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:'.TextScanner::MAX_LENGTH],
        ]);

        return $this->page(TextScanner::scan((string) $data['text']), (string) $data['text']);
    }

    /** @param  array<string, mixed>|null  $result */
    private function page(?array $result, string $text): Response
    {
        return Inertia::render('admin/curation', [
            'text' => $text,
            'ergebnis' => $result,
            'gesucht' => DB::table('search_misses')->orderByDesc('count')->orderByDesc('last_seen_at')->limit(30)
                ->get(['query', 'count', 'last_seen_at'])
                ->map(function (object $row): array {
                    $m = (array) $row;

                    return ['suche' => (string) $m['query'], 'anzahl' => (int) $m['count'], 'zuletzt' => substr((string) $m['last_seen_at'], 0, 10)];
                })
                ->all(),
            'pruefbedarf' => QualityCheck::run(),
            'maxLaenge' => TextScanner::MAX_LENGTH,
        ]);
    }
}
