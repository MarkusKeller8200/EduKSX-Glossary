<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Term;
use App\Statistics\Usage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Administration › Statistik (#495): Tageszähler ohne Personenbezug, getrennt nach Kanal. */
class StatisticsController extends Controller
{
    private const PERIODS = [7, 30, 90, 365];

    public function __invoke(Request $request): Response
    {
        $days = in_array((int) $request->query('tage'), self::PERIODS, true) ? (int) $request->query('tage') : 30;
        $channel = array_key_exists((string) $request->query('kanal'), Usage::CHANNELS) ? (string) $request->query('kanal') : '';
        $from = now()->subDays($days - 1)->toDateString();
        $base = fn () => DB::table('usage_counts')->where('day', '>=', $from)->when($channel !== '', fn ($q) => $q->where('channel', $channel));

        $totals = [];
        foreach ($base()->select(['channel', 'metric', DB::raw('sum(count) as n')])->groupBy('channel', 'metric')->get() as $row) {
            $totals[] = ['kanal' => (string) $row->channel, 'art' => (string) $row->metric, 'anzahl' => (int) $row->n];
        }

        // Verlauf: Begriffe (aufgerufen bzw. geöffnet) je Tag und Kanal
        $series = [];
        foreach ($base()->whereIn('metric', ['term_view', 'term_open'])->select(['day', 'channel', DB::raw('sum(count) as n')])->groupBy('day', 'channel')->orderBy('day')->get() as $row) {
            $series[] = ['tag' => substr((string) $row->day, 0, 10), 'kanal' => (string) $row->channel, 'anzahl' => (int) $row->n];
        }

        // Top-Begriffe je Kanal (Gegenstand = Code des Permalinks)
        /** @var array<string, array{code: string, web: int, extension: int, embed: int, summe: int}> $top */
        $top = [];
        foreach ($base()->whereIn('metric', ['term_view', 'term_open'])->select(['subject', 'channel', DB::raw('sum(count) as n')])->groupBy('subject', 'channel')->get() as $row) {
            $code = (string) $row->subject;
            $top[$code] ??= ['code' => $code, 'web' => 0, 'extension' => 0, 'embed' => 0, 'summe' => 0];
            $channel = (string) $row->channel;
            if (in_array($channel, ['web', 'extension', 'embed'], true)) {
                $top[$code][$channel] += (int) $row->n;
            }
            $top[$code]['summe'] += (int) $row->n;
        }
        $top = array_values($top);
        usort($top, fn (array $a, array $b): int => $b['summe'] <=> $a['summe']);
        $top = array_slice($top, 0, 50);
        $titles = Term::query()->whereIn('code', array_column($top, 'code'))->get()->mapWithKeys(fn (Term $t): array => [(string) $t->code => ['titel' => $t->title(), 'id' => $t->id]]);
        foreach ($top as &$t) {
            $t += $titles[$t['code']] ?? ['titel' => $t['code'], 'id' => null];
        }
        unset($t);

        $list = fn (string $ch, string $metric): array => DB::table('usage_counts')->where('day', '>=', $from)->where('channel', $ch)->where('metric', $metric)
            ->select(['subject', DB::raw('sum(count) as n')])->groupBy('subject')->orderByDesc('n')->limit(30)->get()
            ->map(fn (object $r): array => ['name' => (string) $r->subject, 'anzahl' => (int) $r->n])->all();

        // Erweiterung: Index-Abrufe pro Tag ≈ aktive Installationen (einmal täglich je Browser)
        $installs = DB::table('usage_counts')->where('day', '>=', $from)->where('channel', 'extension')->where('metric', 'index')
            ->select(['day', DB::raw('sum(count) as n')])->groupBy('day')->orderBy('day')->get()
            ->map(fn (object $r): array => ['tag' => substr((string) $r->day, 0, 10), 'anzahl' => (int) $r->n])->all();

        return Inertia::render('admin/statistics', [
            'tage' => $days,
            'kanal' => $channel,
            'zeitraeume' => self::PERIODS,
            'kanaele' => Usage::CHANNELS,
            'arten' => Usage::METRICS,
            'summen' => $totals,
            'verlauf' => $series,
            'begriffe' => $top,
            'einbindungen' => $list('embed', 'index'),
            'werkzeuge' => $list('mcp', 'tool'),
            'endpunkte' => $list('api', 'endpoint'),
            'installationen' => $installs,
        ]);
    }
}
