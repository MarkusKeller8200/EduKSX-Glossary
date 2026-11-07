<?php

namespace App\Glossary;

use App\Models\Category;
use App\Models\Source;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Excel-Import (#13, #15) wie im Intranet: Zeile ohne ID → neu, mit bekannter ID → ändern, mit unbekannter ID →
 * neu mit dieser ID (ID aus dem Intranet bleibt erhalten). Nie löschen. Importierte Begriffe sind Entwürfe.
 *
 * Vorschau und Übernahme laufen durch denselben Code: Die Vorschau führt den Import in einer Transaktion aus und
 * nimmt sie zurück – so meldet sie genau die Fehler und Änderungen, die beim Übernehmen entstehen.
 */
final class GlossaryImporter
{
    public function __construct(private readonly GlossaryService $glossary) {}

    /**
     * @param  list<array<string, string>>  $rows  Zeilen mit Spaltennamen (SpreadsheetReader)
     * @return array{plan: list<array{zeile: int, art: string, id: string, titel: string, meldungen: list<string>, aenderungen: array<string, string>}>, neu: int, geaendert: int, fehler: int, listen: array{Kategorien: list<string>, Herkunft: list<string>}}
     */
    public function run(array $rows, bool $createLists, ?User $user, bool $canEditOfficial, bool $dryRun): array
    {
        $plan = [];
        $created = ['Kategorien' => [], 'Herkunft' => []];

        DB::beginTransaction();
        try {
            foreach ($rows as $n => $row) {
                $line = $n + 2;   // Zeile 1 = Überschrift
                $id = trim($row['ID'] ?? '');
                $data = ExcelFormat::data($row);
                $abk = (string) (($data['own_abbreviation'] ?? '') ?: ($data['official_abbreviation'] ?? ''));
                $bez = (string) (($data['own_name'] ?? '') ?: ($data['official_name'] ?? ''));
                $titel = trim($abk.' – '.$bez, ' –');

                try {
                    $missing = $this->missingLists($data);
                    if ($missing !== [] && ! $createLists) {
                        throw new InvalidArgumentException('Unbekannt: '.implode(', ', $missing).' (beim Import neu anlegen lassen oder zuerst die Liste ergänzen).');
                    }

                    $result = DB::transaction(function () use ($data, $id, $user, $canEditOfficial, &$created): array {
                        foreach ($this->createMissingLists($data) as $list => $names) {
                            $created[$list] = [...$created[$list], ...$names];
                        }
                        $term = $id !== '' ? Term::query()->find($id) : null;
                        $before = $term !== null ? ExcelFormat::normalize(ExcelFormat::row($term)) : [];
                        if ($term === null && $id !== '') {
                            $data['id'] = $id;
                        }
                        $saved = $this->glossary->save($term, $data, $user, $canEditOfficial || $term === null, 'Excel-Import');
                        $after = ExcelFormat::normalize(ExcelFormat::row($saved->refresh()));

                        $changes = array_diff_assoc($after, $before);
                        if ($term === null) {
                            $changes = array_filter($changes, fn (string $v): bool => $v !== '');
                        }

                        return [$term === null ? 'neu' : 'geaendert', $changes, $saved->id];
                    });
                    [$art, $changes, $savedId] = $result;
                    if ($art === 'geaendert' && $changes === []) {
                        continue;   // unverändert → nicht anzeigen
                    }
                    $plan[] = ['zeile' => $line, 'art' => $art, 'id' => $savedId, 'titel' => $titel, 'meldungen' => [], 'aenderungen' => $changes];
                } catch (OfficialFieldLocked $e) {
                    $plan[] = ['zeile' => $line, 'art' => 'fehler', 'id' => $id, 'titel' => $titel, 'meldungen' => [$e->getMessage()], 'aenderungen' => []];
                } catch (InvalidArgumentException $e) {
                    $plan[] = ['zeile' => $line, 'art' => 'fehler', 'id' => $id, 'titel' => $titel, 'meldungen' => [$e->getMessage()], 'aenderungen' => []];
                }
            }
            $dryRun ? DB::rollBack() : DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $count = fn (string $art): int => count(array_filter($plan, fn (array $p): bool => $p['art'] === $art));

        return [
            'plan' => $plan,
            'neu' => $count('neu'),
            'geaendert' => $count('geaendert'),
            'fehler' => $count('fehler'),
            'listen' => ['Kategorien' => array_values(array_unique($created['Kategorien'])), 'Herkunft' => array_values(array_unique($created['Herkunft']))],
        ];
    }

    /**
     * Unbekannte Einträge der Listen in dieser Zeile.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function missingLists(array $data): array
    {
        $missing = [];
        foreach ([['categories', Category::class, 'Kategorie'], ['sources', Source::class, 'Herkunft']] as [$field, $model, $what]) {
            foreach ((array) ($data[$field] ?? []) as $ref) {
                if (! $this->exists($model, (string) $ref)) {
                    $missing[] = "{$what} «{$ref}»";
                }
            }
        }

        return $missing;
    }

    /**
     * Fehlende Kategorien/Herkünfte anlegen (Kürzel = Label = Bezeichnung = Wert aus der Datei).
     *
     * @param  array<string, mixed>  $data
     * @return array{Kategorien: list<string>, Herkunft: list<string>}
     */
    private function createMissingLists(array $data): array
    {
        $created = ['Kategorien' => [], 'Herkunft' => []];
        foreach ([['categories', Category::class, 'Kategorien'], ['sources', Source::class, 'Herkunft']] as [$field, $model, $list]) {
            foreach ((array) ($data[$field] ?? []) as $ref) {
                $ref = (string) $ref;
                if ($this->exists($model, $ref)) {
                    continue;
                }
                $key = Str::slug($ref, '-', 'de') ?: Str::lower(Str::random(6));
                for ($i = 2; $model::query()->where('key', $key)->exists(); $i++) {
                    $key = Str::slug($ref, '-', 'de').'-'.$i;
                }
                $model::query()->create([
                    'key' => $key,
                    'abbreviation' => mb_strlen($ref) <= 30 ? $ref : null,
                    'name' => $ref,
                    'label' => mb_strlen($ref) <= 60 ? $ref : null,
                    'sort_order' => (int) $model::query()->max('sort_order') + 1,
                ]);
                $created[$list][] = $ref;
            }
        }

        return $created;
    }

    /** @param  class-string<Category|Source>  $model */
    private function exists(string $model, string $ref): bool
    {
        return $model::query()
            ->where('key', $ref)->orWhere('abbreviation', $ref)->orWhere('label', $ref)->orWhere('name', $ref)
            ->exists();
    }
}
