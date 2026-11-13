<?php

namespace App\Models;

use App\Data\RecordsDataChanges;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persönliche Darstellung eines Begriffs (DEC-020): nur für die Person selbst, nie in Revisionen, Datenständen,
 * API, Export, GitHub Pages oder Confluence. NULL = keine Anpassung (gemeinsamer Wert gilt). Wird mit dem Konto
 * gelöscht.
 *
 * Seit #148 nur für Mitglieder einer Organisation und erst nach Freigabe durch einen Organisations-Admin dieser
 * Organisation (approved_organization_id); eigene Texte der Organisations-Admins gelten sofort.
 *
 * @property string $id
 * @property string $term_id
 * @property string $user_id
 * @property string|null $abbreviation_override
 * @property string|null $name_override
 * @property string|null $short_description_override
 * @property string|null $description_override
 * @property list<string>|null $aliases_override
 * @property string|null $approved_organization_id freigegeben für diese Organisation; NULL = wartet
 * @property string|null $approved_by_id
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class TermUserOverride extends Model
{
    use HasUuids;
    use RecordsDataChanges;

    /** Felder der persönlichen Darstellung. */
    public const FIELDS = [
        'abbreviation_override', 'name_override', 'short_description_override', 'description_override', 'aliases_override',
    ];

    /** @var list<string> */
    protected $fillable = self::FIELDS;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aliases_override' => 'array',
            'approved_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** Keine einzige Anpassung gesetzt. */
    public function isEmpty(): bool
    {
        foreach (self::FIELDS as $field) {
            if ($this->{$field} !== null && $this->{$field} !== '' && $this->{$field} !== []) {
                return false;
            }
        }

        return true;
    }

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /** Für die Organisation $organizationId freigegeben. */
    public function isApprovedFor(?string $organizationId): bool
    {
        return $organizationId !== null && $this->approved_organization_id === $organizationId;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
