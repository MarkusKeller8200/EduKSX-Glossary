<?php

namespace App\Curation;

use Illuminate\Support\Facades\DB;

/**
 * Text prüfen (Konzept Einsammeln E1, Prototyp): findet Abkürzungen und Kurzwörter in einem Text, vergleicht sie mit
 * dem Bestand (offizielle Abkürzung und Aliasse) und liefert unbekannte als Kandidaten mit Anzahl, Fundstelle und
 * vermuteter Langform. Der Text wird nur im Speicher verarbeitet und nirgends gespeichert.
 */
final class TextScanner
{
    public const MAX_LENGTH = 50000;

    /** Grossgeschriebene Wörter, die keine Abkürzungen sind (Überschriften in Grossbuchstaben) */
    private const STOP = [
        'UND', 'ODER', 'DER', 'DIE', 'DAS', 'DEN', 'DEM', 'DES', 'EIN', 'EINE', 'FÜR', 'MIT', 'VON', 'IM', 'IN', 'AM',
        'AN', 'AUF', 'ZU', 'ZUM', 'ZUR', 'BEI', 'AUS', 'NACH', 'ÜBER', 'BIS', 'ALS', 'WIE', 'NICHT', 'IST', 'SIND',
        'WIR', 'SIE', 'ES', 'ER', 'DU', 'ICH', 'BITTE', 'INFO', 'INFOS', 'HINWEIS', 'WICHTIG', 'NEU', 'OK', 'PS', 'CH',
        'ZH', 'PDF', 'URL', 'WWW', 'HTTP', 'HTTPS', 'MO', 'DI', 'MI', 'DO', 'FR', 'SA', 'SO',
    ];

    /**
     * @return array{known: list<array{abbreviation: string, count: int, term_id: string, title: string}>,
     *     candidates: list<array{abbreviation: string, count: int, long_form: string|null, context: string}>}
     */
    public static function scan(string $text): array
    {
        $text = mb_substr($text, 0, self::MAX_LENGTH);
        $found = [];
        // Kürzel: beginnt mit Grossbuchstaben, mindestens zwei Grossbuchstaben, 2–10 Zeichen (SL, VSA, LP21, DaZ)
        if (preg_match_all('/(?<![\p{L}\d])(\p{Lu}[\p{L}\d]{1,9})(?![\p{L}\d])/u', $text, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[1] as [$word, $offset]) {
                if (preg_match_all('/\p{Lu}/u', $word) < 2 || in_array(mb_strtoupper($word), self::STOP, true)) {
                    continue;
                }
                // ganz grossgeschriebene lange Wörter sind eher Überschriften als Abkürzungen
                if (mb_strlen($word) > 6 && mb_strtoupper($word) === $word && preg_match('/\d/', $word) !== 1) {
                    continue;
                }
                $found[$word] ??= ['count' => 0, 'offset' => $offset];
                $found[$word]['count']++;
            }
        }
        $longForms = self::longForms($text);
        $known = self::known();

        $result = ['known' => [], 'candidates' => []];
        foreach ($found as $word => $hit) {
            $key = mb_strtoupper($word);
            if (isset($known[$key])) {
                $result['known'][] = ['abbreviation' => $word, 'count' => $hit['count']] + $known[$key];

                continue;
            }
            $result['candidates'][] = [
                'abbreviation' => $word,
                'count' => $hit['count'],
                'long_form' => $longForms[$key] ?? null,
                'context' => self::context($text, (int) $hit['offset'], strlen($word)),
            ];
        }
        usort($result['known'], fn (array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcmp($a['abbreviation'], $b['abbreviation']));
        usort($result['candidates'], fn (array $a, array $b): int => ($b['long_form'] !== null) <=> ($a['long_form'] !== null)
            ?: $b['count'] <=> $a['count'] ?: strcmp($a['abbreviation'], $b['abbreviation']));

        return $result;
    }

    /**
     * Langformen aus Mustern «Langform (ABK)» und «ABK (Langform)».
     *
     * @return array<string, string> KÜRZEL → Langform
     */
    public static function longForms(string $text): array
    {
        $out = [];
        // «Schulische Heilpädagogik (SHP)»: so viele Wörter davor, wie das Kürzel Grossbuchstaben hat (höchstens 6)
        if (preg_match_all('/((?:[\p{L}\d][\p{L}\d\-]*\s+){1,8})\((\p{Lu}[\p{L}\d]{1,9})\)/u', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as [, $before, $abbr]) {
                $words = preg_split('/\s+/u', trim($before)) ?: [];
                $n = max(1, min(6, preg_match_all('/\p{Lu}/u', $abbr)));
                // kleinstes Fenster ab n Wörtern, dessen erstes Wort zum Kürzel passt («Deutsch als Zweitsprache»)
                $candidate = [];
                for ($k = $n; $k <= min(count($words), $n + 3); $k++) {
                    $window = array_slice($words, -$k);
                    while ($window !== [] && in_array(mb_strtoupper($window[0]), self::STOP, true)) {
                        array_shift($window);
                    }
                    if ($window !== [] && self::initialsMatch($window, $abbr)) {
                        $candidate = $window;
                        break;
                    }
                }
                if ($candidate !== [] && self::initialsMatch($candidate, $abbr)) {
                    $out[mb_strtoupper($abbr)] ??= implode(' ', $candidate);
                }
            }
        }
        // «SHP (Schulische Heilpädagogik)»
        if (preg_match_all('/(?<![\p{L}\d])(\p{Lu}[\p{L}\d]{1,9})\s*\(([^()]{4,80})\)/u', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as [, $abbr, $long]) {
                $words = preg_split('/\s+/u', trim($long)) ?: [];
                if (preg_match_all('/\p{Lu}/u', $abbr) >= 2 && self::initialsMatch($words, $abbr)) {
                    $out[mb_strtoupper($abbr)] ??= trim($long);
                }
            }
        }

        return $out;
    }

    /**
     * Erster Buchstabe des Kürzels passt zum ersten Wort, und die Grossbuchstaben kommen in den Wörtern vor.
     *
     * @param  list<string>  $words
     */
    private static function initialsMatch(array $words, string $abbr): bool
    {
        $joined = mb_strtoupper(implode(' ', $words));
        $letters = preg_split('//u', (string) preg_replace('/[^\p{Lu}]/u', '', $abbr), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($letters === [] || mb_substr($joined, 0, 1) !== $letters[0]) {
            return false;
        }
        $pos = 0;
        foreach ($letters as $letter) {
            $next = mb_strpos($joined, $letter, $pos);
            if ($next === false) {
                return false;
            }
            $pos = $next + 1;
        }

        return true;
    }

    /** @return array<string, array{term_id: string, title: string}> KÜRZEL → Begriff */
    private static function known(): array
    {
        $out = [];
        $titles = [];
        $terms = DB::table('terms')->whereNull('merged_into_id')
            ->select(['id', 'official_abbreviation', 'official_name'])->get();
        foreach ($terms as $row) {
            /** @var array{id: string, official_abbreviation: string|null, official_name: string} $t */
            $t = (array) $row;
            $abbreviation = (string) ($t['official_abbreviation'] ?? '');
            $title = trim(($abbreviation !== '' ? $abbreviation.' – ' : '').$t['official_name'], ' –');
            if ($abbreviation !== '') {
                $out[mb_strtoupper($abbreviation)] = ['term_id' => (string) $t['id'], 'title' => $title];
            }
            $titles[(string) $t['id']] = $title;
        }
        foreach (DB::table('term_aliases')->select(['term_id', 'alias'])->get() as $row) {
            $a = (array) $row;
            $key = mb_strtoupper((string) ($a['alias'] ?? ''));
            $termId = (string) ($a['term_id'] ?? '');
            if (! isset($out[$key]) && isset($titles[$termId])) {
                $out[$key] = ['term_id' => $termId, 'title' => $titles[$termId]];
            }
        }

        return $out;
    }

    private static function context(string $text, int $offset, int $length): string
    {
        $start = max(0, $offset - 60);
        $snippet = substr($text, $start, $length + 120);
        // am Rand abgeschnittene Zeichen entfernen
        $snippet = (string) preg_replace('/^[\x80-\xBF]+|[\xC0-\xFF][\x80-\xBF]*$/', '', $snippet);

        return ($start > 0 ? '…' : '').trim((string) preg_replace('/\s+/u', ' ', $snippet)).'…';
    }
}
