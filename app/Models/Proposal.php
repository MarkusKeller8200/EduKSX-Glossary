<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Vorschlag (#113): neuer Begriff oder Korrektur. Nie öffentlich; ändern nur über App\Proposals\ProposalService.
 *
 * @property int $id
 * @property string $kind
 * @property string|null $term_id
 * @property int|null $base_revision
 * @property array<string, mixed> $changes
 * @property string|null $reason
 * @property string $status
 * @property string|null $user_id
 * @property string|null $service_account_id
 * @property string $author_label
 * @property string|null $result_term_id
 * @property int|null $result_revision
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable|null $created_at
 * @property-read Term|null $term
 * @property-read Collection<int, ProposalEvent> $events
 */
class Proposal extends Model
{
    public const OPEN = ['eingereicht', 'rueckfrage'];

    public const STATUS = [
        'eingereicht' => 'eingereicht',
        'rueckfrage' => 'Rückfrage',
        'angenommen' => 'angenommen',
        'abgelehnt' => 'abgelehnt',
        'zurueckgezogen' => 'zurückgezogen',
    ];

    /** Bezeichnung nach Ablauf der Frist bzw. nach Kontolöschung (DEC-024, LOESCHKONZEPT). */
    public const ANONYMOUS = 'Community';

    /** @var list<string> */
    protected $fillable = ['kind', 'term_id', 'base_revision', 'changes', 'reason', 'status', 'user_id', 'service_account_id', 'author_label'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array', 'base_revision' => 'integer', 'result_revision' => 'integer',
            'resolved_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /** @return HasMany<ProposalEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ProposalEvent::class)->orderBy('id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }
}
