<?php

namespace App\Console\Commands;

use App\Enums\ResearchBasis;
use App\Enums\TermStatus;
use App\Glossary\ExcelFormat;
use App\Glossary\FieldGuide;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * KI-Anleitung (#481): erzeugt in docs/KI-ANLEITUNG.md den Abschnitt «Format» aus dem Code (Spalten aus
 * ExcelFormat, Erklärungen aus FieldGuide, Werte aus den Enums) und schreibt site/ki-anleitung.html für GitHub Pages.
 * Mit --check: Fehler, wenn die Dateien nicht dem Code entsprechen (Test und CI).
 */
class AiGuide extends Command
{
    protected $signature = 'glossary:ki-anleitung {--check : Fehler, wenn die Dateien nicht dem Code entsprechen}';

    protected $description = 'KI-Anleitung (docs/KI-ANLEITUNG.md, site/ki-anleitung.html) aus dem Code nachführen oder prüfen';

    public const PATH = 'docs/KI-ANLEITUNG.md';

    public const SITE = 'site/ki-anleitung.html';

    private const START = '<!-- generiert:anfang -->';

    private const END = '<!-- generiert:ende -->';

    public function handle(): int
    {
        $markdown = self::markdown((string) @file_get_contents(base_path(self::PATH)));
        $html = self::html($markdown);
        if ($this->option('check')) {
            $ok = (string) @file_get_contents(base_path(self::PATH)) === $markdown
                && (string) @file_get_contents(base_path(self::SITE)) === $html;
            if (! $ok) {
                $this->error(self::PATH.' bzw. '.self::SITE.' ist veraltet: php artisan glossary:ki-anleitung');

                return self::FAILURE;
            }
            $this->info(self::PATH.' ist aktuell.');

            return self::SUCCESS;
        }
        file_put_contents(base_path(self::PATH), $markdown);
        file_put_contents(base_path(self::SITE), $html);
        $this->info(self::PATH.' und '.self::SITE.' geschrieben.');

        return self::SUCCESS;
    }

    /** Anleitung mit neu erzeugtem Abschnitt zwischen den Markierungen. */
    public static function markdown(string $current): string
    {
        $start = strpos($current, self::START);
        $end = strpos($current, self::END);
        if ($start === false || $end === false || $end < $start) {
            return $current;
        }

        return substr($current, 0, $start).self::START."\n".self::generated().self::END.substr($current, $end + strlen(self::END));
    }

    /** Spalten, Werte und Regeln aus dem Code. */
    public static function generated(): string
    {
        $guide = [];
        foreach (FieldGuide::FIELDS as $f) {
            foreach (explode(' / ', (string) $f['excel']) as $column) {
                $guide[$column] ??= $f;
            }
        }
        $cell = fn (string $text): string => str_replace(['|', "\n"], ['\\|', ' '], $text);
        $lines = [
            'Excel-Format Version '.ExcelFormat::VERSION.' (`app/Glossary/ExcelFormat.php`). Erste Zeile = Spaltennamen genau wie unten;',
            'Spalten dürfen fehlen (sie bleiben dann unverändert), die Reihenfolge ist frei. Zeilen **ohne ID** sind neue',
            'Begriffe, Zeilen **mit ID** ändern den Begriff mit dieser ID. Gelöscht wird nie.',
            '',
            '| Spalte | Format | Beispiel | Pflicht |',
            '| --- | --- | --- | --- |',
        ];
        foreach (['ID', ...array_keys(ExcelFormat::COLUMNS)] as $column) {
            $f = $guide[$column] ?? ['format' => '', 'beispiel' => '', 'pflicht' => ''];
            $lines[] = '| '.$column.' | '.$cell((string) $f['format']).' | '.$cell((string) $f['beispiel']).' | '.$cell((string) $f['pflicht']).' |';
        }
        $translations = array_keys(ExcelFormat::translationColumns());
        $lines[] = '';
        $lines[] = 'Übersetzungen (freiwillig): '.implode(', ', array_map(fn (string $c): string => '«'.$c.'»', $translations)).'.';
        $lines[] = '';
        $lines[] = '- **Mehrfachwerte** («;»): '.implode(', ', array_map(fn (string $c): string => '«'.$c.'»', ExcelFormat::MULTI)).', dazu «Suchbegriffe».';
        $lines[] = '- **Status:** '.implode(' | ', TermStatus::labels()).' (leer = «'.TermStatus::Current->label().'»).';
        $lines[] = '- **Aktiv:** ja | nein (leer = ja).';
        $lines[] = '- **Recherche:** '.ResearchBasis::AiAssisted->label().' (der Import setzt nur diesen Wert; «'.ResearchBasis::HumanReviewed->label().'» ändert nichts).';
        $lines[] = '- **Datum:** JJJJ-MM-TT (auch TT.MM.JJJJ).';
        $lines[] = '- **Offizielle Spalten** ('.implode(', ', array_map(fn (string $c): string => '«'.$c.'»', ExcelFormat::OFFICIAL)).') bei bestehenden Begriffen nur mit dem Recht «Offizielle Felder ändern».';
        $lines[] = '- CSV: UTF-8, Trennzeichen Semikolon, gleiche Spaltennamen.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /** Einfache, eigenständige Seite für GitHub Pages. */
    public static function html(string $markdown): string
    {
        $body = (string) Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        return <<<HTML
<!doctype html>
<html lang="de-CH">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mit KI recherchieren – EduKSX-Glossary</title>
  <!-- Erzeugt mit php artisan glossary:ki-anleitung aus docs/KI-ANLEITUNG.md – nicht von Hand ändern -->
  <style>
    body{max-width:52rem;margin:2rem auto;padding:0 1rem;font:16px/1.55 system-ui,sans-serif;color:#1f2933}
    table{border-collapse:collapse;width:100%;font-size:14px}th,td{border:1px solid #d5dbe3;padding:.3rem .5rem;text-align:left;vertical-align:top}
    pre{background:#f4f6f8;padding:.75rem;overflow:auto;white-space:pre-wrap}code{font-size:.92em}a{color:#0272ad}
  </style>
</head>
<body>
<p><a href="index.html">← EduKSX-Glossary</a> · <a href="https://glossary.eduksx.ch/mit-ki-recherchieren/vorlage.xlsx">Vorlage (.xlsx)</a></p>
{$body}
</body>
</html>

HTML;
    }

    /** Prompt zum Kopieren (zwischen den Markierungen, ohne Code-Zaun). */
    public static function prompt(string $markdown): string
    {
        if (preg_match('/<!-- prompt:anfang -->\s*```text\n(.*?)```\s*<!-- prompt:ende -->/s', $markdown, $m) !== 1) {
            return '';
        }

        return trim($m[1]);
    }
}
