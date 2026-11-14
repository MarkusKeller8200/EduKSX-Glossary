<?php

namespace App\Models;

use App\Data\HasEventId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Ereignis im Change-Feed (#25): Veröffentlichung, Rückzug oder Zusammenführung (#16) eines Begriffs. Die ID ist der Cursor.
 * Akteur (actor_*) nur intern, nie im Change-Feed.
 *
 * @property int $id
 * @property string $event_id UUID v7 für die Zusammenführung über Module (#332)
 * @property string $term_id
 * @property string $action
 * @property int|null $revision
 * @property int|null $release_id
 * @property string|null $merged_into_id
 * @property string|null $actor_type
 * @property string|null $actor_label
 * @property string|null $actor_id
 * @property CarbonImmutable $created_at
 */
class TermPublicationEvent extends Model
{
    use HasEventId;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'term_id', 'action', 'revision', 'release_id', 'merged_into_id', 'actor_type', 'actor_label', 'actor_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['revision' => 'integer', 'release_id' => 'integer', 'created_at' => 'immutable_datetime'];
    }
}
