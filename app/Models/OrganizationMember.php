<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mitgliedschaft in einer Organisation (#148): höchstens eine je Konto.
 *
 * @property int $id
 * @property string $organization_id
 * @property string $user_id
 * @property string $role admin, member
 * @property string $status active, blocked
 * @property bool $is_role_granted globale Rolle «Organisations-Admin» durch die Organisation vergeben
 * @property CarbonImmutable|null $created_at
 * @property-read Organization $organization
 * @property-read User $user
 */
class OrganizationMember extends Model
{
    public const ADMIN = 'admin';

    public const MEMBER = 'member';

    /** @var list<string> */
    protected $fillable = ['organization_id', 'user_id', 'role', 'status', 'is_role_granted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_role_granted' => 'boolean', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ADMIN;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
