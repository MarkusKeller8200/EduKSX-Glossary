<?php

namespace App\Http\Controllers\Admin;

use App\Glossary\GlossaryService;
use App\Glossary\OfficialFieldLocked;
use App\Http\Controllers\Controller;
use App\Models\Term;
use App\Models\TermLegacyValue;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Altwerte zuordnen (DEC-020, #82): eigene Abkürzungen, Bezeichnungen, Aliase und «Intern genutzt in» aus dem alten
 * Intranet-Format. Nichts geschieht automatisch; die Redaktion entscheidet je Wert. Übernahmen laufen über den
 * GlossaryService (Regeln, Revision, Journal) und sind danach wie jede Änderung zu veröffentlichen.
 */
class LegacyValueController extends Controller
{
    /** Zuordnungen je Feld. */
    private const ALLOWED = [
        'own_abbreviation' => ['official', 'alternative', 'search', 'discarded'],
        'own_name' => ['official', 'alternative', 'search', 'discarded'],
        'own_alias' => ['official', 'alternative', 'search', 'discarded'],
        'used_in' => ['discarded'],
    ];

    public function __construct(private readonly GlossaryService $glossary) {}

    public function index(Request $request): Response
    {
        $all = $request->query('anzeige') === 'alle';
        $query = TermLegacyValue::query()->with('term')->orderBy('term_id')->orderBy('field')->orderBy('id');
        if (! $all) {
            $query->open();
        }
        $rows = $query->limit(5000)->get()->map(fn (TermLegacyValue $v): array => [
            'id' => $v->id,
            'term_id' => $v->term_id,
            'titel' => $v->term->title(),
            'zusammengefuehrt' => $v->term->merged_into_id !== null,
            'offiziell' => match ($v->field) {
                'own_abbreviation' => (string) $v->term->official_abbreviation,
                'own_name' => (string) $v->term->official_name,
                default => '',
            },
            'feld' => $v->field,
            'feld_label' => TermLegacyValue::FIELDS[$v->field] ?? $v->field,
            'wert' => $v->value,
            'herkunft' => $v->origin === 'import' ? 'Excel-Import' : 'Migration',
            'zuordnung' => $v->resolution !== null ? (TermLegacyValue::RESOLUTIONS[$v->resolution] ?? $v->resolution) : null,
            'erlaubt' => self::ALLOWED[$v->field] ?? ['discarded'],
        ])->all();

        return Inertia::render('admin/legacy-values', [
            'werte' => $rows,
            'alle' => $all,
            'offen' => TermLegacyValue::query()->open()->count(),
            'zuordnungen' => TermLegacyValue::RESOLUTIONS,
        ]);
    }

    /** Ausgewählte Werte zuordnen; je Begriff eine Revision. */
    public function resolve(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:5000'],
            'ids.*' => ['integer'],
            'zuordnung' => ['required', 'string', Rule::in(array_keys(TermLegacyValue::RESOLUTIONS))],
        ]);
        $action = (string) $data['zuordnung'];
        $values = TermLegacyValue::query()->open()->whereIn('id', $data['ids'])->with('term')->get();
        $user = $this->currentUserOrNull($request);

        $done = 0;
        $skipped = [];
        foreach ($values->groupBy('term_id') as $group) {
            /** @var TermLegacyValue $first */
            $first = $group->first();
            $term = $first->term->mergeTarget() ?? $first->term;
            $accepted = $group->filter(fn (TermLegacyValue $v): bool => in_array($action, self::ALLOWED[$v->field] ?? [], true));
            foreach ($group->diff($accepted) as $v) {
                $skipped[] = "{$v->value} ({$term->title()}): bei «".(TermLegacyValue::FIELDS[$v->field] ?? $v->field).'» nicht möglich';
            }
            if ($accepted->isEmpty()) {
                continue;
            }

            try {
                DB::transaction(function () use ($accepted, $term, $action, $user): void {
                    if ($action !== 'discarded') {
                        $this->glossary->save($term, $this->data($term, $accepted->all(), $action), $user, Gate::allows('edit-official'), null, 'updated', ['via' => 'altwerte']);
                    }
                    TermLegacyValue::query()->whereKey($accepted->modelKeys())->update([
                        'resolution' => $action, 'resolved_by' => $user?->id, 'resolved_at' => now(),
                    ]);
                });
                $done += $accepted->count();
            } catch (OfficialFieldLocked|InvalidArgumentException $e) {
                $skipped[] = "{$term->title()}: {$e->getMessage()}";
            }
        }

        return back()->with('status', "{$done} Altwerte zugeordnet (".TermLegacyValue::RESOLUTIONS[$action].').'
            .($done > 0 && $action !== 'discarded' ? ' Geänderte Begriffe sind Entwürfe – zum Veröffentlichen freigeben.' : '')
            .($skipped !== [] ? ' Nicht zugeordnet: '.implode('; ', array_slice($skipped, 0, 10)).(count($skipped) > 10 ? ' …' : '') : ''));
    }

    /** «Intern genutzt in» als CSV für die lokale Verwaltung im Intranet. */
    public function exportUsedIn(): StreamedResponse
    {
        $values = TermLegacyValue::query()->where('field', 'used_in')->with('term')->orderBy('term_id')->get();

        return response()->streamDownload(function () use ($values): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'Slug', 'Abkürzung offiziell', 'Bezeichnung offiziell', 'Intern genutzt in', 'Zuordnung'], ';', '"', '');
            foreach ($values as $v) {
                fputcsv($out, [
                    $v->term_id, $v->term->slug, Csv::safe((string) $v->term->official_abbreviation),
                    Csv::safe((string) $v->term->official_name), Csv::safe($v->value), (string) $v->resolution,
                ], ';', '"', '');
            }
            fclose($out);
        }, 'glossary-intern-genutzt-in-'.date('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Daten für GlossaryService::save aus den gewählten Altwerten eines Begriffs.
     *
     * @param  array<int, TermLegacyValue>  $values
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException wenn ein offizielles Feld schon anders gesetzt ist
     */
    private function data(Term $term, array $values, string $action): array
    {
        $term->loadMissing('aliases');
        $official = $term->aliases->where('alias_type', 'official')->pluck('alias')->all();
        $alternative = $term->aliases->where('alias_type', 'alternative')->pluck('alias')->all();
        $search = (string) $term->search_terms;
        $data = [];

        foreach ($values as $v) {
            if ($action === 'alternative') {
                $alternative[] = $v->value;
            } elseif ($action === 'search') {
                $search = trim($search.'; '.$v->value, '; ');
            } elseif ($v->field === 'own_alias') {
                $official[] = $v->value;
            } else {
                $field = $v->field === 'own_abbreviation' ? 'official_abbreviation' : 'official_name';
                $current = (string) ($data[$field] ?? $term->{$field});
                if ($current !== '' && $current !== $v->value) {
                    throw new InvalidArgumentException("offizielles Feld ist schon «{$current}» – «{$v->value}» als alternative Schreibweise übernehmen.");
                }
                $data[$field] = $v->value;
            }
        }

        return $data + match ($action) {
            'alternative' => ['alternative_aliases' => $alternative],
            'search' => ['search_terms' => $search],
            default => ['official_aliases' => $official],
        };
    }
}
