<?php

namespace App\Glossary;

use Normalizer;

/**
 * Berechnete Anzeigewerte (DEC-020) – eine Regel für Website, API und Redaktion, nie gespeichert:
 *
 * - display_label: gemeinsame Abkürzung, sonst gemeinsame Bezeichnung.
 * - is_full_term: keine Abkürzung, aber eine Bezeichnung.
 * - meaning_count: Anzahl unterschiedlicher Bedeutungen derselben Abkürzung im jeweiligen Sichtstand (mindestens 1).
 *   Gleich ist eine Bedeutung bei gleicher normalisierter Bezeichnung; Kategorien zählen nicht.
 * - is_ambiguous: meaning_count > 1.
 *
 * Arbeitet auf Abbildern (Revision bzw. Arbeitsstand) mit official_abbreviation und official_name.
 */
final class GlossaryDisplay
{
    /** @var array<string, array<string, true>> normalisierte Abkürzung → Bedeutungen */
    private array $meanings = [];

    /**
     * @param  iterable<array<string, mixed>>  $snapshots  Sichtstand (z. B. alle öffentlich sichtbaren Abbilder)
     */
    public function __construct(iterable $snapshots = [])
    {
        foreach ($snapshots as $s) {
            $abbreviation = self::normalize($s['official_abbreviation'] ?? null);
            if ($abbreviation === '') {
                continue;
            }
            $meaning = self::normalize($s['official_name'] ?? null);
            $this->meanings[$abbreviation][$meaning !== '' ? $meaning : 'id:'.($s['id'] ?? '')] = true;
        }
    }

    /** @param  array<string, mixed>  $s */
    public static function displayLabel(array $s): string
    {
        $abbreviation = trim((string) ($s['official_abbreviation'] ?? ''));

        return $abbreviation !== '' ? $abbreviation : trim((string) ($s['official_name'] ?? ''));
    }

    /** @param  array<string, mixed>  $s */
    public static function isFullTerm(array $s): bool
    {
        return trim((string) ($s['official_abbreviation'] ?? '')) === '' && trim((string) ($s['official_name'] ?? '')) !== '';
    }

    /** @param  array<string, mixed>  $s */
    public function meaningCount(array $s): int
    {
        $abbreviation = self::normalize($s['official_abbreviation'] ?? null);

        return max(1, $abbreviation === '' ? 1 : count($this->meanings[$abbreviation] ?? []));
    }

    /** @param  array<string, mixed>  $s */
    public function isAmbiguous(array $s): bool
    {
        return $this->meaningCount($s) > 1;
    }

    /** Vergleichsform: Unicode NFC, Leerraum zusammengefasst, ohne Gross-/Kleinschreibung. */
    public static function normalize(mixed $value): string
    {
        $value = (string) $value;
        if (class_exists(Normalizer::class)) {
            $value = Normalizer::normalize($value, Normalizer::FORM_C) ?: $value;
        }

        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));
    }
}
