<?php

namespace App\Console\Commands;

use App\Glossary\ApiPresenter;
use Illuminate\Console\Command;

/**
 * Reduzierte Lesefassung für GitHub Pages (#27, DEC-013): veröffentlichte Begriffe nach site/begriffe.json schreiben
 * (gleiche Felder wie /api/v1/exports). Danach committen und pushen – der Workflow «pages» veröffentlicht.
 */
class ExportPages extends Command
{
    /** Felder der Lesefassung – nur aus ApiPresenter::PUBLIC_TERM_FIELDS (#43). */
    public const FIELDS = ['id', 'slug', 'abbreviation', 'name', 'aliases', 'short_description', 'categories', 'status', 'url'];

    protected $signature = 'glossary:export-pages {--path=site/begriffe.json : Zieldatei}';

    protected $description = 'Veröffentlichte Begriffe für GitHub Pages exportieren (site/begriffe.json)';

    public function handle(ApiPresenter $presenter): int
    {
        $release = $presenter->release();
        $data = [
            'release' => $release['release'],
            'checksum' => $release['checksum'],
            'published_at' => $release['published_at'],
            'version' => (string) config('glossary.version'),
            'full_url' => 'https://glossary.eduksx.ch',
            'license' => $presenter->license(),
            'categories' => $presenter->categoryList(),
            // Teilmenge der öffentlichen Felder (ApiPresenter::PUBLIC_TERM_FIELDS)
            'terms' => array_map(fn (array $t): array => array_intersect_key($t, array_flip(self::FIELDS)), $presenter->terms()),
        ];
        $path = base_path((string) $this->option('path'));
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        $this->info(count($data['terms'])." Begriffe (Datenstand {$release['release']}) nach {$this->option('path')} geschrieben.");

        return self::SUCCESS;
    }
}
