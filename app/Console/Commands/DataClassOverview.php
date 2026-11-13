<?php

namespace App\Console\Commands;

use App\Data\DataClass;
use App\Data\DataClasses;
use Illuminate\Console\Command;

/** Übersicht der Datenklassen je Spalte aus dem Code erzeugen (W7, #276): docs/DATENKLASSEN.md. */
class DataClassOverview extends Command
{
    protected $signature = 'glossary:datenklassen {--check : Fehler, wenn die Datei nicht dem Code entspricht}';

    protected $description = 'Übersicht der Datenklassen je Spalte (docs/DATENKLASSEN.md) erzeugen oder prüfen';

    public function handle(): int
    {
        $path = base_path('docs/DATENKLASSEN.md');
        $content = self::render();
        if ($this->option('check')) {
            if ((string) @file_get_contents($path) !== $content) {
                $this->error('docs/DATENKLASSEN.md ist veraltet: php artisan glossary:datenklassen');

                return self::FAILURE;
            }
            $this->info('docs/DATENKLASSEN.md ist aktuell.');

            return self::SUCCESS;
        }
        file_put_contents($path, $content);
        $this->info('docs/DATENKLASSEN.md geschrieben.');

        return self::SUCCESS;
    }

    public static function render(): string
    {
        $lines = [
            '# Datenklassen je Spalte',
            '',
            'Erzeugt aus `app/Data/DataClasses.php` mit `php artisan glossary:datenklassen` – nicht von Hand ändern.',
            'Regeln nach dem DB-Standard (B2): In der Historie (`data_changes`) stehen Werte nur bei den Klassen öffentlich',
            'und intern, sonst nur «changed». Abbilder (Revisionen) enthalten nur Felder der Klassen öffentlich und intern.',
            '',
            '| Klasse | In der Historie | Beim Löschen der Person |',
            '| --- | --- | --- |',
        ];
        foreach (DataClass::cases() as $class) {
            $lines[] = '| '.$class->label().' | '.($class->keepsValues() ? 'alter und neuer Wert' : 'nur «changed»').' | '.$class->onPersonDeleted().' |';
        }
        foreach (DataClasses::MAP as $table => $columns) {
            $lines[] = '';
            $lines[] = '## `'.$table.'`';
            $lines[] = '';
            $lines[] = '| Spalte | Klasse |';
            $lines[] = '| --- | --- |';
            foreach ($columns as $column => $class) {
                $lines[] = '| `'.$column.'` | '.$class->label().' |';
            }
        }

        return implode("\n", $lines)."\n";
    }
}
