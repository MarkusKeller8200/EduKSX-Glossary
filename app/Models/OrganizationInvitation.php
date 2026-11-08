<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Einladung in eine Organisation (#148): je E-Mail-Adresse, 14 Tage gültig. Das Konto mit dieser Adresse nimmt sie
 * unter «Meine Organisation» aktiv an (nur ohne andere Mitgliedschaft) oder lehnt sie ab.
 *
 * @property int $id
 * @property string $organization_id
 * @property string $email
 * @property string|null $invited_by
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $created_at
 * @property-read Organization $organization
 */
class OrganizationInvitation extends Model
{
    public const UPDATED_AT = null;

    public const DAYS = 14;

    /** @var list<string> */
    protected $fillable = ['organization_id', 'email', 'invited_by', 'expires_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isValid(): bool
    {
        return $this->expires_at->isFuture();
    }
}
