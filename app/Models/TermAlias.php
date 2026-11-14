<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Schreibweise eines Begriffs: offizielle Aliase, gemeinsam freigegebene alternative Schreibweise
 * oder Suchbegriff (DEC-020; persönliche Aliase stehen in TermUserOverride). Nicht eindeutig – mehrdeutige
 * Abkürzungen sind erlaubt.
 *
 * @property int $id
 * @property string $term_id
 * @property string $alias
 * @property string $alias_type
 * @property string $locale
 */
class TermAlias extends Model
{
    public const TYPES = ['official', 'alternative', 'search'];

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['alias', 'alias_type', 'locale'];

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}
