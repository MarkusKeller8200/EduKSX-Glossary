<?php

namespace App\Http\Controllers\Admin;

use App\Glossary\ExcelFormat;
use App\Glossary\GlossaryImporter;
use App\Http\Controllers\Controller;
use App\Models\Term;
use App\Models\User;
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

    /** Alle Begriffe (Arbeitsstand) bzw. leere Vorlage als .xlsx. */
    public function export(Request $request): HttpResponse
    {
        $template = $request->boolean('vorlage');
        $rows = [];
        if (! $template) {
            foreach (Term::query()->with(['categories', 'sources', 'aliases'])->get() as $term) {
                $rows[] = array_values(ExcelFormat::row($term));
            }
            usort($rows, fn (array $a, array $b): int => strcasecmp($a[4] ?: $a[2], $b[4] ?: $b[2]));
        }
        $name = $template ? 'glossary-vorlage' : 'glossary-'.date('Y-m-d');

        return response(SpreadsheetWriter::xlsx(ExcelFormat::header(), $rows, 'Glossary'), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$name.'.xlsx"',
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/import', ['spalten' => ExcelFormat::header()]);
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
            $table = SpreadsheetReader::read((string) $file->getRealPath(), $file->getClientOriginalName(), ExcelFormat::header());
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
        ], now()->addMinutes(30));

        return Inertia::render('admin/import-preview', [
            'token' => $token,
            'datei' => $file->getClientOriginalName(),
            'fehlendeSpalten' => array_values($unknown),
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
        $result = $this->importer->run($rows, (bool) $cached['listen_anlegen'], $this->user($request), $this->official(), dryRun: false);

        return redirect('/admin/begriffe')->with('status', "Import: {$result['neu']} neu, {$result['geaendert']} geändert, {$result['fehler']} übersprungen. Neue Begriffe sind Entwürfe.");
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
