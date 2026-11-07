<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gesicherter Altwert aus dem Intranet-Format (DEC-020): eigene Abkürzung, eigene Bezeichnung, eigener Alias oder
 * «Intern genutzt in». Kein gemeinsames Fachdatum; die Redaktion ordnet ihn zu (offiziell, alternative
 * Schreibweise, Suchbegriff) oder verwirft ihn. Nie öffentlich.
 *
 * @property int $id
 * @property string $term_id
 * @property string $field
 * @property string $value
 * @property string $origin
 * @property string|null $resolution
 * @property string|null $resolved_by
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable $created_at
 * @property-read Term $term
 */
class TermLegacyValue extends Model
{
    public const UPDATED_AT = null;

    /** Feld → Bezeichnung (Spalten des alten Intranet-Formats). */
    public const FIELDS = [
        'own_abbreviation' => 'Abkürzung eigene',
        'own_alias' => 'Alias eigene',
        'own_name' => 'Bezeichnung eigene',
        'used_in' => 'Intern genutzt in',
    ];

    /** Zuordnung → Bezeichnung. */
    public const RESOLUTIONS = [
        'official' => 'als offizielle Angabe übernommen',
        'alternative' => 'als alternative Schreibweise übernommen',
        'search' => 'als Suchbegriff übernommen',
        'discarded' => 'verworfen',
    ];

    /** @var list<string> */
    protected $fillable = ['term_id', 'field', 'value', 'origin', 'resolution', 'resolved_by', 'resolved_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['resolved_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    /**
     * Noch nicht zugeordnet.
     *
     * @param  Builder<TermLegacyValue>  $query
     * @return Builder<TermLegacyValue>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolution');
    }

    /** Altwert sichern (doppelte Werte je Begriff und Feld werden nur einmal gespeichert). */
    public static function remember(string $termId, string $field, string $value, string $origin): bool
    {
        $value = trim($value);
        if ($value === '' || ! array_key_exists($field, self::FIELDS)) {
            return false;
        }

        return static::query()->insertOrIgnore([
            'term_id' => $termId, 'field' => $field, 'value' => mb_substr($value, 0, 500), 'origin' => $origin,
            'created_at' => now(),
        ]) > 0;
    }

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}
