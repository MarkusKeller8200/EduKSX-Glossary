<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Servicekonto (#47, KONZEPT-BENUTZER 2.2): technische Integration ohne Login. Ändern nur über
 * App\Access\Service\ServiceAccountManager.
 *
 * @property string $id
 * @property string $name
 * @property string $purpose
 * @property string|null $responsible_user_id
 * @property string $status
 * @property list<string> $scopes
 * @property CarbonImmutable|null $created_at
 * @property-read User|null $responsible
 * @property-read Collection<int, ServiceAccountToken> $tokens
 */
class ServiceAccount extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = ['name', 'purpose', 'responsible_user_id', 'status', 'scopes'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['scopes' => 'array', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** @return HasMany<ServiceAccountToken, $this> */
    public function tokens(): HasMany
    {
        return $this->hasMany(ServiceAccountToken::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }
}
