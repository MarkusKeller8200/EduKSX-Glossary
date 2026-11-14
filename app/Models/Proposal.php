<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Vorschlag (#113): neuer Begriff oder Korrektur. Nie öffentlich; ändern nur über App\Proposals\ProposalService.
 *
 * @property int $id
 * @property string $uuid öffentliche Kennung in der API (UUID v7, #322)
 * @property string $proposal_type new | change
 * @property string|null $term_id
 * @property int|null $base_revision
 * @property array<string, mixed> $changes
 * @property string|null $reason
 * @property string $status
 * @property string|null $author_id
 * @property string|null $service_account_id
 * @property string|null $organization_id Vorschlag eines Mitglieds einer Organisation (#148)
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
    /** Öffentliche Kennung (UUID v7) beim Anlegen setzen (#322). */
    protected static function booted(): void
    {
        static::creating(function (self $proposal): void {
            $proposal->uuid ??= (string) Str::uuid7();
        });
    }

    /** Offen (zurückziehen möglich), auch während der Prüfung durch die Organisation. */
    public const OPEN = ['organization_review', 'submitted', 'query'];

    /** In der Moderation der Redaktion (annehmen, ablehnen, Rückfrage). */
    public const MODERATION = ['submitted', 'query'];

    /** Wartet auf die Bestätigung durch einen Organisations-Admin (#148). */
    public const ORG_REVIEW = 'organization_review';

    public const STATUS = [
        'organization_review' => 'Prüfung durch Organisation',
        'submitted' => 'eingereicht',
        'query' => 'Rückfrage',
        'accepted' => 'angenommen',
        'rejected' => 'abgelehnt',
        'withdrawn' => 'zurückgezogen',
    ];

    /** Bezeichnung nach Ablauf der Frist bzw. nach Kontolöschung (DEC-024, LOESCHKONZEPT). */
    public const ANONYMOUS = 'Community';

    /** @var list<string> */
    protected $fillable = ['proposal_type', 'term_id', 'base_revision', 'changes', 'reason', 'status', 'author_id', 'service_account_id', 'author_label', 'organization_id'];

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

    public function inModeration(): bool
    {
        return in_array($this->status, self::MODERATION, true);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
