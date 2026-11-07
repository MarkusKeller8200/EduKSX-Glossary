<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Liest die erste Tabelle einer XLSX- oder CSV-Datei als Zeilen mit Spaltennamen.
 *
 * Ohne Zusatzpaket (XLSX = ZIP mit XML). Nur Texte und Zahlen, keine Formeln,
 * keine Formatierung. Gelesen wird nur, was der Aufrufer per $columns verlangt –
 * alle anderen Spalten werden verworfen und nirgends gespeichert.
 */
final class SpreadsheetReader
{
    public const MAX_ROWS = 5000;

    /**
     * @param  list<string>  $columns  Spaltennamen, die zurückgegeben werden (Whitelist)
     * @return array{header: list<string>, rows: list<array<string, string>>}
     */
    public static function read(string $path, string $originalName, array $columns): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $table = match ($extension) {
            'xlsx' => self::xlsx($path),
            'csv', 'txt' => self::csv($path),
            default => throw new RuntimeException('Nur Excel (.xlsx) oder CSV-Dateien.'),
        };

        if ($table === []) {
            throw new RuntimeException('Die Datei enthält keine Tabelle.');
        }

        $header = array_map(fn (string $h): string => trim($h), array_shift($table));
        $wanted = array_flip($columns);
        $index = [];
        foreach ($header as $i => $name) {
            if (isset($wanted[$name])) {
                $index[$name] = $i;
            }
        }

        $rows = [];
        foreach ($table as $line) {
            if (count($rows) >= self::MAX_ROWS) {
                throw new RuntimeException('Mehr als '.self::MAX_ROWS.' Zeilen – bitte aufteilen.');
            }
            $row = [];
            foreach ($index as $name => $i) {
                $row[$name] = trim($line[$i] ?? '');
            }
            if (implode('', $row) !== '') {
                $rows[] = $row;
            }
        }

        return ['header' => array_values(array_filter($header, fn (string $h): bool => $h !== '')), 'rows' => $rows];
    }

    /**
     * @return list<list<string>>
     */
    private static function csv(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $firstLine = strtok($content, "\r\n") ?: '';
        $counts = [';' => substr_count($firstLine, ';'), ',' => substr_count($firstLine, ','), "\t" => substr_count($firstLine, "\t")];
        arsort($counts);
        $delimiter = (string) array_key_first($counts);

        $stream = fopen('php://memory', 'r+');
        if ($stream === false) {
            throw new RuntimeException('CSV konnte nicht gelesen werden.');
        }
        fwrite($stream, $content);
        rewind($stream);

        $table = [];
        while (($line = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $table[] = array_map(fn ($v): string => (string) $v, $line);
        }
        fclose($stream);

        return $table;
    }

    /**
     * @return list<list<string>>
     */
    private static function xlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP-Erweiterung «zip» fehlt – Excel-Dateien können nicht gelesen werden.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Die Excel-Datei ist beschädigt oder keine .xlsx-Datei.');
        }

        try {
            $shared = self::sharedStrings($zip);
            $sheetXml = $zip->getFromName(self::firstSheetPath($zip));
            if ($sheetXml === false) {
                throw new RuntimeException('Keine Tabelle in der Excel-Datei gefunden.');
            }

            $sheet = self::xml($sheetXml);
            $table = [];
            foreach ($sheet->sheetData->row ?? [] as $row) {
                $cells = [];
                $next = 0;
                foreach ($row->c as $cell) {
                    $col = isset($cell['r']) ? self::columnIndex((string) $cell['r']) : $next;
                    $cells[$col] = self::cellValue($cell, $shared);
                    $next = $col + 1;
                }
                if ($cells === []) {
                    continue;
                }
                $line = array_fill(0, max(array_keys($cells)) + 1, '');
                foreach ($cells as $col => $value) {
                    $line[$col] = $value;
                }
                $table[] = array_values($line);
            }

            return $table;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<string>
     */
    private static function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $strings = [];
        foreach (self::xml($xml)->si as $item) {
            if (isset($item->t)) {
                $strings[] = (string) $item->t;

                continue;
            }
            $text = '';
            foreach ($item->r as $run) {
                $text .= (string) $run->t;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private static function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook === false || $rels === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $first = self::xml($workbook)->sheets->sheet[0] ?? null;
        $relId = $first?->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? null;

        foreach (self::xml($rels)->Relationship as $rel) {
            if ((string) $rel['Id'] === (string) $relId) {
                $target = ltrim((string) $rel['Target'], '/');

                return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * @param  list<string>  $shared
     */
    private static function cellValue(SimpleXMLElement $cell, array $shared): string
    {
        $type = (string) ($cell['t'] ?? 'n');

        return match ($type) {
            's' => $shared[(int) $cell->v] ?? '',
            'inlineStr' => (string) ($cell->is->t ?? ''),
            'b' => ((string) $cell->v) === '1' ? 'ja' : 'nein',
            default => (string) ($cell->v ?? ''),
        };
    }

    private static function columnIndex(string $reference): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($reference)) ?? '';
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    private static function xml(string $content): SimpleXMLElement
    {
        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        if ($xml === false) {
            throw new RuntimeException('Die Excel-Datei konnte nicht gelesen werden.');
        }

        return $xml;
    }
}
