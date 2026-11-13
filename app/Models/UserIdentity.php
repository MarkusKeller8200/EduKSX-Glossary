<?php

namespace App\Models;

use App\Data\RecordsDataChanges;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Verknüpfter Anmeldedienst eines Kontos (#45). Eindeutig über issuer + subject (W8, #277); tenant_id ist abgeleitet.
 *
 * @property int $id
 * @property string $user_id
 * @property string $provider
 * @property string $issuer
 * @property string $subject
 * @property string|null $tenant_id
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable|null $created_at
 * @property-read User $user
 */
class UserIdentity extends Model
{
    use RecordsDataChanges;

    /** Tenant der privaten Microsoft-Konten (kein Mandant einer Organisation). */
    public const MICROSOFT_CONSUMER_TENANT = '9188040d-6c67-4c5b-b112-36a304b66dad';

    /** Anzeigenamen der Anbieter. */
    public const PROVIDERS = ['microsoft' => 'Microsoft', 'edulog' => 'Edulog'];

    /** @var list<string> */
    protected $fillable = ['user_id', 'provider', 'issuer', 'subject', 'tenant_id', 'last_used_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['last_used_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
