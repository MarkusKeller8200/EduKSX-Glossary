<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Darstellung eines Begriffs für eine Organisation (#148, DEC-025): für alle aktiven Mitglieder, nie in Revisionen,
 * Datenständen, öffentlicher API, GitHub Pages oder Confluence; nur über die Organisations-API. NULL = keine Anpassung.
 * Wird mit der Organisation gelöscht.
 *
 * @property string $id
 * @property string $term_id
 * @property string $organization_id
 * @property string|null $updated_by
 * @property string|null $abbreviation_override
 * @property string|null $name_override
 * @property string|null $short_description_override
 * @property string|null $description_override
 * @property list<string>|null $aliases_override
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class OrganizationTermOverride extends Model
{
    use HasUuids;

    /** Felder wie bei der persönlichen Darstellung. */
    public const FIELDS = TermUserOverride::FIELDS;

    /** @var list<string> */
    protected $fillable = self::FIELDS;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aliases_override' => 'array',
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

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
