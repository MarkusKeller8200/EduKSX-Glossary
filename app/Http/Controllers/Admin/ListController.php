<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Locale;
use App\Glossary\ListTransfer;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ListTranslation;
use App\Models\Source;
use App\Support\Download;
use App\Support\Palette;
use App\Support\SpreadsheetReader;
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

        return Download::table($request->query('format') === 'csv' ? 'csv' : 'xlsx', ListTransfer::header(), $this->transfer->rows($list), $name, ucfirst($list));
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
            $data[$name] = $model::query()->with('translations')->orderBy('sort_order')->orderBy('name')->withCount('terms')->get()
                ->map(fn (Category|Source $i): array => [
                    'id' => $i->id, 'key' => $i->key, 'abbreviation' => $i->abbreviation, 'name' => $i->name,
                    'label' => $i->label, 'description' => $i->description, 'url' => $i->url, 'note' => $i->note, 'color' => $i->color,
                    'sort_order' => $i->sort_order, 'begriffe' => (int) $i->getAttribute('terms_count'),
                    'translations' => $this->translationForm($i),
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
        $translations = $this->pullTranslations($data);
        $this->syncTranslations($model::query()->create($data), $translations);

        return back()->with('status', 'Eintrag angelegt.');
    }

    public function update(Request $request, string $list, string $id): RedirectResponse
    {
        $model = $this->model($list);
        $item = $model::query()->findOrFail($id);
        $data = $this->validated($request, $model, $item->id);
        unset($data['key']);   // Schlüssel bleibt stabil (API, Excel)
        $translations = $this->pullTranslations($data);
        $item->update($data);
        $this->syncTranslations($item, $translations);

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
            'translations' => ['array'],
            'translations.*' => ['array'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
            'translations.*.label' => ['nullable', 'string', 'max:60'],
            'translations.*.description' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /**
     * Übersetzungen fürs Formular (X1, #282): alle Sprachen und Felder, leer als ''.
     *
     * @return array<string, array<string, string>>
     */
    private function translationForm(Category|Source $item): array
    {
        $out = [];
        foreach (Locale::translated() as $locale) {
            $row = $item->translations->firstWhere('locale', $locale);
            foreach (ListTranslation::FIELDS as $field) {
                $out[$locale][$field] = (string) ($row?->{$field} ?? '');
            }
        }

        return $out;
    }

    /**
     * Übersetzungen aus den Formulardaten lösen; nur fr/it/en, leere Felder → null.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array<string, string|null>>|null null = nicht mitgeschickt (unverändert lassen)
     */
    private function pullTranslations(array &$data): ?array
    {
        if (! array_key_exists('translations', $data)) {
            return null;
        }
        $input = (array) $data['translations'];
        unset($data['translations']);
        $out = [];
        foreach (Locale::translated() as $locale) {
            foreach (ListTranslation::FIELDS as $field) {
                $value = trim((string) (((array) ($input[$locale] ?? []))[$field] ?? ''));
                $out[$locale][$field] = $value === '' ? null : $value;
            }
        }

        return $out;
    }

    /** @param  array<string, array<string, string|null>>|null  $translations */
    private function syncTranslations(Category|Source $item, ?array $translations): void
    {
        if ($translations === null) {
            return;
        }
        foreach ($translations as $locale => $fields) {
            if (array_filter($fields) === []) {
                $item->translations()->where('locale', $locale)->delete();
            } else {
                $item->translations()->updateOrCreate(['locale' => $locale], $fields);
            }
        }
    }
}
