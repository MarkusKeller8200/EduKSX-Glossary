<?php

namespace App\Support;

/**
 * CSV wie Excel es erwartet (#71): UTF-8 mit BOM, Semikolon, Anführungszeichen. Werte, die mit =, +, -, @, Tab oder
 * Wagenrücklauf beginnen, werden mit «'» als Text geschützt (CSV-Injection).
 */
final class Csv
{
    /** Wert gegen Formeln schützen. */
    public static function safe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }

    /**
     * Ganze Datei als Text.
     *
     * @param  list<string>  $header
     * @param  iterable<array<int|string, string>>  $rows
     */
    public static function make(array $header, iterable $rows): string
    {
        $out = fopen('php://temp', 'w+');
        if ($out === false) {
            return '';
        }
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_map(self::safe(...), $header), ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, array_map(fn ($v): string => self::safe((string) $v), array_values($row)), ';', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }
}
