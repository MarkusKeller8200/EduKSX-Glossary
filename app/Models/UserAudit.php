<?php

namespace App\Models;

use App\Data\HasEventId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Protokoll sicherheitsrelevanter Änderungen an Konten, Rollen und Rechten (docs/KONZEPT-BENUTZER.md Abschnitt 3):
 * ausführende Identität, Ziel, Zeit, alter und neuer Wert, Ergebnis. Nur anfügen, nie ändern.
 *
 * @property int $id
 * @property string $event_id UUID v7 für die Zusammenführung über Module (#332)
 * @property string|null $actor_id
 * @property string|null $actor_label
 * @property string|null $target_user_id
 * @property string|null $target_role_id
 * @property string $action
 * @property array<mixed>|null $old_values
 * @property array<mixed>|null $new_values
 * @property string $result
 * @property CarbonImmutable $created_at
 */
class UserAudit extends Model
{
    use HasEventId;

    public const UPDATED_AT = null;

    protected $table = 'user_audit';

    /** @var list<string> */
    protected $fillable = ['actor_id', 'actor_label', 'target_user_id', 'target_role_id', 'action', 'old_values', 'new_values', 'result'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Audit-Einträge sind unveränderlich.'));
        static::deleting(fn (): never => throw new LogicException('Audit-Einträge werden nur über das Löschkonzept entfernt.'));
    }
}
