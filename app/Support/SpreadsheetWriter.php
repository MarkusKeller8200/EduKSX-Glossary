<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Schreibt eine Tabelle als CSV (Excel Schweiz: Semikolon, UTF-8 mit BOM) oder XLSX.
 * XLSX ohne Zusatzpaket: minimale Office-Open-XML-Datei mit Inline-Texten.
 */
final class SpreadsheetWriter
{
    /**
     * @param  list<string>  $header
     * @param  list<list<string>>  $rows
     */
    public static function csv(array $header, array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('CSV konnte nicht erstellt werden.');
        }
        fwrite($stream, "\xEF\xBB\xBF");
        foreach ([$header, ...$rows] as $line) {
            // Formel-Injektion verhindern (=, +, -, @ am Anfang)
            fputcsv($stream, array_map(self::safe(...), $line), ';', '"', '');
        }
        rewind($stream);
        $content = (string) stream_get_contents($stream);
        fclose($stream);

        return $content;
    }

    /**
     * @param  list<string>  $header
     * @param  list<list<string>>  $rows
     */
    public static function xlsx(array $header, array $rows, string $sheetName = 'Tabelle'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($path === false) {
            throw new RuntimeException('Temporäre Datei nicht möglich.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Excel-Datei konnte nicht erstellt werden.');
        }

        $sheetRows = [];
        foreach ([$header, ...$rows] as $r => $line) {
            $cells = [];
            foreach ($line as $c => $value) {
                $ref = self::column($c).($r + 1);
                $style = $r === 0 ? ' s="1"' : '';
                $cells[] = '<c r="'.$ref.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.self::xml($value).'</t></is></c>';
            }
            $sheetRows[] = '<row r="'.($r + 1).'">'.implode('', $cells).'</row>';
        }

        $cols = '';
        foreach ($header as $c => $title) {
            $longest = mb_strlen($title) + 4;
            foreach (array_slice($rows, 0, 500) as $row) {
                $longest = max($longest, mb_strlen($row[$c] ?? '') + 2);
            }
            $width = max(10, min(50, $longest));
            $cols .= '<col min="'.($c + 1).'" max="'.($c + 1).'" width="'.$width.'" customWidth="1"/>';
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.self::xml(mb_substr($sheetName, 0, 31)).'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border/></borders>'
            .'<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            .'<cellXfs count="2"><xf fontId="0"/><xf fontId="1" applyFont="1"/></cellXfs>'
            .'</styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>'.$cols.'</cols>'
            .'<sheetData>'.implode('', $sheetRows).'</sheetData>'
            .'<autoFilter ref="A1:'.self::column(max(0, count($header) - 1)).max(1, count($rows) + 1).'"/>'
            .'</worksheet>');
        $zip->close();

        $content = (string) file_get_contents($path);
        @unlink($path);

        return $content;
    }

    private static function column(int $index): string
    {
        $name = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $name = chr(65 + $mod).$name;
            $index = intdiv($index - 1, 26);
        }

        return $name;
    }

    private static function xml(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function safe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
