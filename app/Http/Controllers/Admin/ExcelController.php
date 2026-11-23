<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ResearchBasis;
use App\Glossary\ExcelFormat;
use App\Glossary\GlossaryImporter;
use App\Glossary\Journal;
use App\Glossary\PrintRow;
use App\Glossary\TermExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ExportController;
use App\Models\ImportBatch;
use App\Models\Term;
use App\Support\Download;
use App\Support\SpreadsheetReader;
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
        return Download::table($request->query('format') === 'csv' ? 'csv' : 'xlsx', ExcelFormat::header(), $rows, $name);
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
            // Spalten des alten Formats mitlesen, um es zu erkennen: es wird nicht mehr unterstützt (DEC-030)
            $table = SpreadsheetReader::read((string) $file->getRealPath(), $file->getClientOriginalName(), [
                ...ExcelFormat::header(), ...ExcelFormat::OLD_COLUMNS,
            ]);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['datei' => $e->getMessage()]);
        }
        $old = array_values(array_intersect(ExcelFormat::OLD_COLUMNS, $table['header']));
        if ($old !== []) {
            throw ValidationException::withMessages(['datei' => 'Altes Format des Intranets mit den Spalten «'.implode('», «', $old).'» – wird nicht mehr unterstützt. Vorlage herunterladen oder die Spalten entfernen.']);
        }
        $unknown = array_diff(array_keys(ExcelFormat::COLUMNS), $table['header']);
        if (count($unknown) === count(ExcelFormat::COLUMNS)) {
            throw ValidationException::withMessages(['datei' => 'Keine bekannten Spalten gefunden (Vorlage herunterladen oder Export des Intranets verwenden).']);
        }

        $createLists = $request->boolean('listen_anlegen');
        $result = $this->importer->run($table['rows'], $createLists, $this->currentUserOrNull($request), $this->official(), dryRun: true);
        $token = (string) Str::uuid();
        Cache::put(self::CACHE.$token, [
            'user' => $this->currentUserOrNull($request)?->id,
            'rows' => $table['rows'],
            'listen_anlegen' => $createLists,
            'datei' => mb_substr($file->getClientOriginalName(), 0, 255),
            'format' => ExcelFormat::VERSION,
            'fehlende' => array_values($unknown),
        ], now()->addMinutes(30));

        return Inertia::render('admin/import-preview', [
            'token' => $token,
            'datei' => $file->getClientOriginalName(),
            'fehlendeSpalten' => array_values($unknown),
            'auswahl' => null,
            'ki' => false,
            ...$result,
        ]);
    }

    /**
     * Vorschau mit überschriebenen Werten erneut prüfen (#505): dieselbe Datei, darin die geänderten Werte; gespeichert
     * wird nichts. Abgewählte Zeilen und Felder bleiben in der Vorschau sichtbar und wirken erst beim Übernehmen.
     */
    public function check(Request $request, string $token): Response
    {
        $cached = $this->cached($request, $token, false);
        $choice = $this->choice($request);
        $ai = $request->boolean('ki');
        $rows = self::applyChoice($cached['rows'], $choice, false, $ai);
        $result = $this->importer->run($rows, (bool) $cached['listen_anlegen'], $this->currentUserOrNull($request), $this->official(), dryRun: true);

        return Inertia::render('admin/import-preview', [
            'token' => $token,
            'datei' => (string) ($cached['datei'] ?? 'Datei'),
            'fehlendeSpalten' => array_values(array_filter((array) ($cached['fehlende'] ?? []), fn (string $c): bool => ! ($ai && $c === 'Recherche'))),
            'auswahl' => $choice === [] ? null : $choice,
            'ki' => $ai,
            ...$result,
        ]);
    }

    /** Vorschau übernehmen: dieselben Zeilen nochmals, diesmal gespeichert. Fehlerhafte Zeilen werden übersprungen. */
    public function apply(Request $request, string $token): RedirectResponse
    {
        $choice = $this->choice($request);
        $cached = $this->cached($request, $token, true);
        // Auswahl (#505): abgewählte Zeilen weglassen, abgewählte Felder nicht übernehmen, überschriebene Werte einsetzen
        $rows = self::applyChoice($cached['rows'], $choice, true, $request->boolean('ki'));
        $user = $this->currentUserOrNull($request);
        $result = $this->importer->run($rows, (bool) $cached['listen_anlegen'], $user, $this->official(), dryRun: false);

        $errors = [];
        foreach ($result['plan'] as $p) {
            if ($p['art'] === 'fehler' && count($errors) < 100) {
                $errors[] = ['zeile' => $p['zeile'], 'meldung' => mb_substr(implode(' ', $p['meldungen']), 0, 300)];
            }
        }
        ImportBatch::query()->create([
            'imported_by_id' => $user?->id,
            'user_label' => Journal::actorLabel($user),
            'file_name' => (string) ($cached['datei'] ?? 'Datei'),
            'format_version' => (int) ($cached['format'] ?? ExcelFormat::VERSION),
            'rows' => count($rows),
            'created_count' => $result['neu'],
            'updated_count' => $result['geaendert'],
            'error_count' => $result['fehler'],
            'errors' => $errors === [] ? null : $errors,
        ]);

        return redirect('/admin/begriffe')->with('status', "Import: {$result['neu']} neu, {$result['geaendert']} geändert, {$result['fehler']} übersprungen. Neue Begriffe sind Entwürfe.");
    }

    /**
     * Zwischengespeicherte Vorschau dieser Person.
     *
     * @return array{rows: list<array<string, string>>, listen_anlegen: bool, datei?: string, format?: int, fehlende?: list<string>}
     */
    private function cached(Request $request, string $token, bool $pull): array
    {
        $cached = $pull ? Cache::pull(self::CACHE.$token) : Cache::get(self::CACHE.$token);
        abort_if(! is_array($cached) || ($cached['user'] ?? null) !== $this->currentUserOrNull($request)?->id, 404);

        /** @var array{rows: list<array<string, string>>, listen_anlegen: bool, datei?: string, format?: int, fehlende?: list<string>} $cached */
        return $cached;
    }

    /**
     * Auswahl aus der Vorschau (#505): je Zeilennummer der Datei abgewählt, abgewählte Spalten und überschriebene Werte.
     *
     * @return array<int, array{aus: bool, felder_aus: list<string>, werte: array<string, string>}>
     */
    private function choice(Request $request): array
    {
        $request->validate([
            'zeilen' => ['nullable', 'array', 'max:5000'],
            'zeilen.*.aus' => ['nullable', 'boolean'],
            'zeilen.*.felder_aus' => ['nullable', 'array', 'max:100'],
            'zeilen.*.felder_aus.*' => ['string', 'max:100'],
            'zeilen.*.werte' => ['nullable', 'array', 'max:100'],
            'zeilen.*.werte.*' => ['nullable', 'string', 'max:20000'],
            'ki' => ['nullable', 'boolean'],
        ]);
        $columns = array_flip(array_diff(ExcelFormat::header(), ['ID']));
        $out = [];
        foreach ((array) $request->input('zeilen', []) as $line => $c) {
            if (! is_numeric($line) || (int) $line < 2 || ! is_array($c)) {
                continue;
            }
            $values = [];
            foreach ((array) ($c['werte'] ?? []) as $column => $value) {
                if (isset($columns[(string) $column])) {
                    $values[(string) $column] = (string) ($value ?? '');
                }
            }
            $entry = [
                'aus' => (bool) ($c['aus'] ?? false),
                'felder_aus' => array_values(array_filter(array_map('strval', (array) ($c['felder_aus'] ?? [])), fn (string $f): bool => isset($columns[$f]))),
                'werte' => $values,
            ];
            if ($entry['aus'] || $entry['felder_aus'] !== [] || $entry['werte'] !== []) {
                $out[(int) $line] = $entry;
            }
        }

        return $out;
    }

    /**
     * Auswahl auf die Zeilen der Datei anwenden. Abgewählte Spalten fehlen danach in der Zeile: bei bestehenden
     * Begriffen bleibt der Wert, bei neuen bleibt das Feld leer. Die Schlüssel (Zeilennummer − 2) bleiben erhalten.
     * `$ai` (#508): neue Begriffe ohne Angabe in «Recherche» gelten als «KI-unterstützt» (vor der Auswahl je Zeile,
     * damit die Detailauswahl es wieder abwählen kann); bestehende Begriffe bleiben, wie sie sind.
     *
     * @param  list<array<string, string>>  $rows
     * @param  array<int, array{aus: bool, felder_aus: list<string>, werte: array<string, string>}>  $choice
     * @return array<int, array<string, string>>
     */
    public static function applyChoice(array $rows, array $choice, bool $final, bool $ai = false): array
    {
        if ($ai) {
            // nur neue Begriffe: bestehende (womöglich schon geprüfte) werden nicht zurückgestuft
            $ids = array_values(array_filter(array_map(fn (array $r): string => trim($r['ID'] ?? ''), $rows)));
            $known = $ids === [] ? [] : array_flip(Term::query()->whereIn('id', array_filter($ids, fn (string $id): bool => Str::isUuid($id)))->pluck('id')->all());
            foreach ($rows as $n => $row) {
                if (trim($row['Recherche'] ?? '') === '' && ! isset($known[trim($row['ID'] ?? '')])) {
                    $rows[$n]['Recherche'] = ResearchBasis::AiAssisted->label();
                }
            }
        }
        foreach ($choice as $line => $c) {
            $n = $line - 2;
            if (! isset($rows[$n])) {
                continue;
            }
            if ($final && $c['aus']) {
                unset($rows[$n]);

                continue;
            }
            foreach ($c['werte'] as $column => $value) {
                $rows[$n][$column] = $value;
            }
            if ($final) {
                foreach ($c['felder_aus'] as $column) {
                    unset($rows[$n][$column]);
                }
            }
        }

        return $rows;
    }

    private function official(): bool
    {
        return Gate::allows('edit-official');
    }
}
