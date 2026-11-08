<?php

namespace App\Http\Controllers;

use App\Glossary\ApiPresenter;
use App\Glossary\ExcelFormat;
use App\Glossary\PrintRow;
use App\Glossary\TermExport;
use App\Support\Csv;
use App\Support\SpreadsheetWriter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Export veröffentlichter Daten ausserhalb der Administration (#111, Recht «export», ab Benutzer): nur veröffentlichte,
 * aktive Begriffe mit den öffentlichen Feldern (ExcelFormat::PUBLIC_COLUMNS) – keine Entwürfe, keine Notiz, keine
 * persönliche Darstellung. Als JSON (?format=json) die Zeilen für den PDF-Export (#112). Den Arbeitsstand exportiert die Redaktion unter /admin/export.
 */
class ExportController extends Controller
{
    /** Export-Menü (#131): sichtbare Zeilen (IDs oder Slugs) als Excel, CSV oder Markdown, Spalten sichtbar/vollständig. */
    public function selection(Request $request, TermExport $export): HttpResponse
    {
        $data = self::validateSelection($request);
        $table = $export->published($data['keys'], $data['umfang']);

        return $export->response($table, $data['format'], 'glossary-'.date('Y-m-d'), true);
    }

    /**
     * @return array{keys: list<string>|null, format: string, umfang: string}
     */
    public static function validateSelection(Request $request): array
    {
        $data = $request->validate([
            'format' => ['required', 'string', 'in:'.implode(',', TermExport::FORMATS)],
            'umfang' => ['required', 'string', 'in:sichtbar,vollstaendig'],
            'keys' => ['nullable', 'array', 'max:10000'],
            'keys.*' => ['string', 'max:200'],
        ]);

        return [
            'keys' => isset($data['keys']) ? array_values(array_map('strval', (array) $data['keys'])) : null,
            'format' => (string) $data['format'],
            'umfang' => (string) $data['umfang'],
        ];
    }

    public function __invoke(Request $request, ApiPresenter $presenter): HttpResponse
    {
        $terms = $presenter->terms();

        // Zeilen für den PDF-Export (#112): Druckansicht im Browser
        if ($request->query('format') === 'json') {
            return response()->json(['zeilen' => array_map(fn (array $t): array => PrintRow::fromPublic($t), $terms)])
                ->header('Cache-Control', 'private, no-store');
        }

        $rows = array_map(fn (array $term): array => ExcelFormat::publicRow($term), $terms);
        $name = 'glossary-veroeffentlicht-'.date('Y-m-d');

        if ($request->query('format') === 'csv') {
            return response(Csv::make(ExcelFormat::PUBLIC_COLUMNS, $rows), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$name.'.csv"',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return response(SpreadsheetWriter::xlsx(ExcelFormat::PUBLIC_COLUMNS, $rows, 'Glossary'), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$name.'.xlsx"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
