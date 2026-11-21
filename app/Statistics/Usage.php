<?php

namespace App\Statistics;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Statistik ohne Cookies (#495): zählt Aufrufe als Tageszähler je Kanal, Art und Gegenstand. Gespeichert wird nur der
 * Zähler – keine IP-Adresse, keine Browser-Kennung, keine Wiedererkennung. Auf dem Gerät wird nichts gespeichert oder
 * gelesen; deshalb kein Eintrag im Cookie-Hinweis (Art. 45c FMG), nur in der Datenschutzerklärung (Art. 19 DSG).
 */
final class Usage
{
    public const CHANNELS = ['web' => 'Website', 'extension' => 'Browser-Erweiterung', 'embed' => 'Einbindung', 'api' => 'API', 'mcp' => 'MCP'];

    public const METRICS = [
        'term_view' => 'Begriff aufgerufen', 'term_open' => 'Begriff geöffnet', 'index' => 'Index geladen',
        'search_miss' => 'Suche ohne Treffer', 'export' => 'Export', 'report' => 'Meldung', 'endpoint' => 'Abruf',
        'tool' => 'Werkzeug',
    ];

    /** Zähler danach so viele Monate behalten (aggregiert, ohne Personenbezug) */
    public const RETENTION_MONTHS = 25;

    /** Kein Zählen für Bots und Vorab-Abrufe; auf der Website zusätzlich keine Scripts (curl, Python …) */
    private const BOTS = '/bot|crawl|spider|slurp|preview|monitor|uptime|headless|lighthouse/i';

    private const SCRIPTS = '/curl|wget|python|go-http|java\/|guzzle|okhttp|node-fetch|axios/i';

    public static function hit(string $channel, string $metric, string $subject = '', ?Request $request = null): void
    {
        if (! isset(self::CHANNELS[$channel], self::METRICS[$metric]) || ! (bool) config('glossary.usage_enabled', true)) {
            return;
        }
        if ($request !== null && self::ignore($request, in_array($channel, ['api', 'mcp'], true))) {
            return;
        }
        $subject = mb_substr($subject, 0, 120);
        try {
            DB::statement(
                'insert into usage_counts (day, channel, metric, subject, count) values (?, ?, ?, ?, 1)
                 on conflict (day, channel, metric, subject) do update set count = usage_counts.count + 1',
                [now()->toDateString(), $channel, $metric, $subject],
            );
        } catch (Throwable $e) {
            report($e);   // Statistik darf nie eine Antwort verhindern
        }
    }

    /** Bots und Vorab-Abrufe nicht zählen (nur zur Prüfung gelesen, nie gespeichert). */
    public static function ignore(Request $request, bool $machines = false): bool
    {
        $purpose = strtolower((string) ($request->headers->get('Sec-Purpose') ?? $request->headers->get('Purpose') ?? ''));
        if (str_contains($purpose, 'prefetch') || $request->headers->has('X-Inertia-Partial-Data')) {
            return true;
        }

        $agent = (string) $request->userAgent();

        return preg_match(self::BOTS, $agent) === 1 || (! $machines && preg_match(self::SCRIPTS, $agent) === 1);
    }

    /** Kanal aus «?via=» bei Links aus Erweiterung und Einbindung; sonst Website. */
    public static function viaChannel(Request $request): string
    {
        return match ((string) $request->query('via', '')) {
            'erweiterung' => 'extension',
            'einbindung' => 'embed',
            default => 'web',
        };
    }

    /** Domain einer Herkunft (Origin bzw. Adresse), ohne Pfad; leer, wenn unbekannt. */
    public static function domain(?string $url): string
    {
        $host = (string) parse_url((string) $url, PHP_URL_HOST);

        return mb_substr(preg_replace('/^www\./', '', mb_strtolower($host)) ?? '', 0, 120);
    }

    /** Zähler älter als die Frist löschen (glossary:loeschfristen). */
    public static function prune(): int
    {
        return DB::table('usage_counts')->where('day', '<', now()->subMonths(self::RETENTION_MONTHS)->toDateString())->delete();
    }
}
