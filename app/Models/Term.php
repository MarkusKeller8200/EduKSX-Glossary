<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Begriff (Arbeitsstand). Öffentlich ist nur die veröffentlichte Revision (publishedRevision), nie dieser Stand.
 * Ändern nur über App\Glossary\GlossaryService (Regeln, Revisionen, Verlauf).
 *
 * @property string $id
 * @property string $slug
 * @property string|null $official_abbreviation
 * @property string|null $official_name
 * @property string|null $short_description
 * @property string|null $description
 * @property string|null $note
 * @property string|null $url
 * @property string|null $search_terms
 * @property string $lifecycle_status
 * @property bool $is_active
 * @property CarbonImmutable|null $valid_from
 * @property CarbonImmutable|null $valid_until
 * @property int $revision
 * @property string|null $published_revision_id
 * @property string|null $merged_into_id
 * @property CarbonImmutable|null $merged_at
 * @property-read Term|null $mergedInto
 * @property-read TermRevision|null $publishedRevision
 * @property-read Collection<int, Category> $categories
 * @property-read Collection<int, Source> $sources
 * @property-read Collection<int, TermAlias> $aliases
 * @property-read Collection<int, TermRevision> $revisions
 * @property-read Collection<int, TermUserOverride> $overrides
 * @property-read Collection<int, TermLegacyValue> $legacyValues
 */
class Term extends Model
{
    use HasUuids;

    /** Status des Begriffs (nicht die Veröffentlichung). */
    public const STATUS = ['aktuell' => 'aktuell', 'historisch' => 'historisch', 'zu_pruefen' => 'zu prüfen'];

    /** Offizielle Felder: ändern nur mit Recht «edit-official» (leer → Wert setzen ist erlaubt, wie im Intranet). */
    public const OFFICIAL = ['official_abbreviation', 'official_name', 'official_aliases'];

    /** @var list<string> */
    protected $fillable = [
        'slug', 'official_abbreviation', 'official_name', 'short_description', 'description', 'note', 'url',
        'search_terms', 'lifecycle_status', 'is_active', 'valid_from', 'valid_until',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['lifecycle_status' => 'aktuell', 'is_active' => true, 'revision' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'valid_from' => 'immutable_date',
            'valid_until' => 'immutable_date',
            'revision' => 'integer',
            'merged_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'term_categories');
    }

    /** @return BelongsToMany<Source, $this> */
    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(Source::class, 'term_sources')->withPivot(['url', 'retrieved_at', 'note', 'evidence']);
    }

    /** @return HasMany<TermAlias, $this> */
    public function aliases(): HasMany
    {
        return $this->hasMany(TermAlias::class);
    }

    /** @return HasMany<TermRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(TermRevision::class)->orderBy('revision');
    }

    /** @return HasMany<TermUserOverride, $this> Persönliche Darstellungen (nur je Person auslesen) */
    public function overrides(): HasMany
    {
        return $this->hasMany(TermUserOverride::class);
    }

    /** @return HasMany<TermLegacyValue, $this> */
    public function legacyValues(): HasMany
    {
        return $this->hasMany(TermLegacyValue::class);
    }

    /** @return BelongsTo<TermRevision, $this> */
    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(TermRevision::class, 'published_revision_id');
    }

    /**
     * Veröffentlicht (es gibt eine freigegebene Revision). Ob der Begriff öffentlich angezeigt wird, entscheidet
     * «is_active» im veröffentlichten Abbild – nicht der Arbeitsstand (PublicGlossary, API).
     *
     * @param  Builder<Term>  $query
     * @return Builder<Term>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_revision_id');
    }

    /** @return BelongsTo<Term, $this> */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'merged_into_id');
    }

    /**
     * Nicht zusammengeführt (#16): zusammengeführte Begriffe sind nur noch Weiterleitung.
     *
     * @param  Builder<Term>  $query
     * @return Builder<Term>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('merged_into_id');
    }

    /** Verbleibender Begriff nach einer Zusammenführung (folgt Ketten); null, wenn nicht zusammengeführt. */
    public function mergeTarget(): ?Term
    {
        $target = $this;
        for ($i = 0; $target->merged_into_id !== null && $i < 20; $i++) {
            $next = Term::query()->find($target->merged_into_id);
            if ($next === null) {
                return null;
            }
            $target = $next;
        }

        return $target === $this ? null : $target;
    }

    /** Angezeigte Abkürzung: die gemeinsame (DEC-020; persönliche Darstellung nur für die Person selbst). */
    public function displayAbbreviation(): string
    {
        return (string) $this->official_abbreviation;
    }

    /** Angezeigte Bezeichnung: die gemeinsame (DEC-020). */
    public function displayName(): string
    {
        return (string) $this->official_name;
    }

    /** Titel «Abkürzung – Bezeichnung». */
    public function title(): string
    {
        return trim($this->displayAbbreviation().' – '.$this->displayName(), ' –');
    }

    /** Arbeitsstand weicht von der veröffentlichten Revision ab. */
    public function hasUnpublishedChanges(): bool
    {
        return $this->publishedRevision === null || $this->publishedRevision->revision !== $this->revision;
    }
}
