<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bewerbung um eine Rolle (#460), heute nur «moderator». Entscheiden dürfen nur Konten mit «manage-users».
 *
 * @property int $id
 * @property string $user_id
 * @property string $role
 * @property string $reason
 * @property string $status open, accepted, rejected
 * @property string|null $decided_by_id
 * @property CarbonImmutable|null $decided_at
 * @property string|null $decision_note
 * @property CarbonImmutable|null $created_at
 * @property-read User $user
 * @property-read User|null $decidedBy
 */
class RoleRequest extends Model
{
    public const OPEN = 'open';

    public const ACCEPTED = 'accepted';

    public const REJECTED = 'rejected';

    /** abgeschlossene Anfragen nach 24 Monaten löschen (Löschkonzept) */
    public const RETENTION_MONTHS = 24;

    protected $fillable = ['role', 'reason'];

    protected function casts(): array
    {
        return ['decided_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }
}
