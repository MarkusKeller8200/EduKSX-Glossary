<?php

namespace App\Glossary;

use App\Models\Category;
use App\Models\PublicationRelease;
use App\Models\Source;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Zwischenspeicher der öffentlichen Sicht (#137): Startseite, API und Exporte bauen den Stand nicht bei jedem Aufruf
 * aus allen Revisionen neu auf. Der Schlüssel enthält den aktuellen Datenstand (PublicationRelease, ändert bei jeder
 * Veröffentlichung, jedem Rückzug und Zusammenführen) und eine Prüfsumme der Listen Kategorien/Herkunft (Labels,
 * Farben). Ändert sich etwas davon, gilt automatisch ein neuer Schlüssel; alte Einträge laufen ab.
 */
final class PublicCache
{
    private const TTL = 3600;

    /** @var array<int, string> Schlüssel je Anfrage nur einmal berechnen */
    private array $keys = [];

    /**
     * @template T
     *
     * @param  Closure(): T  $build
     * @return T
     */
    public function remember(string $name, Closure $build): mixed
    {
        return Cache::remember($this->key().':'.$name, self::TTL, $build);
    }

    private function key(): string
    {
        $release = (int) PublicationRelease::query()->max('id');
        if (isset($this->keys[$release])) {
            return $this->keys[$release];
        }
        $lists = hash('sha256', (string) json_encode([
            Category::query()->orderBy('id')->get(['id', 'key', 'abbreviation', 'name', 'label', 'description', 'url', 'color', 'sort_order'])->toArray(),
            Source::query()->orderBy('id')->get(['id', 'key', 'abbreviation', 'name', 'label', 'description', 'url', 'color', 'sort_order'])->toArray(),
        ]));

        return $this->keys[$release] = 'glossary.public.'.$release.'.'.substr($lists, 0, 16);
    }
}
