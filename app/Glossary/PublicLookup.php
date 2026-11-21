<?php

namespace App\Glossary;

/**
 * Lesen im öffentlichen Glossary für API und MCP-Server (DEC-011, DEC-029 M5): nur veröffentlichte Begriffe in den
 * Feldern der API v1 (ApiPresenter), keine eigene Abfragelogik. Texte, die ein Assistent zum Erklären schickt, werden
 * nur im Speicher verarbeitet (M9).
 */
final class PublicLookup
{
    /** höchstens so viele Zeichen beim Erklären eines Texts */
    public const MAX_TEXT = 20000;

    public function __construct(private readonly ApiPresenter $presenter) {}

    /**
     * Suchtext, Kategorie (Schlüssel oder Label), Herkunft und Status wie GET /api/v1/terms.
     *
     * @param  array<string, mixed>  $t  Begriff in den Feldern der API
     */
    public static function matches(array $t, string $q, string $category = '', string $status = '', string $source = ''): bool
    {
        if ($status !== '' && $t['status'] !== $status) {
            return false;
        }
        foreach (['categories' => $category, 'sources' => $source] as $field => $wanted) {
            if ($wanted === '') {
                continue;
            }
            $refs = [];
            foreach ((array) $t[$field] as $c) {
                $refs[] = mb_strtolower((string) ($c['key'] ?? ''));
                $refs[] = mb_strtolower((string) ($c['label'] ?? ''));
            }
            if (! in_array(mb_strtolower($wanted), $refs, true)) {
                return false;
            }
        }
        $q = mb_strtolower(trim($q));
        if ($q === '') {
            return true;
        }
        $haystack = mb_strtolower(implode(' ', array_filter([
            $t['abbreviation'], $t['name'], $t['official_abbreviation'], $t['official_name'], $t['short_description'],
            $t['display_label'],
            implode(' ', (array) $t['aliases']),
            implode(' ', (array) ($t['search_terms'] ?? [])),
        ])));

        return str_contains($haystack, $q);
    }

    /**
     * Suche: genaue Treffer der Abkürzung bzw. Bezeichnung zuerst.
     *
     * @return list<array<string, mixed>>
     */
    public function search(string $q, string $category = '', string $source = '', int $limit = 10): array
    {
        $needle = mb_strtolower(trim($q));
        $hits = array_values(array_filter($this->presenter->terms(), fn (array $t): bool => self::matches($t, $q, $category, '', $source)));
        usort($hits, fn (array $a, array $b): int => self::rank($b, $needle) <=> self::rank($a, $needle));

        return array_slice($hits, 0, max(1, min(50, $limit)));
    }

    /**
     * Ein Begriff nach Code des Permalinks (auch als Adresse /g/{code}), Slug oder UUID; null, wenn nicht öffentlich.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $reference): ?array
    {
        $reference = trim($reference);
        if (preg_match('#/g/([0-9A-Za-z]{6})/?$#', $reference, $m) === 1 || preg_match('#/begriffe/([a-z0-9-]+)/?$#', $reference, $m) === 1) {
            $reference = $m[1];
        }

        return preg_match('/^[A-Za-z0-9-]{1,120}$/', $reference) === 1 ? $this->presenter->find($reference) : null;
    }

    /**
     * Alle Bedeutungen einer Abkürzung, Bezeichnung oder Schreibweise (ohne Gross-/Kleinschreibung).
     *
     * @return list<array<string, mixed>>
     */
    public function resolve(string $abbreviation): array
    {
        $key = GlossaryDisplay::normalize(trim($abbreviation));
        if ($key === '') {
            return [];
        }

        return array_values(array_filter($this->presenter->terms(), function (array $t) use ($key): bool {
            foreach ([$t['abbreviation'], $t['name'], ...(array) $t['aliases']] as $value) {
                if (is_string($value) && GlossaryDisplay::normalize($value) === $key) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * Abkürzungen (genaue Schreibweise, an Wortgrenzen) und auf Wunsch Bezeichnungen in einem Text finden – wie der
     * «glossary-highlighter» (DEC-026). Dazu Kürzel, die im Glossary fehlen. Der Text wird nicht gespeichert.
     *
     * @return array{found: list<array{text: string, count: int, terms: list<array<string, mixed>>}>, unknown: list<array{text: string, count: int}>}
     */
    public function explain(string $text, bool $names = false): array
    {
        $text = mb_substr($text, 0, self::MAX_TEXT);
        /** @var array<string, array{exact: bool, terms: array<string, array<string, mixed>>}> $keys */
        $keys = [];
        foreach ($this->presenter->terms() as $t) {
            $add = function (?string $value, bool $exact) use (&$keys, $t): void {
                $value = trim((string) $value);
                if (mb_strlen($value) < 2) {
                    return;
                }
                $k = $exact ? $value : mb_strtolower($value);
                $keys[$k] ??= ['exact' => $exact, 'terms' => []];
                $keys[$k]['terms'][(string) $t['id']] = self::compact($t);
            };
            $add($t['abbreviation'], true);
            foreach ((array) $t['aliases'] as $alias) {
                $add((string) $alias, true);
            }
            if ($names) {
                $add($t['name'], false);
            }
        }
        // längere Schlüssel zuerst, damit «VSA ZH» vor «VSA» zählt
        uksort($keys, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        $found = [];
        $covered = $text;
        foreach ($keys as $k => $entry) {
            $pattern = '/(?<![\p{L}\d])'.preg_quote($k, '/').'(?![\p{L}\d])/u'.($entry['exact'] ? '' : 'i');
            $count = preg_match_all($pattern, $covered);
            if ($count > 0) {
                $found[] = ['text' => $k, 'count' => (int) $count, 'terms' => array_values($entry['terms'])];
                $covered = (string) preg_replace($pattern, ' ', $covered);
            }
        }

        // Kürzel, die im Glossary fehlen (mindestens zwei Grossbuchstaben, wie «Text prüfen»)
        $unknown = [];
        if (preg_match_all('/(?<![\p{L}\d])(\p{Lu}[\p{L}\d]{1,9})(?![\p{L}\d])/u', $covered, $m) > 0) {
            foreach ($m[1] as $word) {
                if (preg_match_all('/\p{Lu}/u', $word) >= 2 && ! (mb_strlen($word) > 6 && mb_strtoupper($word) === $word)) {
                    $unknown[$word] = ($unknown[$word] ?? 0) + 1;
                }
            }
        }
        arsort($unknown);

        return [
            'found' => $found,
            'unknown' => array_map(fn (string $w, int $c): array => ['text' => $w, 'count' => $c], array_keys(array_slice($unknown, 0, 50, true)), array_slice($unknown, 0, 50, true)),
        ];
    }

    /**
     * Kategorien und Herkunft mit Beschreibung.
     *
     * @return array{categories: list<array<string, mixed>>, sources: list<array<string, mixed>>}
     */
    public function lists(): array
    {
        return ['categories' => $this->presenter->categoryList(), 'sources' => $this->presenter->sourceList()];
    }

    /** @return array{name: string, url: string, attribution: string} */
    public function license(): array
    {
        return $this->presenter->license();
    }

    /**
     * Kurzform für Listen (Suche, Erklären).
     *
     * @param  array<string, mixed>  $t
     * @return array<string, mixed>
     */
    public static function compact(array $t): array
    {
        return [
            'code' => $t['code'],
            'abbreviation' => $t['abbreviation'],
            'name' => $t['name'],
            'short_description' => $t['short_description'],
            'status' => $t['status'],
            'categories' => array_values(array_map(fn (mixed $c): string => is_array($c) ? (string) ($c['label'] ?? '') : '', (array) $t['categories'])),
            'research_basis' => $t['research_basis'] ?? null,
            'permalink' => $t['permalink'],
        ];
    }

    /** @param  array<string, mixed>  $t */
    private static function rank(array $t, string $needle): int
    {
        if ($needle === '') {
            return 0;
        }
        foreach ([$t['abbreviation'], $t['name']] as $i => $value) {
            if (is_string($value) && mb_strtolower($value) === $needle) {
                return 3 - $i;
            }
        }

        return 0;
    }
}
