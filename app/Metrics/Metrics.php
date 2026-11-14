<?php

namespace App\Metrics;

use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Metriken im Textformat von Prometheus (#192). Zähler (Anfragen, Antwortzeiten, Anmeldungen) im Cache, nur wenn
 * GLOSSARY_METRICS_TOKEN gesetzt ist; Bestandszahlen werden beim Abruf gezählt. Keine Personendaten.
 */
final class Metrics
{
    /** Grenzen der Antwortzeit in Sekunden (Histogramm) */
    public const BUCKETS = [0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0];

    private const PREFIX = 'metrics:';

    public static function enabled(): bool
    {
        return Settings::string('metrics.token') !== '';
    }

    /** Eine Anfrage zählen: Gruppe (web, api, admin), Statusklasse und Dauer. */
    public static function request(string $group, int $status, float $seconds): void
    {
        $class = intdiv($status, 100).'xx';
        self::increment("http_requests_total|{$group}|{$class}");
        foreach (self::BUCKETS as $le) {
            if ($seconds <= $le) {
                self::increment("http_duration_bucket|{$group}|{$le}");
            }
        }
        self::increment("http_duration_count|{$group}");
        self::increment("http_duration_sum_ms|{$group}", (int) round($seconds * 1000));
    }

    /** Anmeldung zählen: ok oder failed */
    public static function login(string $result): void
    {
        self::increment("logins_total|{$result}");
    }

    public static function increment(string $key, int $by = 1): void
    {
        if (! self::enabled()) {
            return;
        }
        try {
            $store = Cache::store();
            $store->add(self::PREFIX.$key, 0, now()->addYear());
            $store->increment(self::PREFIX.$key, $by);
            $keys = (array) $store->get(self::PREFIX.'keys', []);
            if (! in_array($key, $keys, true)) {
                $keys[] = $key;
                $store->forever(self::PREFIX.'keys', $keys);
            }
        } catch (Throwable) {
            // Metriken dürfen nie eine Anfrage scheitern lassen
        }
    }

    /** Ausgabe im Textformat 0.0.4 */
    public static function render(): string
    {
        $out = [];
        /** @param  list<array{0: array<string, string>, 1: int|float}>  $samples */
        $line = function (string $name, string $help, string $type, array $samples) use (&$out): void {
            $out[] = "# HELP glossary_{$name} {$help}";
            $out[] = "# TYPE glossary_{$name} {$type}";
            foreach ($samples as [$labels, $value]) {
                $l = $labels === [] ? '' : '{'.implode(',', array_map(fn ($k, $v) => $k.'="'.addcslashes((string) $v, "\"\\\n").'"', array_keys($labels), $labels)).'}';
                $out[] = "glossary_{$name}{$l} {$value}";
            }
        };

        $line('build_info', 'Version der Anwendung', 'gauge', [[['version' => (string) config('glossary.version')], 1]]);
        $line('terms_published', 'Veröffentlichte Begriffe', 'gauge', [[[], DB::table('terms')->whereNotNull('published_revision_id')->whereNull('merged_into_id')->count()]]);
        $line('proposals_open', 'Offene Vorschläge', 'gauge', [[[], DB::table('proposals')->whereIn('status', ['organization_review', 'submitted', 'query'])->count()]]);
        $line('users_active', 'Aktive Konten', 'gauge', [[[], DB::table('users')->where('status', 'active')->count()]]);
        $line('queue_jobs', 'Aufträge in der Warteschlange', 'gauge', [[['state' => 'pending'], DB::table('jobs')->count()], [['state' => 'failed'], DB::table('failed_jobs')->count()]]);
        $line('search_misses', 'Suchbegriffe ohne Treffer (90 Tage)', 'gauge', [[[], DB::table('search_misses')->count()]]);

        $counters = self::counters();
        $requests = [];
        $buckets = [];
        $counts = [];
        $sums = [];
        $logins = [];
        foreach ($counters as $key => $value) {
            $p = explode('|', $key);
            match ($p[0]) {
                'http_requests_total' => $requests[] = [['group' => $p[1], 'status' => $p[2]], $value],
                'http_duration_bucket' => $buckets[$p[1]][$p[2]] = $value,
                'http_duration_count' => $counts[$p[1]] = $value,
                'http_duration_sum_ms' => $sums[$p[1]] = $value,
                'logins_total' => $logins[] = [['result' => $p[1]], $value],
                default => null,
            };
        }
        $line('http_requests_total', 'HTTP-Anfragen nach Bereich und Statusklasse', 'counter', $requests);
        $histogram = [];
        foreach ($counts as $group => $count) {
            foreach (self::BUCKETS as $le) {
                $histogram[] = [['group' => $group, 'le' => (string) $le], $buckets[$group][(string) $le] ?? 0];
            }
            $histogram[] = [['group' => $group, 'le' => '+Inf'], $count];
        }
        $out[] = '# HELP glossary_http_request_duration_seconds Antwortzeit der Anwendung';
        $out[] = '# TYPE glossary_http_request_duration_seconds histogram';
        foreach ($histogram as [$labels, $value]) {
            $out[] = 'glossary_http_request_duration_seconds_bucket{group="'.$labels['group'].'",le="'.$labels['le'].'"} '.$value;
        }
        foreach ($counts as $group => $count) {
            $out[] = 'glossary_http_request_duration_seconds_sum{group="'.$group.'"} '.round(($sums[$group] ?? 0) / 1000, 3);
            $out[] = 'glossary_http_request_duration_seconds_count{group="'.$group.'"} '.$count;
        }
        $line('logins_total', 'Anmeldungen nach Ergebnis', 'counter', $logins);

        return implode("\n", $out)."\n";
    }

    /** @return array<string, int> */
    private static function counters(): array
    {
        $store = Cache::store();
        $out = [];
        foreach ((array) $store->get(self::PREFIX.'keys', []) as $key) {
            $out[(string) $key] = (int) $store->get(self::PREFIX.$key, 0);
        }
        ksort($out);

        return $out;
    }
}
