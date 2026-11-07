<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Source;
use App\Models\Term;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Quellenprüfung (#69, docs/KONZEPT.md «Recherche, Quellen und Qualität»): Prüfdatum und Belegstatus je Herkunft,
 * jährliche Wiedervorlage. Redaktionelle Angaben ohne Revision; nicht öffentlich.
 */
class SourceCheckController extends Controller
{
    /** Belegstatus → Bezeichnung. */
    public const EVIDENCE = [
        'amtlich' => 'amtlich belegt',
        'belegt' => 'belegt (Fachquelle)',
        'sekundaer' => 'sekundär',
        'offen' => 'offen',
    ];

    /** Wiedervorlage nach Monaten. */
    public const MONTHS = 12;

    /** Begriffe zur Wiedervorlage: Prüfung fehlt oder älter als ein Jahr, Status «zu prüfen», offiziell ohne amtlichen Beleg. */
    public function index(): Response
    {
        $limit = CarbonImmutable::now()->subMonths(self::MONTHS);
        $rows = [];
        foreach (Term::query()->current()->with('sources')->get() as $term) {
            $reasons = [];
            if ($term->lifecycle_status === 'zu_pruefen') {
                $reasons[] = 'Status «zu prüfen»';
            }
            if ($term->sources->isEmpty()) {
                $reasons[] = 'keine Herkunft';
            }
            $oldest = null;
            foreach ($term->sources as $source) {
                $checked = $this->pivot($source, 'retrieved_at');
                $date = $checked !== null ? CarbonImmutable::parse($checked) : null;
                if ($date === null || $date->lessThan($limit)) {
                    $reasons[] = $date === null ? "{$source->displayLabel()}: nie geprüft" : "{$source->displayLabel()}: geprüft am {$date->format('d.m.Y')}";
                }
                if ($date !== null && ($oldest === null || $date->lessThan($oldest))) {
                    $oldest = $date;
                }
            }
            $official = ($term->official_abbreviation ?? '') !== '' || ($term->official_name ?? '') !== '';
            $amtlich = $term->sources->contains(fn (Source $s): bool => $this->pivot($s, 'evidence') === 'amtlich');
            if ($official && $term->sources->isNotEmpty() && ! $amtlich) {
                $reasons[] = 'offiziell ohne amtlichen Beleg';
            }
            if ($reasons !== []) {
                $rows[] = ['id' => $term->id, 'titel' => $term->title(), 'gruende' => array_values(array_unique($reasons)), 'aeltestes' => $oldest?->toDateString()];
            }
        }
        usort($rows, fn (array $a, array $b): int => strcmp((string) $a['aeltestes'], (string) $b['aeltestes']) ?: strcasecmp($a['titel'], $b['titel']));

        return Inertia::render('admin/source-check', ['begriffe' => $rows, 'monate' => self::MONTHS]);
    }

    /** Prüfdatum, Belegstatus und Hinweis je Herkunft eines Begriffs speichern. */
    public function update(Request $request, Term $term): RedirectResponse
    {
        $data = $request->validate([
            'herkunft' => ['required', 'array', 'max:50'],
            'herkunft.*.source_id' => ['required', 'uuid'],
            'herkunft.*.retrieved_at' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'herkunft.*.evidence' => ['nullable', 'string', Rule::in(array_keys(self::EVIDENCE))],
            'herkunft.*.note' => ['nullable', 'string', 'max:255'],
        ]);
        $ids = $term->sources()->pluck('sources.id')->map(fn ($id): string => (string) $id)->all();
        $count = 0;
        foreach ($data['herkunft'] as $row) {
            if (! in_array((string) $row['source_id'], $ids, true)) {
                continue;
            }
            $term->sources()->updateExistingPivot((string) $row['source_id'], [
                'retrieved_at' => $row['retrieved_at'] ?? null,
                'evidence' => $row['evidence'] ?? null,
                'note' => $row['note'] ?? null,
            ]);
            $count++;
        }

        return back()->with('status', "Prüfung von {$count} Herkünften gespeichert.");
    }

    private function pivot(Source $source, string $key): ?string
    {
        $pivot = $source->getRelationValue('pivot');
        $value = $pivot instanceof Pivot ? $pivot->getAttribute($key) : null;

        return is_scalar($value) ? substr((string) $value, 0, $key === 'retrieved_at' ? 10 : null) : null;
    }
}
