<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Übersetzung eines Begriffs (X1, #282): fr, it oder en. Teil der Revision; gespeichert über GlossaryService.
 *
 * @property int $id
 * @property string $term_id
 * @property string $locale
 * @property string|null $abbreviation
 * @property string|null $name
 * @property string|null $short_description
 * @property string|null $description
 */
class TermTranslation extends Model
{
    /** Übersetzbare Felder. */
    public const FIELDS = ['abbreviation', 'name', 'short_description', 'description'];

    /** @var list<string> */
    protected $fillable = ['locale', 'abbreviation', 'name', 'short_description', 'description'];

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}
