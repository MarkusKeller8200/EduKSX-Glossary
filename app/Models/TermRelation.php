<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Beziehung zu einem anderen Begriff (X2, #283). Teil der Revision; gespeichert über GlossaryService.
 *
 * @property int $id
 * @property string $term_id
 * @property string $related_term_id
 * @property string $relation_type
 */
class TermRelation extends Model
{
    /** @var list<string> */
    protected $fillable = ['related_term_id', 'relation_type'];

    /** @return BelongsTo<Term, $this> */
    public function relatedTerm(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'related_term_id');
    }
}
