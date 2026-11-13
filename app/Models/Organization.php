<?php

namespace App\Models;

use App\Data\OptimisticLocking;
use App\Data\RecordsDataChanges;
use App\Data\Trashable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Organisation (#148, DEC-025): Konten mit gemeinsamer Darstellung der Begriffe. Ändern nur über
 * App\Organizations\OrganizationService.
 *
 * @property string $id
 * @property string $name
 * @property string|null $created_by_id
 * @property string|null $entra_tenant_id Microsoft-Tenant (X3, #284), eindeutig
 * @property CarbonImmutable|null $created_at
 * @property-read Collection<int, OrganizationMember> $members
 * @property-read Collection<int, OrganizationInvitation> $invitations
 * @property int $lock_version
 * @property CarbonImmutable|null $deleted_at
 * @property string|null $deleted_by_id
 */
class Organization extends Model
{
    use HasUuids;
    use OptimisticLocking, RecordsDataChanges, Trashable;

    /** @var list<string> */
    protected $fillable = ['name', 'created_by_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return HasMany<OrganizationMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    /** @return HasMany<OrganizationInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    /** @return HasMany<OrganizationTermOverride, $this> */
    public function overrides(): HasMany
    {
        return $this->hasMany(OrganizationTermOverride::class);
    }
}
