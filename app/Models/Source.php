<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Herkunft/Quelleninstitution (erweiterbare Liste, z. B. VSA) – Felder wie die Listen im Intranet-Glossar.
 *
 * @property string $id
 * @property string $key
 * @property string|null $abbreviation
 * @property string $name
 * @property string|null $label
 * @property string|null $description
 * @property string|null $url
 * @property string|null $note
 * @property string|null $color
 * @property int $sort_order
 */
class Source extends Model
{
    use HasUuids;

    protected $table = 'sources';

    /** @var list<string> */
    protected $fillable = ['key', 'abbreviation', 'name', 'label', 'description', 'url', 'note', 'color', 'sort_order'];

    /** @return BelongsToMany<Term, $this> */
    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(Term::class, 'term_sources');
    }

    /** Kurzes Label für Tags und Excel: Label, sonst Kürzel, sonst Schlüssel. */
    public function displayLabel(): string
    {
        return (string) ($this->label ?: ($this->abbreviation ?: $this->key));
    }

    /** @return HasMany<SourceTranslation, $this> Übersetzungen fr/it/en (X1, #282) */
    public function translations(): HasMany
    {
        return $this->hasMany(SourceTranslation::class);
    }
}
