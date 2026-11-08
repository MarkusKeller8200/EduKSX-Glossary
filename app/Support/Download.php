<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

/**
 * Tabellen als Datei herunterladen (#138): eine Stelle für Excel, CSV und Markdown mit Dateiname und
 * `Cache-Control: private, no-store` (Exporte können angemeldete Inhalte enthalten).
 */
final class Download
{
    /**
     * @param  list<string>  $header
     * @param  iterable<list<string>|array<int|string, string>>  $rows
     */
    public static function table(string $format, array $header, iterable $rows, string $name, string $sheet = 'Glossary'): Response
    {
        $rows = array_values(array_map(fn (array $r): array => array_values(array_map('strval', $r)), [...$rows]));

        return match ($format) {
            'csv' => self::file(Csv::make($header, $rows), 'text/csv; charset=UTF-8', $name.'.csv'),
            default => self::file(SpreadsheetWriter::xlsx($header, $rows, $sheet), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $name.'.xlsx'),
        };
    }

    public static function file(string $content, string $type, string $filename): Response
    {
        return response($content, 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
