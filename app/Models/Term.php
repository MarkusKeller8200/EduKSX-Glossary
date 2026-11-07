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
 * @property string|null $own_abbreviation
 * @property string|null $official_name
 * @property string|null $own_name
 * @property string|null $short_description
 * @property string|null $description
 * @property string|null $note
 * @property string|null $url
 * @property list<string>|null $used_in
 * @property string|null $search_terms
 * @property string $lifecycle_status
 * @property bool $is_active
 * @property CarbonImmutable|null $valid_from
 * @property CarbonImmutable|null $valid_until
 * @property int $revision
 * @property string|null $published_revision_id
 * @property-read TermRevision|null $publishedRevision
 * @property-read Collection<int, Category> $categories
 * @property-read Collection<int, Source> $sources
 * @property-read Collection<int, TermAlias> $aliases
 * @property-read Collection<int, TermRevision> $revisions
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
        'slug', 'official_abbreviation', 'own_abbreviation', 'official_name', 'own_name', 'short_description',
        'description', 'note', 'url', 'used_in', 'search_terms', 'lifecycle_status', 'is_active', 'valid_from',
        'valid_until',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['lifecycle_status' => 'aktuell', 'is_active' => true, 'revision' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'used_in' => 'array',
            'is_active' => 'boolean',
            'valid_from' => 'immutable_date',
            'valid_until' => 'immutable_date',
            'revision' => 'integer',
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
        return $this->belongsToMany(Source::class, 'term_sources')->withPivot(['url', 'retrieved_at', 'note']);
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

    /** @return BelongsTo<TermRevision, $this> */
    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(TermRevision::class, 'published_revision_id');
    }

    /**
     * Öffentlich sichtbar: veröffentlicht und aktiv (PUB-01, unveröffentlichte Entwürfe nie).
     *
     * @param  Builder<Term>  $query
     * @return Builder<Term>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_revision_id')->where('is_active', true);
    }

    /** Angezeigte Abkürzung: eigene vor offizieller (wie im Intranet). */
    public function displayAbbreviation(): string
    {
        return (string) ($this->own_abbreviation ?: $this->official_abbreviation);
    }

    /** Angezeigte Bezeichnung: eigene vor offizieller (wie im Intranet). */
    public function displayName(): string
    {
        return (string) ($this->own_name ?: $this->official_name);
    }

    /** Arbeitsstand weicht von der veröffentlichten Revision ab. */
    public function hasUnpublishedChanges(): bool
    {
        return $this->publishedRevision === null || $this->publishedRevision->revision !== $this->revision;
    }
}
