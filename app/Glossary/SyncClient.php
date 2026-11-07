<?php

namespace App\Glossary;

use Closure;
use RuntimeException;

/**
 * Referenzclient für den Sync (#29): arbeitet wie ein Abnehmer (z. B. das Intranet) nur über die öffentliche API v1.
 *
 * 1. Erster Abruf: Vollstand über /api/v1/exports?format=json, merkt sich «cursor».
 * 2. Danach nur noch /api/v1/changes?since=<cursor> (seitenweise bis has_more = false):
 *    - «published»: Begriff in dieser Revision übernehmen; ist er nicht aktiv, lokal entfernen.
 *    - «unpublished»: lokal entfernen (Tombstone).
 *    - «merged»: lokal entfernen und alte ID auf «merged_into» umleiten.
 * 3. Listen (Kategorien, Herkunft) separat über /categories und /sources; im Begriff zählen die Schlüssel.
 *
 * Der Client ist absichtlich ohne Laravel-Abhängigkeiten geschrieben, damit das Intranet ihn als Vorlage nimmt.
 * Der Abruf ist austauschbar (HTTP im Befehl glossary:sync-check, direkt im Test).
 */
final class SyncClient
{
    /**
     * Lokaler Stand: Cursor, Datenstand, Begriffe nach ID, Weiterleitungen alte ID → neue ID.
     *
     * @var array{cursor: int, release: int, terms: array<string, array<mixed>>, redirects: array<string, string>}
     */
    private array $state;

    /**
     * @param  Closure(string): array<mixed>  $fetch  holt einen Pfad (ab /api/v1/…) und liefert das JSON
     * @param  array<mixed>|null  $state  gespeicherter Stand (null = noch nie synchronisiert)
     */
    public function __construct(private readonly Closure $fetch, ?array $state = null)
    {
        $terms = [];
        foreach ((array) ($state['terms'] ?? []) as $id => $term) {
            if (is_array($term)) {
                $terms[(string) $id] = $term;
            }
        }
        $redirects = [];
        foreach ((array) ($state['redirects'] ?? []) as $from => $to) {
            $redirects[(string) $from] = (string) $to;
        }
        $this->state = [
            'cursor' => $state === null ? -1 : (int) ($state['cursor'] ?? 0),   // -1: Vollstand nötig
            'release' => (int) ($state['release'] ?? 0),
            'terms' => $terms,
            'redirects' => $redirects,
        ];
    }

    /**
     * Synchronisieren: beim ersten Mal Vollstand, sonst Änderungen seit dem Cursor.
     *
     * @return array{art: string, published: int, unpublished: int, merged: int, cursor: int}
     */
    public function sync(): array
    {
        if ($this->state['cursor'] < 0) {
            $export = ($this->fetch)('/api/v1/exports?format=json');
            $this->state['terms'] = [];
            foreach ($this->list($export, 'terms') as $term) {
                $this->state['terms'][(string) $term['id']] = self::normalize($term);
            }
            $this->state['cursor'] = (int) ($export['cursor'] ?? 0);
            $this->state['release'] = (int) ($export['release'] ?? 0);

            // Ereignisse, die zwischen Cursor und Begriffen dazukamen, nachholen (wiederholbar)
            return ['art' => 'vollstand'] + $this->changes();
        }

        return ['art' => 'aenderungen'] + $this->changes();
    }

    /**
     * Lokalen Stand mit dem Vollstand des Servers vergleichen.
     *
     * @return list<string> Abweichungen (leer = identisch)
     */
    public function compare(): array
    {
        $export = ($this->fetch)('/api/v1/exports?format=json');
        $remote = [];
        foreach ($this->list($export, 'terms') as $term) {
            $remote[(string) $term['id']] = self::normalize($term);
        }

        $diff = [];
        foreach (array_diff_key($remote, $this->state['terms']) as $id => $term) {
            $diff[] = "fehlt lokal: {$id} ({$this->title($term)})";
        }
        foreach (array_diff_key($this->state['terms'], $remote) as $id => $term) {
            $diff[] = "zu viel lokal: {$id} ({$this->title($term)})";
        }
        foreach (array_intersect_key($this->state['terms'], $remote) as $id => $term) {
            if ($term != $remote[$id]) {
                $fields = array_keys(array_filter($remote[$id], fn ($v, $k): bool => ($term[$k] ?? null) != $v, ARRAY_FILTER_USE_BOTH));
                $diff[] = "anders: {$id} ({$this->title($term)}): ".implode(', ', $fields);
            }
        }

        return $diff;
    }

    /**
     * Stand zum Speichern (JSON).
     *
     * @return array{cursor: int, release: int, terms: array<string, array<mixed>>, redirects: array<string, string>}
     */
    public function state(): array
    {
        return $this->state;
    }

    /** Aktuelle ID zu einer (evtl. zusammengeführten) ID. */
    public function resolve(string $id): string
    {
        for ($i = 0; isset($this->state['redirects'][$id]) && $i < 20; $i++) {
            $id = $this->state['redirects'][$id];
        }

        return $id;
    }

    /**
     * Für den Vergleich: Kategorien und Herkunft nur als Schlüssel (Bezeichnungen kommen aus den Listen).
     *
     * @param  array<mixed>  $term
     * @return array<mixed>
     */
    public static function normalize(array $term): array
    {
        foreach (['categories', 'sources'] as $list) {
            $keys = [];
            foreach ((array) ($term[$list] ?? []) as $item) {
                $keys[] = is_array($item) ? (string) ($item['key'] ?? '') : (string) $item;
            }
            $term[$list] = $keys;
        }
        // vom Gesamtstand abhängige Felder fehlen im Change-Feed; der Abnehmer berechnet sie aus seinem Stand (DEC-020)
        foreach (ApiPresenter::CONTEXT_FIELDS as $field) {
            unset($term[$field]);
        }
        ksort($term);

        return $term;
    }

    /**
     * Change-Feed seit dem Cursor abarbeiten.
     *
     * @return array{published: int, unpublished: int, merged: int, cursor: int}
     */
    private function changes(): array
    {
        $count = ['published' => 0, 'unpublished' => 0, 'merged' => 0];
        do {
            $page = ($this->fetch)('/api/v1/changes?since='.$this->state['cursor'].'&limit=500');
            foreach ($this->list($page, 'data') as $event) {
                $id = (string) $event['term_id'];
                switch ($event['action'] ?? '') {
                    case 'published':
                        $term = is_array($event['term'] ?? null) ? $event['term'] : null;
                        if ($term !== null && ($term['is_active'] ?? true) === true) {
                            $this->state['terms'][$id] = self::normalize($term);
                        } else {
                            unset($this->state['terms'][$id]);
                        }
                        unset($this->state['redirects'][$id]);   // wieder aufgenommen
                        $count['published']++;
                        break;
                    case 'unpublished':
                        unset($this->state['terms'][$id]);
                        $count['unpublished']++;
                        break;
                    case 'merged':
                        unset($this->state['terms'][$id]);
                        if (is_string($event['merged_into'] ?? null)) {
                            $this->state['redirects'][$id] = $event['merged_into'];
                        }
                        $count['merged']++;
                        break;
                    default:
                        // unbekannte Ereignisart: überspringen (neue Arten sind abwärtskompatibel)
                }
                $this->state['cursor'] = (int) $event['cursor'];
            }
            $meta = is_array($page['meta'] ?? null) ? $page['meta'] : [];
            $this->state['cursor'] = max($this->state['cursor'], (int) ($meta['next_cursor'] ?? 0));
            $this->state['release'] = (int) ($meta['release'] ?? $this->state['release']);
        } while (($meta['has_more'] ?? false) === true);

        return $count + ['cursor' => $this->state['cursor']];
    }

    /**
     * @param  array<mixed>  $json
     * @return list<array<mixed>>
     */
    private function list(array $json, string $key): array
    {
        $items = $json[$key] ?? null;
        if (! is_array($items)) {
            throw new RuntimeException("Antwort ohne «{$key}».");
        }

        return array_values(array_filter($items, 'is_array'));
    }

    /** @param  array<mixed>  $term */
    private function title(array $term): string
    {
        return trim(((string) ($term['abbreviation'] ?? '')).' – '.((string) ($term['name'] ?? '')), ' –');
    }
}
