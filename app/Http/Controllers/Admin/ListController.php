<?php

namespace App\Http\Controllers\Admin;

use App\Glossary\ListTransfer;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Source;
use App\Support\Csv;
use App\Support\Palette;
use App\Support\SpreadsheetReader;
use App\Support\SpreadsheetWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Listen pflegen (#14): Kategorien und Herkunft – Kürzel, Bezeichnung, Label, Beschreibung, URL, Notiz, Farbe,
 * Reihenfolge (wie die Listen im Intranet). Löschen nur, wenn kein Begriff den Eintrag verwendet. Excel-Export und
 * -Import je Liste mit Vorschau (#101, App\Glossary\ListTransfer).
 */
class ListController extends Controller
{
    private const CACHE = 'glossary-list-import:';

    public function __construct(private readonly ListTransfer $transfer) {}

    /** Liste als .xlsx bzw. mit ?format=csv als CSV (Spalten wie das Formular). */
    public function export(Request $request, string $list): HttpResponse
    {
        $this->model($list);
        $name = 'glossary-'.$list.'-'.date('Y-m-d');
        if ($request->query('format') === 'csv') {
            return response(Csv::make(ListTransfer::header(), $this->transfer->rows($list)), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$name.'.csv"',
            ]);
        }

        return response(SpreadsheetWriter::xlsx(ListTransfer::header(), $this->transfer->rows($list), ucfirst($list)), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$name.'.xlsx"',
        ]);
    }

    /** Datei hochladen → Vorschau (neu / geändert / Fehler); gespeichert wird nichts. */
    public function preview(Request $request, string $list): Response
    {
        $this->model($list);
        $request->validate(['datei' => ['required', 'file', 'max:2048']]);
        $file = $request->file('datei');
        abort_if($file === null || is_array($file), 422);
        try {
            $table = SpreadsheetReader::read((string) $file->getRealPath(), $file->getClientOriginalName(), [...ListTransfer::header(), ListTransfer::INTRANET_ID]);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['datei' => $e->getMessage()]);
        }
        if (! in_array('Bezeichnung', $table['header'], true)) {
            throw ValidationException::withMessages(['datei' => 'Spalte «Bezeichnung» fehlt (Export der Liste als Vorlage verwenden).']);
        }
        $plan = $this->transfer->plan($list, $table['rows']);
        $token = (string) Str::uuid();
        Cache::put(self::CACHE.$token, ['user' => $request->user()?->getAuthIdentifier(), 'list' => $list, 'plan' => $plan], now()->addMinutes(30));

        return Inertia::render('admin/list-import-preview', [
            'liste' => $list,
            'token' => $token,
            'datei' => $file->getClientOriginalName(),
            'zeilen' => count($table['rows']),
            'plan' => array_map(fn (array $p): array => array_diff_key($p, ['daten' => true, 'id' => true]), $plan),
        ]);
    }

    public function apply(Request $request, string $list, string $token): RedirectResponse
    {
        $cached = Cache::pull(self::CACHE.$token);
        abort_if(! is_array($cached) || ($cached['user'] ?? null) !== $request->user()?->getAuthIdentifier() || ($cached['list'] ?? null) !== $list, 404);
        $result = $this->transfer->apply($list, (array) $cached['plan']);

        return redirect('/admin/listen')->with('status', "Import übernommen: {$result['neu']} neu, {$result['geaendert']} geändert.");
    }

    /** URL-Teil → Model */
    private const LISTS = ['kategorien' => Category::class, 'herkunft' => Source::class];

    public function index(): Response
    {
        $data = [];
        foreach (self::LISTS as $name => $model) {
            $data[$name] = $model::query()->orderBy('sort_order')->orderBy('name')->withCount('terms')->get()
                ->map(fn (Category|Source $i): array => [
                    'id' => $i->id, 'key' => $i->key, 'abbreviation' => $i->abbreviation, 'name' => $i->name,
                    'label' => $i->label, 'description' => $i->description, 'url' => $i->url, 'note' => $i->note, 'color' => $i->color,
                    'sort_order' => $i->sort_order, 'begriffe' => (int) $i->getAttribute('terms_count'),
                ])->all();
        }

        return Inertia::render('admin/lists', ['listen' => $data, 'farbwahl' => Palette::NAMES]);
    }

    public function store(Request $request, string $list): RedirectResponse
    {
        $model = $this->model($list);
        $data = $this->validated($request, $model, null);
        if (($data['key'] ?? '') === '') {
            $base = Str::slug((string) (($data['abbreviation'] ?? '') ?: $data['name']), '-', 'de') ?: Str::lower(Str::random(6));
            $key = $base;
            for ($i = 2; $model::query()->where('key', $key)->exists(); $i++) {
                $key = "{$base}-{$i}";
            }
            $data['key'] = $key;
        }
        $data['sort_order'] ??= (int) $model::query()->max('sort_order') + 1;
        $model::query()->create($data);

        return back()->with('status', 'Eintrag angelegt.');
    }

    public function update(Request $request, string $list, string $id): RedirectResponse
    {
        $model = $this->model($list);
        $item = $model::query()->findOrFail($id);
        $data = $this->validated($request, $model, $item->id);
        unset($data['key']);   // Schlüssel bleibt stabil (API, Excel)
        $item->update($data);

        return back()->with('status', 'Eintrag gespeichert.');
    }

    public function destroy(string $list, string $id): RedirectResponse
    {
        $model = $this->model($list);
        $item = $model::query()->withCount('terms')->findOrFail($id);
        if ((int) $item->getAttribute('terms_count') > 0) {
            return back()->with('status', 'Wird noch von Begriffen verwendet – nicht gelöscht.');
        }
        $item->delete();

        return back()->with('status', 'Eintrag gelöscht.');
    }

    /** @return class-string<Category|Source> */
    private function model(string $list): string
    {
        abort_unless(isset(self::LISTS[$list]), 404);

        return self::LISTS[$list];
    }

    /**
     * @param  class-string<Category|Source>  $model
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $model, ?string $ignoreId): array
    {
        $table = (new $model)->getTable();

        return $request->validate([
            'key' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/', Rule::unique($table, 'key')->ignore($ignoreId)],
            'abbreviation' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:2000'],
            'url' => ['nullable', 'url:http,https', 'max:500'],
            'note' => ['nullable', 'string', 'max:5000'],
            'color' => ['nullable', 'string', Rule::in(Palette::NAMES)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
