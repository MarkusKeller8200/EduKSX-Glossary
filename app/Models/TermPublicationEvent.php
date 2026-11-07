<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Ereignis im Change-Feed (#25): Veröffentlichung oder Rückzug eines Begriffs. Die ID ist der Cursor.
 *
 * @property int $id
 * @property string $term_id
 * @property string $action
 * @property int|null $revision
 * @property int|null $release_id
 * @property CarbonImmutable $created_at
 */
class TermPublicationEvent extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['term_id', 'action', 'revision', 'release_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['revision' => 'integer', 'release_id' => 'integer', 'created_at' => 'immutable_datetime'];
    }
}
