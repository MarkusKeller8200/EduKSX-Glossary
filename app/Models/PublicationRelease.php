<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Datenstand: jede Veröffentlichung bzw. jeder Rückzug erhöht die Nummer (id). Grundlage für Sync und ETag
 * (/api/v1/releases/latest, docs/KONZEPT-API.md).
 *
 * @property int $id
 * @property string|null $term_id
 * @property string $action
 * @property string $checksum
 * @property int $term_count
 * @property string|null $published_by
 * @property \Carbon\CarbonImmutable $created_at
 */
class PublicationRelease extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['term_id', 'action', 'checksum', 'term_count', 'published_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['term_count' => 'integer', 'created_at' => 'immutable_datetime'];
    }
}
