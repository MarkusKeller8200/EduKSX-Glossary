<?php

namespace App\Http\Controllers\Api;

use App\Enums\Locale;
use App\Enums\TermStatus;
use App\Glossary\ApiPresenter;
use App\Http\Controllers\Controller;
use App\Models\Term;
use App\Models\TermPublicationEvent;
use App\Models\TermRevision;
use App\Search\SearchMisses;
use EduKSX\Core\Http\ETag;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Öffentliche Lese-API v1 (#25, docs/KONZEPT-API.md). Nur veröffentlichte Daten; jede Antwort mit ETag (aus
 * Datenstand und Anfrage) und 304 bei unverändertem Stand. Fehler als application/problem+json (bootstrap/app.php).
 */
class GlossaryApiController extends Controller
{
    private const MAX_PER_PAGE = 200;

    public function __construct(private readonly ApiPresenter $presenter) {}

    /** GET /api/v1/index – kompakter Index für Renderer und Browser-Erweiterung (#159, DEC-026). */
    public function index(Request $request): Response
    {
        $release = $this->presenter->release();

        return $this->cached($request, [
            'release' => $release['release'],
            'license' => $this->presenter->license(),
            'terms' => $this->presenter->index(),
        ], $release['release']);
    }

    /** GET /api/v1/terms – Suche, Filter, Pagination. */
    public function terms(Request $request): Response
    {
        $q = mb_strtolower(trim((string) $request->query('q', '')));
        $category = trim((string) $request->query('category', ''));
        $status = trim((string) $request->query('status', ''));
        $page = max(1, (int) $request->query('page', '1'));
        $perPage = min(self::MAX_PER_PAGE, max(1, (int) $request->query('per_page', '50')));
        if ($status !== '') {
            $status = TermStatus::fromAny($status) !== null ? TermStatus::normalize($status) : $status;   // auch Werte bis 0.11.0 (aktuell, zu_pruefen)
        }
        if ($status !== '' && ! array_key_exists($status, Term::STATUS)) {
            throw new BadRequestHttpException('Unbekannter Status «'.$status.'». Erlaubt: '.implode(', ', array_keys(Term::STATUS)).'.');
        }

        $lang = $this->language($request);
        $all = $lang === null ? $this->presenter->terms() : array_map(fn (array $t): array => ApiPresenter::localizeTerm($t, $lang), $this->presenter->terms());
        $terms = array_values(array_filter($all, function (array $t) use ($q, $category, $status): bool {
            if ($status !== '' && $t['status'] !== $status) {
                return false;
            }
            if ($category !== '') {
                $refs = [];
                foreach ($t['categories'] as $c) {
                    $refs[] = $c['key'];
                    $refs[] = $c['label'];
                }
                if (! in_array($category, $refs, true)) {
                    return false;
                }
            }
            if ($q === '') {
                return true;
            }
            $haystack = mb_strtolower(implode(' ', array_filter([
                $t['abbreviation'], $t['name'], $t['official_abbreviation'], $t['official_name'], $t['short_description'],
                $t['display_label'],
                implode(' ', $t['aliases']),
            ])));

            return str_contains($haystack, $q);
        }));

        $total = count($terms);
        // Suchen ohne Treffer (X4, #285): nur der Suchtext allein, erste Seite, ohne Filter
        if ($total === 0 && $q !== '' && $category === '' && $status === '' && $page === 1) {
            SearchMisses::record($q);
        }
        $lastPage = max(1, (int) ceil($total / $perPage));
        $data = array_slice($terms, ($page - 1) * $perPage, $perPage);
        $release = $this->presenter->release();
        $link = fn (int $p): string => $request->fullUrlWithQuery(['page' => $p, 'per_page' => $perPage]);

        return $this->cached($request, [
            'data' => $data,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'release' => $release['release'],
            ],
            'links' => [
                'first' => $link(1),
                'prev' => $page > 1 ? $link($page - 1) : null,
                'next' => $page < $lastPage ? $link($page + 1) : null,
                'last' => $link($lastPage),
            ],
        ], $release['release']);
    }

    /** GET /api/v1/terms/{idOrSlug} */
    public function term(Request $request, string $idOrSlug): Response
    {
        $term = $this->presenter->find($idOrSlug);
        if ($term === null) {
            // Zusammengeführt (#16): dauerhaft auf den verbleibenden Begriff weiterleiten
            $merged = Term::query()->whereNotNull('merged_into_id')
                ->idOrSlug($idOrSlug)->first();
            $target = $merged?->mergeTarget();
            if ($target !== null && $this->presenter->find($target->id) !== null) {
                return response('', 301, ['Location' => url("/api/v1/terms/{$target->id}")]);
            }
            throw new NotFoundHttpException("Begriff «{$idOrSlug}» ist nicht veröffentlicht oder existiert nicht.");
        }

        $lang = $this->language($request);

        return $this->cached($request, ['data' => $lang === null ? $term : ApiPresenter::localizeTerm($term, $lang)], $this->presenter->release()['release']);
    }

    /** GET /api/v1/categories */
    public function categories(Request $request): Response
    {
        return $this->cached($request, ['data' => $this->localizeList($request, $this->presenter->categoryList())], $this->presenter->release()['release']);
    }

    /** GET /api/v1/sources */
    public function sources(Request $request): Response
    {
        return $this->cached($request, ['data' => $this->localizeList($request, $this->presenter->sourceList())], $this->presenter->release()['release']);
    }

    /** GET /api/v1/releases/latest – aktueller Datenstand (für günstige periodische Prüfung). */
    public function latestRelease(Request $request): Response
    {
        $release = $this->presenter->release();

        return $this->cached($request, ['data' => $release], $release['release']);
    }

    /**
     * GET /api/v1/changes?since=<cursor>&limit=<n> – Veröffentlichungen, Rückzüge und Zusammenführungen seit dem Cursor, in fester
     * Reihenfolge; idempotent wiederholbar. Bei «published» enthält «term» den Begriff in genau dieser Revision.
     */
    public function changes(Request $request): Response
    {
        $since = max(0, (int) $request->query('since', '0'));
        $limit = min(1000, max(1, (int) $request->query('limit', '500')));

        $events = TermPublicationEvent::query()->where('id', '>', $since)->orderBy('id')->limit($limit + 1)->get();
        $hasMore = $events->count() > $limit;
        $events = $events->take($limit);

        $revisions = TermRevision::query()
            ->whereIn('term_id', $events->pluck('term_id')->unique()->all())
            ->whereIn('revision', $events->pluck('revision')->filter()->unique()->all())
            ->get()
            ->keyBy(fn (TermRevision $r): string => $r->term_id.'#'.$r->revision);

        $lang = $this->language($request);
        $data = $events->map(function (TermPublicationEvent $e) use ($revisions, $lang): array {
            $revision = $e->revision !== null ? $revisions->get($e->term_id.'#'.$e->revision) : null;

            return [
                'cursor' => $e->id,
                'action' => $e->action,
                'term_id' => $e->term_id,
                'revision' => $e->revision,
                'merged_into' => $e->merged_into_id,
                'release' => $e->release_id,
                'occurred_at' => $e->created_at->toIso8601ZuluString(),
                'term' => $revision instanceof TermRevision
                    ? ($lang === null ? $this->presenter->term($revision) : ApiPresenter::localizeTerm($this->presenter->term($revision), $lang))
                    : null,
            ];
        })->values()->all();

        $last = $events->last();
        $next = $last instanceof TermPublicationEvent ? $last->id : $since;
        $release = $this->presenter->release()['release'];

        return $this->cached($request, [
            'data' => $data,
            'meta' => ['since' => $since, 'next_cursor' => $next, 'has_more' => $hasMore, 'release' => $release],
        ], $release);
    }

    /** GET /api/v1/exports?format=json – Vollstand aller öffentlichen Begriffe (auch Quelle für GitHub Pages). */
    public function export(Request $request): Response
    {
        $format = (string) $request->query('format', 'json');
        if ($format !== 'json') {
            throw new BadRequestHttpException('Nur format=json wird unterstützt.');
        }
        // Cursor vor den Begriffen lesen: Ereignisse danach holt der Abnehmer über den Change-Feed (wiederholbar).
        $release = $this->presenter->release();

        return $this->cached($request, [
            'release' => $release['release'],
            'cursor' => $release['cursor'],
            'license' => $this->presenter->license(),
            'checksum' => $release['checksum'],
            'published_at' => $release['published_at'],
            'categories' => $this->localizeList($request, $this->presenter->categoryList()),
            'sources' => $this->localizeList($request, $this->presenter->sourceList()),
            'terms' => ($lang = $this->language($request)) === null
                ? $this->presenter->terms()
                : array_map(fn (array $t): array => ApiPresenter::localizeTerm($t, $lang), $this->presenter->terms()),
        ], $release['release']);
    }

    /**
     * Verlangte Sprache (?lang=de|fr|it|en, X1, #282); ohne Angabe null (Ausgabe wie bisher, deutsch).
     *
     * @throws BadRequestHttpException bei unbekannter Sprache
     */
    private function language(Request $request): ?string
    {
        $lang = strtolower(trim((string) $request->query('lang', '')));
        if ($lang === '') {
            return null;
        }
        if (Locale::tryFrom($lang) === null) {
            throw new BadRequestHttpException('Unbekannte Sprache «'.$lang.'». Erlaubt: '.implode(', ', Locale::values()).'.');
        }

        return $lang;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function localizeList(Request $request, array $items): array
    {
        $lang = $this->language($request);

        return $lang === null ? $items : array_map(fn (array $i): array => ApiPresenter::localizeItem($i, $lang), $items);
    }

    /**
     * Antwort mit Inhalts-ETag aus eduksx/core (API-Standard B5, #334); 304, wenn der Abnehmer diesen Stand schon hat.
     * Datenstand als EduKSX-Release, bis 1.0 zusätzlich X-Glossary-Release (Ausnahmeregister API-E7).
     *
     * @param  array<string, mixed>  $body
     */
    private function cached(Request $request, array $body, int $release): Response
    {
        return ETag::publicJson($request, $body, 60, [
            'EduKSX-Release' => (string) $release,
            'X-Glossary-Release' => (string) $release,
        ]);
    }
}
