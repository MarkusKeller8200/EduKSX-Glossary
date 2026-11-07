<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Unveränderliches Abbild eines Begriffs zu einer Revision. Veröffentlicht, wenn published_at gesetzt ist.
 *
 * @property string $id
 * @property string $term_id
 * @property int $revision
 * @property string $change_type
 * @property array<string, mixed> $snapshot
 * @property string|null $author_id
 * @property string|null $author_label Community-Bezeichnung zum Zeitpunkt der Änderung (Text, kein Bezug)
 * @property array<string, array{old: mixed, new: mixed}>|null $change_set Feld → alter und neuer Wert
 * @property string|null $actor_type community, moderator, admin, developer, service, system
 * @property array<string, mixed>|null $context z. B. Quelle (Excel) oder Zusammenführung
 * @property string|null $approved_by
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable $created_at
 */
class TermRevision extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'revision', 'change_type', 'snapshot', 'author_id', 'author_label', 'change_set', 'actor_type', 'context',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'change_set' => 'array',
            'context' => 'array',
            'revision' => 'integer',
            'published_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        // Inhalt einer Revision ist unveränderlich; nur die Freigabe (published_at, approved_by) wird gesetzt.
        // Einzige Ausnahme: bei der Kontolöschung wird die Bezeichnung ersetzt (PersonalData::forget, DEC-020).
        static::updating(function (TermRevision $revision): void {
            $changed = array_keys($revision->getDirty());
            if (array_diff($changed, ['published_at', 'approved_by']) !== []) {
                throw new LogicException('Revisionen sind unveränderlich.');
            }
        });
    }

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}
