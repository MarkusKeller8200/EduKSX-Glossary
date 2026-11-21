<?php

namespace App\Mcp\Tools;

/** Textform der Antworten für Assistenten ohne strukturierte Ausgabe (DEC-029 M5). */
final class Format
{
    /** @param  array<string, mixed>  $t  Kurzform (PublicLookup::compact) */
    public static function line(array $t): string
    {
        $title = trim((string) ($t['abbreviation'] ?? '').' – '.(string) ($t['name'] ?? ''), ' –');
        $hint = ($t['research_basis'] ?? null) === 'ai_assisted' ? ' [KI-unterstützt, noch nicht geprüft]' : '';

        $short = (string) ($t['short_description'] ?? '');
        $link = (string) ($t['permalink'] ?? '');

        return $title.($short !== '' ? ': '.$short : '').$hint.($link !== '' ? ' ('.$link.')' : '');
    }

    /** @param  array<string, mixed>  $t  Begriff in den Feldern der API v1 */
    public static function detail(array $t): string
    {
        $lines = [self::line($t)];
        if ((string) ($t['description'] ?? '') !== '') {
            $lines[] = (string) $t['description'];
        }
        $labels = fn (mixed $list): string => implode(', ', array_map(fn (mixed $c): string => is_array($c) ? (string) ($c['label'] ?? '') : '', (array) $list));
        foreach (['Aliase' => implode(', ', (array) ($t['aliases'] ?? [])), 'Kategorien' => $labels($t['categories'] ?? []), 'Herkunft' => $labels($t['sources'] ?? []), 'Status' => (string) ($t['status'] ?? ''), 'Gültig' => trim(($t['valid_from'] ?? '').' – '.($t['valid_until'] ?? ''), ' –'), 'Quelle' => (string) ($t['url'] ?? '')] as $label => $value) {
            if ($value !== '') {
                $lines[] = $label.': '.$value;
            }
        }
        $lines[] = 'Daten: EduKSX-Glossary, CC BY 4.0';

        return implode("\n", $lines);
    }
}
