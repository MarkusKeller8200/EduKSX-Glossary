<?php

namespace App\Models;

use App\Enums\AliasType;
use App\Enums\TermStatus;
use App\Glossary\Permalink;
use Carbon\CarbonImmutable;
use EduKSX\Core\Eloquent\OptimisticLocking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Begriff (Arbeitsstand). Öffentlich ist nur die veröffentlichte Revision (publishedRevision), nie dieser Stand.
 * Ändern nur über App\Glossary\GlossaryService (Regeln, Revisionen, Verlauf).
 *
 * @property string $id
 * @property string $slug
 * @property string $code Permalink /g/{code} (DEC-026), beim Anlegen vergeben, unveränderlich
 * @property string|null $official_abbreviation
 * @property string|null $official_name
 * @property string|null $short_description
 * @property string|null $description
 * @property string|null $note
 * @property string|null $url
 * @property-read string $search_terms Suchbegriffe als Text «a; b», aus den Aliasen vom Typ search (E1, #266)
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
 * @property-read TermRevision|null $latestRevision
 * @property-read Collection<int, Category> $categories
 * @property-read Collection<int, Source> $sources
 * @property-read Collection<int, TermAlias> $aliases
 * @property-read Collection<int, TermTranslation> $translations
 * @property-read Collection<int, TermRelation> $termRelations
 * @property-read Collection<int, TermRevision> $revisions
 * @property-read Collection<int, TermUserOverride> $overrides
 * @property int $lock_version
 */
class Term extends Model
{
    use HasUuids;
    use OptimisticLocking;

    /** Status des Begriffs (nicht die Veröffentlichung): Wert → Label, siehe {@see TermStatus}. */
    public const STATUS = ['current' => 'aktuell', 'historical' => 'historisch', 'under_review' => 'zu prüfen'];

    /** Offizielle Felder: ändern nur mit Recht «edit-official» (leer → Wert setzen ist erlaubt, wie im Intranet). */
    public const OFFICIAL = ['official_abbreviation', 'official_name', 'official_aliases'];

    /** @var list<string> */
    protected $fillable = [
        'slug', 'official_abbreviation', 'official_name', 'short_description', 'description', 'note', 'url',
        'lifecycle_status', 'is_active', 'valid_from', 'valid_until',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['lifecycle_status' => 'current', 'is_active' => true, 'revision' => 0];

    protected static function booted(): void
    {
        // Permalink (#154): jeder Begriff erhält beim Anlegen einen Code, der sich nie mehr ändert
        static::creating(function (Term $term): void {
            if (($term->code ?? '') === '') {
                $term->code = Permalink::generate();
            }
        });
        static::updating(function (Term $term): void {
            if ($term->isDirty('code') && $term->getOriginal('code') !== null) {
                $term->code = (string) $term->getOriginal('code');
            }
        });
    }

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
        return $this->belongsToMany(Source::class, 'term_sources')->withPivot(['retrieved_on', 'note', 'evidence']);
    }

    /**
     * Suchbegriffe als Text (E1, #266): nur gespeichert als Aliase vom Typ search.
     *
     * @return Attribute<string, never>
     */
    protected function searchTerms(): Attribute
    {
        return Attribute::get(fn (): string => implode('; ', $this->aliases
            ->where('alias_type', AliasType::Search->value)->pluck('alias')->all()));
    }

    /** @return HasMany<TermTranslation, $this> Übersetzungen fr/it/en (X1, #282) */
    public function translations(): HasMany
    {
        return $this->hasMany(TermTranslation::class);
    }

    /** @return HasMany<TermRelation, $this> Beziehungen zu anderen Begriffen (X2, #283) */
    public function termRelations(): HasMany
    {
        return $this->hasMany(TermRelation::class);
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
     * Nach UUID oder Slug (API, Permalink-Auflösung).
     *
     * @param  Builder<Term>  $query
     * @return Builder<Term>
     */
    public function scopeIdOrSlug(Builder $query, string $value): Builder
    {
        // UUID-Spalte nur mit einer UUID vergleichen (PostgreSQL lehnt andere Texte ab, #287)
        return $query->where(fn (Builder $q) => $q->where('slug', $value)->when(Str::isUuid($value), fn (Builder $q) => $q->orWhere('id', $value)));
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

    /**
     * Titel «Abkürzung – Bezeichnung» aus Werten bzw. einem Abbild (veröffentlichte Revision, Vorschlag), #138.
     *
     * @param  array<string, mixed>  $values
     */
    public static function titleFrom(array $values): string
    {
        return trim(implode(' – ', array_filter([
            (string) ($values['official_abbreviation'] ?? ''), (string) ($values['official_name'] ?? ''),
        ])));
    }

    /** Titel «Abkürzung – Bezeichnung». */
    public function title(): string
    {
        return trim($this->displayAbbreviation().' – '.$this->displayName(), ' –');
    }

    /** @return HasOne<TermRevision, $this> Neueste Revision (Arbeitsstand) */
    public function latestRevision(): HasOne
    {
        // nicht latestOfMany: das vergleicht zusätzlich MAX(id), was es für UUIDs unter PostgreSQL nicht gibt (#287)
        return $this->hasOne(TermRevision::class)->orderByDesc('revision');
    }

    /**
     * Entwurf (DEC-022): Der Arbeitsstand weicht inhaltlich von der veröffentlichten Revision ab. Gleicher Inhalt mit
     * höherer Nummer (z. B. nach «Entwurf verwerfen») ist kein Entwurf.
     */
    public function hasUnpublishedChanges(): bool
    {
        $published = $this->publishedRevision;
        if ($published === null) {
            return true;
        }
        if ($published->revision === $this->revision) {
            return false;
        }
        $latest = $this->latestRevision;

        return $latest === null || $latest->snapshot != $published->snapshot;
    }
}
