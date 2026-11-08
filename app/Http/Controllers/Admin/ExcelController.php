<?php

namespace App\Http\Controllers\Admin;

use App\Glossary\ExcelFormat;
use App\Glossary\GlossaryImporter;
use App\Glossary\Journal;
use App\Glossary\PrintRow;
use App\Glossary\TermExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ExportController;
use App\Models\ImportBatch;
use App\Models\Term;
use App\Models\User;
use App\Support\Csv;
use App\Support\SpreadsheetReader;
use App\Support\SpreadsheetWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Excel-Export und -Import (#13, #15) im Format des Intranets (App\Glossary\ExcelFormat). Import immer zuerst als
 * Vorschau (neu / geändert / Fehler), übernommen wird erst mit «Übernehmen». Nie löschen.
 */
class ExcelController extends Controller
{
    private const CACHE = 'glossary-import:';

    public function __construct(private readonly GlossaryImporter $importer) {}

    /** Export-Menü der Redaktion (#131): sichtbare Zeilen des Arbeitsstands als Excel, CSV oder Markdown. */
    public function exportSelection(Request $request, TermExport $export): HttpResponse
    {
        $data = ExportController::validateSelection($request);
        $table = $export->working($data['keys'], $data['umfang']);

        return $export->response($table, $data['format'], 'glossary-arbeitsstand-'.date('Y-m-d'), false);
    }

    /** Alle Begriffe (Arbeitsstand) bzw. leere Vorlage als .xlsx oder mit ?format=csv als CSV (Format Version 2). */
    public function export(Request $request): HttpResponse
    {
        // Zeilen für den PDF-Export der Redaktion (#112): Arbeitsstand mit Notiz
        if ($request->query('format') === 'json') {
            $terms = Term::query()->current()->with(['categories', 'sources', 'aliases'])->get();

            return response()->json(['zeilen' => $terms->map(fn (Term $t): array => PrintRow::fromTerm($t))->values()->all()])
                ->header('Cache-Control', 'private, no-store');
        }

        $template = $request->boolean('vorlage');
        $rows = [];
        if (! $template) {
            foreach (Term::query()->current()->with(['categories', 'sources', 'aliases'])->get() as $term) {
                $rows[] = array_values(ExcelFormat::row($term));
            }
            // Spalte 2 = Abkürzung offiziell, 5 = Bezeichnung offiziell
            usort($rows, fn (array $a, array $b): int => strcasecmp($a[2] ?: $a[5], $b[2] ?: $b[5]));
        }
        $name = $template ? 'glossary-vorlage' : 'glossary-'.date('Y-m-d');

        // CSV (#71): gleiche Spalten, UTF-8 mit BOM, Semikolon, Formeln als Text
        if ($request->query('format') === 'csv') {
            return response(Csv::make(ExcelFormat::header(), $rows), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$name.'.csv"',
            ]);
        }

        return response(SpreadsheetWriter::xlsx(ExcelFormat::header(), $rows, 'Glossary'), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$name.'.xlsx"',
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/import', [
            'spalten' => ExcelFormat::header(),
            // Import-Protokoll (#64): die letzten 20
            'protokoll' => ImportBatch::query()->latest('id')->limit(20)->get()->map(fn (ImportBatch $b): array => [
                'id' => $b->id,
                'am' => $b->created_at->toIso8601String(),
                'von' => $b->user_label,
                'datei' => $b->file_name,
                'format' => $b->format_version,
                'zeilen' => $b->rows,
                'neu' => $b->created_count,
                'geaendert' => $b->updated_count,
                'fehler' => $b->error_count,
                'altwerte' => $b->legacy_count,
                'meldungen' => $b->errors ?? [],
            ])->all(),
        ]);
    }

    /** Datei hochladen → Vorschau; gespeichert wird nichts. */
    public function preview(Request $request): Response
    {
        $request->validate([
            'datei' => ['required', 'file', 'max:5120'],
            'listen_anlegen' => ['nullable', 'boolean'],
        ]);
        $file = $request->file('datei');
        abort_if($file === null || is_array($file), 422);
        try {
            // auch die Spalten des alten Formats lesen: sie werden gemeldet und als Altwerte gesichert (DEC-020)
            $table = SpreadsheetReader::read((string) $file->getRealPath(), $file->getClientOriginalName(), [
                ...ExcelFormat::header(), ...array_keys(ExcelFormat::LEGACY_COLUMNS),
            ]);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['datei' => $e->getMessage()]);
        }
        $unknown = array_diff(array_keys(ExcelFormat::COLUMNS), $table['header']);
        if (count($unknown) === count(ExcelFormat::COLUMNS)) {
            throw ValidationException::withMessages(['datei' => 'Keine bekannten Spalten gefunden (Vorlage herunterladen oder Export des Intranets verwenden).']);
        }

        $createLists = $request->boolean('listen_anlegen');
        $result = $this->importer->run($table['rows'], $createLists, $this->user($request), $this->official(), dryRun: true);
        $token = (string) Str::uuid();
        Cache::put(self::CACHE.$token, [
            'user' => $this->user($request)?->id,
            'rows' => $table['rows'],
            'listen_anlegen' => $createLists,
            'datei' => mb_substr($file->getClientOriginalName(), 0, 255),
            'format' => ExcelFormat::versionOf($table['header']),
        ], now()->addMinutes(30));

        return Inertia::render('admin/import-preview', [
            'token' => $token,
            'datei' => $file->getClientOriginalName(),
            'fehlendeSpalten' => array_values($unknown),
            'altesFormat' => ExcelFormat::versionOf($table['header']) === 1,
            'altSpalten' => array_values(array_intersect(array_keys(ExcelFormat::LEGACY_COLUMNS), $table['header'])),
            ...$result,
        ]);
    }

    /** Vorschau übernehmen: dieselben Zeilen nochmals, diesmal gespeichert. Fehlerhafte Zeilen werden übersprungen. */
    public function apply(Request $request, string $token): RedirectResponse
    {
        $cached = Cache::pull(self::CACHE.$token);
        abort_if(! is_array($cached) || ($cached['user'] ?? null) !== $this->user($request)?->id, 404);

        /** @var list<array<string, string>> $rows */
        $rows = $cached['rows'];
        $user = $this->user($request);
        $result = $this->importer->run($rows, (bool) $cached['listen_anlegen'], $user, $this->official(), dryRun: false);

        $errors = [];
        foreach ($result['plan'] as $p) {
            if ($p['art'] === 'fehler' && count($errors) < 100) {
                $errors[] = ['zeile' => $p['zeile'], 'meldung' => mb_substr(implode(' ', $p['meldungen']), 0, 300)];
            }
        }
        ImportBatch::query()->create([
            'user_id' => $user?->id,
            'user_label' => Journal::actorLabel($user),
            'file_name' => (string) ($cached['datei'] ?? 'Datei'),
            'format_version' => (int) ($cached['format'] ?? ExcelFormat::VERSION),
            'rows' => count($rows),
            'created_count' => $result['neu'],
            'updated_count' => $result['geaendert'],
            'error_count' => $result['fehler'],
            'legacy_count' => $result['altwerte'],
            'errors' => $errors === [] ? null : $errors,
        ]);

        return redirect('/admin/begriffe')->with('status', "Import: {$result['neu']} neu, {$result['geaendert']} geändert, {$result['fehler']} übersprungen. Neue Begriffe sind Entwürfe."
            .($result['altwerte'] > 0 ? " {$result['altwerte']} Altwerte gesichert (Seite «Altwerte»)." : ''));
    }

    private function user(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }

    private function official(): bool
    {
        return Gate::allows('edit-official');
    }
}
