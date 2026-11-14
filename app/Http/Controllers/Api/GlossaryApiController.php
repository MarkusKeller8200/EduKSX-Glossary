<?php

namespace App\Http\Controllers\Api;

use App\Enums\TermStatus;
use App\Glossary\ApiPresenter;
use App\Glossary\TermListCursor;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ApiLocale;
use App\Models\Term;
use App\Models\TermPublicationEvent;
use App\Models\TermRevision;
use App\Search\SearchMisses;
use EduKSX\Core\Http\CursorPagination;
use EduKSX\Core\Http\ETag;
use EduKSX\Core\Http\ProblemException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
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

    /** Parameter von GET /api/v1/terms; andere ergeben 422 (#321). */
    private const TERMS_PARAMETERS = ['q', 'category', 'status', 'cursor', 'limit', 'with_total', 'page', 'per_page', 'locale', 'lang'];

    /**
     * GET /api/v1/terms – Suche und Filter mit Cursor (API-Standard A5, #321): `cursor`, `limit` (50, höchstens 200),
     * `meta.next_cursor`/`has_more`, Gesamtzahl nur mit `with_total=1`. Übergangsweise Seiten mit `page`/`per_page`.
     */
    public function terms(Request $request): Response
    {
        self::onlyParameters($request, self::TERMS_PARAMETERS);
        $q = mb_strtolower(trim((string) $request->query('q', '')));
        $category = trim((string) $request->query('category', ''));
        $status = trim((string) $request->query('status', ''));
        $withTotal = self::flag($request, 'with_total');
        $paged = $request->query->has('page') || $request->query->has('per_page');
        if ($status !== '') {
            $status = TermStatus::fromAny($status) !== null ? TermStatus::normalize($status) : $status;   // auch Werte bis 0.11.0 (aktuell, zu_pruefen)
        }
        if ($status !== '' && ! array_key_exists($status, Term::STATUS)) {
            throw self::invalid('status', 'Unbekannter Status «'.$status.'». Erlaubt: '.implode(', ', array_keys(Term::STATUS)).'.');
        }

        $lang = $this->language($request);
        // Filtern auf der (übersetzten) Ausgabe, Reihenfolge und Cursor auf den deutschen Grunddaten
        $matches = [];
        foreach ($this->presenter->terms() as $base) {
            $t = $lang === null ? $base : ApiPresenter::localizeTerm($base, $lang);
            if ($this->matches($t, $q, $category, $status)) {
                $matches[] = [$base, $t];
            }
        }

        $total = count($matches);
        $release = $this->presenter->release();
        if ($paged) {
            return $this->pagedTerms($request, $matches, $q, $category, $status, $release['release']);
        }
        // Suchen ohne Treffer (X4, #285): nur der Suchtext allein, erste Seite, ohne Filter
        if ($total === 0 && $q !== '' && $category === '' && $status === '' && ! $request->query->has('cursor')) {
            SearchMisses::record($q);
        }

        $limit = CursorPagination::limit($request);
        $output = [];
        foreach ($matches as [$base, $t]) {
            $output[$base['id']] = $t;
        }
        [$page, $next] = TermListCursor::page($request, array_column($matches, 0), $limit);

        return $this->cached($request, [
            'data' => array_map(fn (array $base): array => $output[$base['id']], $page),
            'meta' => ['limit' => $limit, 'next_cursor' => $next, 'has_more' => $next !== null]
                + ($withTotal ? ['total' => $total] : [])
                + ['release' => $release['release']],
            'links' => ['next' => $next !== null ? $request->fullUrlWithQuery(['cursor' => $next]) : null],
        ], $release['release']);
    }

    /**
     * Seiten mit page/per_page (übergangsweise, Vorschau): ungültige Werte jetzt 422 statt stiller Korrektur.
     *
     * @param  list<array{0: array<string, mixed>, 1: array<string, mixed>}>  $matches
     */
    private function pagedTerms(Request $request, array $matches, string $q, string $category, string $status, int $release): Response
    {
        $page = self::positiveInt($request, 'page', 1, PHP_INT_MAX);
        $perPage = self::positiveInt($request, 'per_page', 50, self::MAX_PER_PAGE);
        $total = count($matches);
        if ($total === 0 && $q !== '' && $category === '' && $status === '' && $page === 1) {
            SearchMisses::record($q);
        }
        $lastPage = max(1, (int) ceil($total / $perPage));
        $data = array_column(array_slice($matches, ($page - 1) * $perPage, $perPage), 1);
        $link = fn (int $p): string => $request->fullUrlWithQuery(['page' => $p, 'per_page' => $perPage]);

        return $this->cached($request, [
            'data' => $data,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'release' => $release,
            ],
            'links' => [
                'first' => $link(1),
                'prev' => $page > 1 ? $link($page - 1) : null,
                'next' => $page < $lastPage ? $link($page + 1) : null,
                'last' => $link($lastPage),
            ],
        ], $release);
    }

    /** @param  array<string, mixed>  $t */
    private function matches(array $t, string $q, string $category, string $status): bool
    {
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
    }

    /**
     * Nur bekannte Parameter (API-Standard A7, #321); sonst 422 mit dem Parameter in `errors`.
     *
     * @param  list<string>  $allowed
     */
    private static function onlyParameters(Request $request, array $allowed): void
    {
        $unknown = array_diff(array_keys($request->query()), $allowed);
        if ($unknown !== []) {
            $errors = [];
            foreach ($unknown as $name) {
                $errors[(string) $name] = ['Unbekannter Parameter. Erlaubt: '.implode(', ', $allowed).'.'];
            }
            throw new ProblemException(422, 'unknown_parameter', 'Unbekannter Parameter: '.implode(', ', $unknown).'.', [], false, $errors);
        }
    }

    private static function invalid(string $parameter, string $message): ProblemException
    {
        return ProblemException::common(422, 'validation_failed', $message, [], [$parameter => [$message]]);
    }

    private static function positiveInt(Request $request, string $name, int $default, int $max): int
    {
        $raw = $request->query($name);
        if ($raw === null || $raw === '') {
            return $default;
        }
        if (! is_string($raw) || preg_match('/^\d+$/', $raw) !== 1 || (int) $raw < 1 || (int) $raw > $max) {
            throw self::invalid($name, "{$name} muss eine ganze Zahl von 1 bis {$max} sein.");
        }

        return (int) $raw;
    }

    private static function flag(Request $request, string $name): bool
    {
        $raw = $request->query($name);
        if ($raw === null) {
            return false;
        }
        if (! in_array($raw, ['0', '1', 'true', 'false'], true)) {
            throw self::invalid($name, "{$name} erwartet 1 oder 0.");
        }

        return $raw === '1' || $raw === 'true';
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
            throw self::invalid('format', 'Nur format=json wird unterstützt.');
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

    /** Verlangte Übersetzungssprache aus ApiLocale (?locale=, ?lang=, Accept-Language); null = Deutsch wie bisher. */
    private function language(Request $request): ?string
    {
        $locale = $request->attributes->get(ApiLocale::ATTRIBUTE);

        return is_string($locale) ? $locale : null;
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
