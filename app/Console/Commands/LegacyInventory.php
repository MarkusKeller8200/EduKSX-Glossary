<?php

namespace App\Console\Commands;

use App\Models\Term;
use App\Models\TermLegacyValue;
use App\Models\TermRevision;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bestand der Altwerte (DEC-020, #80): eigene Abkürzungen, Bezeichnungen, Aliase und «Intern genutzt in», die bei
 * der Migration bzw. beim Import gesichert wurden. Zeigt Anzahlen (offen/zugeordnet), Begriffe ohne gemeinsame
 * Abkürzung und Bezeichnung sowie Revisionen mit Altfeldern; schreibt auf Wunsch eine CSV und eine Sicherung.
 */
class LegacyInventory extends Command
{
    protected $signature = 'glossary:bestand-eigene
        {--csv= : Alle Altwerte als CSV in diese Datei schreiben}
        {--sicherung= : Vorher eine konsistente Kopie der SQLite-Datenbank in diese Datei schreiben (VACUUM INTO)}';

    protected $description = 'Bestand der eigenen Werte und von «Intern genutzt in» (Altwerte) anzeigen';

    public function handle(): int
    {
        $backup = (string) $this->option('sicherung');
        if ($backup !== '') {
            if (DB::connection()->getDriverName() !== 'sqlite') {
                $this->error('Sicherung nur für SQLite.');

                return self::FAILURE;
            }
            if (file_exists($backup)) {
                $this->error("{$backup} gibt es schon.");

                return self::FAILURE;
            }
            DB::statement('VACUUM INTO ?', [$backup]);
            $this->info("Sicherung geschrieben: {$backup}");
        }

        $rows = [];
        foreach (TermLegacyValue::FIELDS as $field => $label) {
            $base = TermLegacyValue::query()->where('field', $field);
            $rows[] = [
                $label,
                (clone $base)->count(),
                (clone $base)->whereNull('resolution')->count(),
                (clone $base)->distinct()->count('term_id'),
            ];
        }
        $this->table(['Feld', 'Werte', 'offen', 'Begriffe'], $rows);

        $resolved = TermLegacyValue::query()->whereNotNull('resolution')->selectRaw('resolution, count(*) as n')->groupBy('resolution')->pluck('n', 'resolution')->all();
        foreach (TermLegacyValue::RESOLUTIONS as $key => $label) {
            $this->line(sprintf('  %-45s %d', ucfirst($label), (int) ($resolved[$key] ?? 0)));
        }

        $nameless = Term::query()->current()->whereNull('official_abbreviation')->whereNull('official_name')->count();
        $noAbbreviation = Term::query()->current()->whereNull('official_abbreviation')
            ->whereHas('legacyValues', fn ($q) => $q->where('field', 'own_abbreviation')->whereNull('resolution'))->count();
        $this->newLine();
        $this->line("Begriffe ohne gemeinsame Abkürzung und Bezeichnung: {$nameless}");
        $this->line("Begriffe ohne gemeinsame Abkürzung, aber mit offener eigener Abkürzung: {$noAbbreviation}");

        $old = TermRevision::query()->where(fn ($q) => $q
            ->where('snapshot', 'like', '%"own_abbreviation":"%')
            ->orWhere('snapshot', 'like', '%"own_name":"%')
            ->orWhere('snapshot', 'like', '%"own_alias":"%')
            ->orWhere('snapshot', 'like', '%"used_in":["%'))->count();
        $this->line("Revisionen mit Altfeldern im Abbild (bleiben unverändert, werden nie ausgegeben): {$old}");

        $csv = (string) $this->option('csv');
        if ($csv !== '') {
            $out = fopen($csv, 'x');
            if ($out === false) {
                $this->error("{$csv} kann nicht angelegt werden (gibt es die Datei schon?).");

                return self::FAILURE;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'Slug', 'Abkürzung offiziell', 'Bezeichnung offiziell', 'Feld', 'Wert', 'Herkunft', 'Zuordnung'], ';', '"', '');
            foreach (TermLegacyValue::query()->with('term')->orderBy('term_id')->orderBy('field')->cursor() as $v) {
                fputcsv($out, [
                    $v->term_id, $v->term->slug, (string) $v->term->official_abbreviation, (string) $v->term->official_name,
                    TermLegacyValue::FIELDS[$v->field] ?? $v->field, $v->value, $v->origin, (string) $v->resolution,
                ], ';', '"', '');
            }
            fclose($out);
            $this->info("CSV geschrieben: {$csv}");
        }

        return self::SUCCESS;
    }
}
