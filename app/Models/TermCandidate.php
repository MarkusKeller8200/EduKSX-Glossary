<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kandidat (DEC-039, DEC-042, #480): gemeldetes Wort – fehlt im Glossary («missing») oder Erklärung eines bekannten
 * Begriffs falsch («wrong»). Wird erst durch die Redaktion zu einem Begriff; nichts davon ist öffentlich.
 *
 * @property string $id
 * @property string $word
 * @property string $normalized
 * @property string $kind missing, wrong
 * @property string $channel extension, embed, text, search
 * @property string|null $term_id
 * @property int $count
 * @property string|null $first_url
 * @property string|null $last_url
 * @property list<string>|null $comments
 * @property string $status new, accepted, alias, rejected
 * @property string|null $reason
 * @property string|null $result_term_id
 * @property string|null $reported_by_id
 * @property string|null $decided_by_id
 * @property CarbonImmutable|null $decided_at
 * @property CarbonImmutable|null $last_seen_at
 * @property CarbonImmutable|null $created_at
 * @property-read Term|null $term
 */
class TermCandidate extends Model
{
    use HasUuids;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'new', 'count' => 1];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'comments' => 'array',
            'count' => 'integer',
            'decided_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}
