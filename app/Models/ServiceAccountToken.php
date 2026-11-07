<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Token eines Servicekontos (#47): nur der SHA-256-Hash wird gespeichert, der Klartext einmal angezeigt.
 *
 * @property string $id
 * @property string $service_account_id
 * @property string $name
 * @property string $token_hash
 * @property string $prefix
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $created_at
 * @property-read ServiceAccount $account
 */
class ServiceAccountToken extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = ['name', 'token_hash', 'prefix', 'expires_at'];

    /** @var list<string> */
    protected $hidden = ['token_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime', 'last_used_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<ServiceAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ServiceAccount::class, 'service_account_id');
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
