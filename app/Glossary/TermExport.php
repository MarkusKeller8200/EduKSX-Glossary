<?php

namespace App\Glossary;

use App\Models\Term;
use App\Support\Download;
use Symfony\Component\HttpFoundation\Response;

/**
 * Export der sichtbaren Zeilen (#131) für das Export-Menü der Website und der Administration:
 *
 * - Umfang «sichtbar»: die Spalten der Tabelle (SICHTBAR); «vollstaendig»: alle Felder des jeweiligen Exports.
 * - Website: nur veröffentlichte, aktive Begriffe mit der Positivliste ExcelFormat::PUBLIC_COLUMNS.
 * - Administration: Arbeitsstand (ExcelFormat::header(), mit Notiz).
 * - Formate: xlsx, csv, md (Markdown-Tabelle). Reihenfolge wie übergeben (IDs oder Slugs), sonst wie üblich.
 */
final class TermExport
{
    /** Spalten der Tabellenansicht (gleiche Namen im öffentlichen und im Arbeitsstand-Export). */
    public const SICHTBAR = ['Abkürzung offiziell', 'Bezeichnung offiziell', 'Kurzbeschreibung', 'Kategorien', 'Status'];

    public const FORMATS = ['xlsx', 'csv', 'md'];

    public function __construct(private readonly ApiPresenter $presenter) {}

    /**
     * Veröffentlichte Begriffe.
     *
     * @param  list<string>|null  $keys  IDs oder Slugs in gewünschter Reihenfolge; null = alle
     * @return array{header: list<string>, rows: list<list<string>>}
     */
    public function published(?array $keys, string $scope): array
    {
        $terms = $this->presenter->terms();
        if ($keys !== null) {
            $byKey = [];
            foreach ($terms as $t) {
                $byKey[(string) $t['id']] = $t;
                $byKey[(string) $t['slug']] = $t;
            }
            $terms = [];
            $seen = [];
            foreach ($keys as $k) {
                $t = $byKey[$k] ?? null;
                if ($t !== null && ! isset($seen[$t['id']])) {
                    $seen[$t['id']] = true;
                    $terms[] = $t;
                }
            }
        }
        $rows = array_map(fn (array $t): array => ExcelFormat::publicRow($t), $terms);

        return $this->pick(ExcelFormat::PUBLIC_COLUMNS, $rows, $scope);
    }

    /**
     * Arbeitsstand (Redaktion).
     *
     * @param  list<string>|null  $ids
     * @return array{header: list<string>, rows: list<list<string>>}
     */
    public function working(?array $ids, string $scope): array
    {
        $query = Term::query()->current()->with(['categories', 'sources', 'aliases']);
        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }
        $terms = $query->get()->keyBy('id');
        $order = $ids ?? $terms->sortBy(fn (Term $t): string => mb_strtolower((string) ($t->official_abbreviation ?: $t->official_name)))->keys()->all();
        $rows = [];
        foreach ($order as $id) {
            $term = $terms->get($id);
            if ($term instanceof Term) {
                $rows[] = array_values(array_map('strval', ExcelFormat::row($term)));
            }
        }

        return $this->pick(ExcelFormat::header(), $rows, $scope);
    }

    /**
     * Datei erzeugen.
     *
     * @param  array{header: list<string>, rows: list<list<string>>}  $table
     */
    public function response(array $table, string $format, string $name, bool $license): Response
    {
        return $format === 'md'
            ? Download::file($this->markdown($table, $license), 'text/markdown; charset=UTF-8', $name.'.md')
            : Download::table($format, $table['header'], $table['rows'], $name);
    }

    /**
     * @param  list<string>  $header
     * @param  list<list<string>>  $rows
     * @return array{header: list<string>, rows: list<list<string>>}
     */
    private function pick(array $header, array $rows, string $scope): array
    {
        if ($scope !== 'sichtbar') {
            return ['header' => $header, 'rows' => $rows];
        }
        $index = [];
        foreach (self::SICHTBAR as $column) {
            $i = array_search($column, $header, true);
            if ($i !== false) {
                $index[] = (int) $i;
            }
        }

        return [
            'header' => array_map(fn (int $i): string => $header[$i], $index),
            'rows' => array_map(fn (array $r): array => array_map(fn (int $i): string => (string) ($r[$i] ?? ''), $index), $rows),
        ];
    }

    /** @param  array{header: list<string>, rows: list<list<string>>}  $table */
    private function markdown(array $table, bool $license): string
    {
        $cell = fn (string $v): string => str_replace(['|', "\r\n", "\n"], ['\\|', ' ', ' '], $v);
        $lines = ['# Glossary', '', 'Stand '.date('d.m.Y').' · '.count($table['rows']).' Einträge', ''];
        $lines[] = '| '.implode(' | ', array_map($cell, $table['header'])).' |';
        $lines[] = '|'.str_repeat(' --- |', count($table['header']));
        foreach ($table['rows'] as $row) {
            $lines[] = '| '.implode(' | ', array_map($cell, $row)).' |';
        }
        if ($license) {
            $lines[] = '';
            $lines[] = 'Daten: EduKSX-Glossary (glossary.eduksx.ch), Lizenz CC BY 4.0 – https://creativecommons.org/licenses/by/4.0/deed.de';
        }

        return implode("\n", $lines)."\n";
    }
}
