<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Ereignis eines Vorschlags (#113): Einreichen, Rückfrage, Antwort, Entscheid. Nicht öffentlich.
 *
 * @property int $id
 * @property int $proposal_id
 * @property string $type
 * @property string|null $actor_id
 * @property string|null $actor_label
 * @property bool $by_author
 * @property string|null $message
 * @property array<string, mixed>|null $details
 * @property CarbonImmutable $created_at
 */
class ProposalEvent extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['proposal_id', 'type', 'actor_id', 'actor_label', 'by_author', 'message', 'details'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['details' => 'array', 'by_author' => 'boolean', 'created_at' => 'immutable_datetime'];
    }
}
