<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Entscheid «als mehrdeutig belassen» für eine Gruppe möglicher Doppelter (#16). Gilt nur für genau diese
 * Mitglieder: kommt ein Begriff dazu, wird die Gruppe wieder vorgeschlagen.
 *
 * @property int $id
 * @property string $signature
 * @property string $match_type abbreviation | name | both
 * @property string $value
 * @property list<string> $term_ids
 * @property string|null $decided_by_id
 * @property CarbonImmutable $created_at
 */
class DuplicateDecision extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['signature', 'match_type', 'value', 'term_ids', 'decided_by_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['term_ids' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
