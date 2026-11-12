<?php

namespace App\Glossary;

use App\Models\Category;
use App\Models\DuplicateDecision;
use App\Models\Term;
use App\Models\User;
use InvalidArgumentException;

/**
 * Mögliche Doppelte finden (#16, wie im Intranet): Gruppen gleicher Abkürzung bzw. gleicher Bezeichnung. Es geschieht
 * nie etwas automatisch – die Redaktion führt zusammen (GlossaryService::merge) oder belässt die Gruppe als
 * mehrdeutig (gleiche Abkürzung, verschiedene Bedeutungen ist erlaubt).
 */
final class Duplicates
{
    /** Reihenfolge der Arten: gleiche Abkürzung und Bezeichnung zuerst (stärkster Hinweis). */
    private const ORDER = ['both' => 0, 'name' => 1, 'abbreviation' => 2];

    /**
     * Offene Gruppen (ohne Entscheid «mehrdeutig belassen»).
     *
     * @return list<array{signatur: string, art: string, wert: string, begriffe: list<array<string, mixed>>}>
     */
    public function groups(): array
    {
        $dismissed = array_flip(DuplicateDecision::query()->pluck('signature')->all());

        return array_values(array_filter(
            $this->all(),
            fn (array $group): bool => ! isset($dismissed[$group['signatur']]),
        ));
    }

    /** Anzahl Entscheide «mehrdeutig belassen». */
    public function dismissedCount(): int
    {
        return DuplicateDecision::query()->count();
    }

    /** Gruppe als mehrdeutig belassen; sie wird nicht mehr vorgeschlagen, solange die Mitglieder gleich bleiben. */
    public function dismiss(string $signature, ?User $user): void
    {
        foreach ($this->all() as $group) {
            if ($group['signatur'] === $signature) {
                DuplicateDecision::query()->firstOrCreate(['signature' => $signature], [
                    'match_type' => $group['art'],
                    'value' => mb_substr($group['wert'], 0, 255),
                    'term_ids' => array_map(fn (array $t): string => (string) $t['id'], $group['begriffe']),
                    'decided_by_id' => $user?->id,
                ]);

                return;
            }
        }

        throw new InvalidArgumentException('Diese Gruppe gibt es nicht mehr – die Liste wurde inzwischen geändert.');
    }

    /** Alle Entscheide «mehrdeutig belassen» aufheben (Gruppen erscheinen wieder). */
    public function resetDismissed(): int
    {
        return DuplicateDecision::query()->delete();
    }

    /**
     * Alle Gruppen mit mindestens zwei Begriffen.
     *
     * @return list<array{signatur: string, art: string, wert: string, begriffe: list<array<string, mixed>>}>
     */
    private function all(): array
    {
        $terms = Term::query()->current()->with(['categories', 'publishedRevision'])->get();

        /** @var array<string, array<string, list<Term>>> $buckets */
        $buckets = ['abbreviation' => [], 'name' => []];
        foreach ($terms as $term) {
            foreach (['abbreviation' => $term->displayAbbreviation(), 'name' => $term->displayName()] as $kind => $value) {
                $key = $this->normalize($value);
                if ($key !== '') {
                    $buckets[$kind][$key][] = $term;
                }
            }
        }

        /** @var array<string, array{art: string, wert: string, schluessel: string, mitglieder: list<Term>}> $groups */
        $groups = [];
        foreach ($buckets as $kind => $byValue) {
            foreach ($byValue as $key => $members) {
                if (count($members) < 2) {
                    continue;
                }
                usort($members, fn (Term $a, Term $b): int => strcmp($a->id, $b->id));
                $set = implode(',', array_map(fn (Term $t): string => $t->id, $members));
                $value = $kind === 'abbreviation' ? $members[0]->displayAbbreviation() : $members[0]->displayName();
                if (isset($groups[$set])) {
                    // gleiche Mitglieder bei Abkürzung und Bezeichnung → eine Gruppe «beides»
                    $groups[$set]['art'] = 'both';
                    $groups[$set]['wert'] = $groups[$set]['wert'].' – '.$value;
                    $groups[$set]['schluessel'] .= '|'.$key;

                    continue;
                }
                $groups[$set] = ['art' => $kind, 'wert' => $value, 'schluessel' => $key, 'mitglieder' => $members];
            }
        }

        $out = [];
        foreach ($groups as $set => $group) {
            $out[] = [
                'signatur' => hash('sha256', $group['art'].'|'.$group['schluessel'].'|'.$set),
                'art' => $group['art'],
                'wert' => $group['wert'],
                'begriffe' => array_map(fn (Term $t): array => $this->row($t), $group['mitglieder']),
            ];
        }
        usort($out, fn (array $a, array $b): int => [self::ORDER[$a['art']], mb_strtolower($a['wert'])]
            <=> [self::ORDER[$b['art']], mb_strtolower($b['wert'])]);

        return $out;
    }

    /** @return array<string, mixed> */
    private function row(Term $term): array
    {
        return [
            'id' => $term->id,
            'abkuerzung' => $term->displayAbbreviation(),
            'bezeichnung' => $term->displayName(),
            'kurz' => (string) $term->short_description,
            'kategorien' => $term->categories->map(fn (Category $c): string => $c->displayLabel())->values()->all(),
            'status' => Term::STATUS[$term->lifecycle_status] ?? $term->lifecycle_status,
            'aktiv' => $term->is_active,
            'veroeffentlicht' => $term->published_revision_id !== null,
        ];
    }

    /** Vergleichsschlüssel: Kleinbuchstaben, Leerraum vereinheitlicht, ohne Punkte am Ende. */
    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));

        return rtrim($value, '.');
    }
}
