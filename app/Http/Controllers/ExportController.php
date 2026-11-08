<?php

namespace App\Http\Controllers;

use App\Glossary\ApiPresenter;
use App\Glossary\ExcelFormat;
use App\Glossary\PrintRow;
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
