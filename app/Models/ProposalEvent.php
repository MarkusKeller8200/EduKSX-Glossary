<?php

namespace App\Models;

use App\Data\HasEventId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Ereignis eines Vorschlags (#113): Einreichen, Rückfrage, Antwort, Entscheid. Nicht öffentlich.
 *
 * @property int $id
 * @property string $event_id UUID v7 für die Zusammenführung über Module (#332)
 * @property int $proposal_id
 * @property string $event_type
 * @property string|null $actor_id
 * @property string|null $actor_label
 * @property bool $is_by_author
 * @property string|null $message
 * @property array<string, mixed>|null $details
 * @property CarbonImmutable $created_at
 */
class ProposalEvent extends Model
{
    use HasEventId;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['proposal_id', 'event_type', 'actor_id', 'actor_label', 'is_by_author', 'message', 'details'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['details' => 'array', 'is_by_author' => 'boolean', 'created_at' => 'immutable_datetime'];
    }
}
