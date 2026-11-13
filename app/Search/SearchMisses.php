<?php

namespace App\Search;

use Illuminate\Support\Facades\DB;

/**
 * Suchen ohne Treffer (X4, #285): Hinweise für die Redaktion auf fehlende Begriffe. Gespeichert wird nur der
 * normalisierte Suchtext mit Zähler – keine Person, keine IP-Adresse, keine Sitzung. Löschfrist 90 Tage.
 */
final class SearchMisses
{
    /** Tage nach dem letzten Auftreten, danach gelöscht (Löschkonzept). */
    public const RETENTION_DAYS = 90;

    /** Kleinbuchstaben, Leerraum zusammengefasst; null bei weniger als 2 oder mehr als 100 Zeichen. */
    public static function normalize(string $query): ?string
    {
        $query = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $query)));
        $length = mb_strlen($query);

        return $length >= 2 && $length <= 100 ? $query : null;
    }

    public static function record(string $query): void
    {
        $query = self::normalize($query);
        if ($query === null) {
            return;
        }
        $now = now()->toDateTimeString();
        DB::table('search_misses')->upsert(
            [['query' => $query, 'count' => 1, 'first_seen_at' => $now, 'last_seen_at' => $now]],
            ['query'],
            ['count' => DB::raw('search_misses.count + 1'), 'last_seen_at' => $now],
        );
    }

    /** Einträge löschen, die länger als die Frist nicht mehr gesucht wurden; Anzahl gelöschter. */
    public static function prune(): int
    {
        return DB::table('search_misses')->where('last_seen_at', '<', now()->subDays(self::RETENTION_DAYS))->delete();
    }
}
