<?php

namespace App\Glossary;

use EduKSX\Core\Http\ProblemException;
use Illuminate\Http\Request;

/**
 * Cursor für die Begriffsliste (API-Standard A5, #321). Die Liste kommt aus dem Zwischenspeicher je Datenstand und ist
 * fest sortiert (Anzeigebezeichnung, dann ID); der Cursor merkt sich den letzten Begriff (Bezeichnung + ID) und die
 * Filter. So entstehen keine Lücken oder Doppelte, auch wenn zwischen zwei Seiten veröffentlicht wird. Undurchsichtig
 * für Abnehmer (base64url); passt er nicht zu den Filtern: 422 `invalid_cursor`.
 */
final class TermListCursor
{
    /** Sortierschlüssel eines Begriffs – gleich wie die Sortierung in ApiPresenter::buildTerms(). */
    public static function key(array $term): string
    {
        return (string) ($term['abbreviation'] ?: $term['name']);
    }

    public static function compare(array $a, array $b): int
    {
        return strcasecmp(self::key($a), self::key($b)) ?: strcmp((string) $a['id'], (string) $b['id']);
    }

    /**
     * Eine Seite ab dem Cursor.
     *
     * @param  list<array<string, mixed>>  $terms  sortiert nach compare()
     * @return array{0: list<array<string, mixed>>, 1: ?string} Seite und nächster Cursor
     */
    public static function page(Request $request, array $terms, int $limit): array
    {
        $filters = self::fingerprint($request);
        $start = 0;
        $token = $request->query('cursor');
        if (is_string($token) && $token !== '') {
            $after = self::decode($token, $filters);
            $start = count($terms);
            foreach ($terms as $i => $term) {
                if (self::compare($term, $after) > 0) {
                    $start = $i;
                    break;
                }
            }
        } elseif ($token !== null && $token !== '') {
            throw ProblemException::common(422, 'invalid_cursor', 'Der Cursor ist ungültig.');
        }
        $page = array_slice($terms, $start, $limit);
        $last = end($page);
        $next = is_array($last) && $start + $limit < count($terms) ? self::encode($last, $filters) : null;

        return [$page, $next];
    }

    /** @param  array<string, mixed>  $term */
    private static function encode(array $term, string $filters): string
    {
        $json = (string) json_encode(['k' => self::key($term), 'i' => (string) $term['id'], 'f' => $filters], JSON_UNESCAPED_UNICODE);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /** @return array{abbreviation: string, name: string, id: string} */
    private static function decode(string $token, string $filters): array
    {
        $json = base64_decode(strtr($token, '-_', '+/'), true);
        $data = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($data) || ! is_string($data['k'] ?? null) || ! is_string($data['i'] ?? null) || ! isset($data['f'])) {
            throw ProblemException::common(422, 'invalid_cursor', 'Der Cursor ist ungültig.');
        }
        if ($data['f'] !== $filters) {
            throw ProblemException::common(422, 'invalid_cursor', 'Der Cursor passt nicht zu den Filtern.');
        }

        return ['abbreviation' => $data['k'], 'name' => $data['k'], 'id' => $data['i']];
    }

    /** Prüfsumme der Filter (alles ausser cursor, limit und with_total). */
    private static function fingerprint(Request $request): string
    {
        $params = $request->query();
        unset($params['cursor'], $params['limit'], $params['with_total']);
        ksort($params);

        return substr(hash('sha256', (string) json_encode($params)), 0, 16);
    }
}
