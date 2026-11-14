<?php

namespace App\Models;

use App\Data\HasEventId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Protokoll der Servicekonten (#47): Anlage, Token, Widerruf, Sperre und Zugriffe mit Ergebnis. Nur anfügen.
 *
 * @property int $id
 * @property string $event_id UUID v7 für die Zusammenführung über Module (#332)
 * @property string|null $service_account_id
 * @property string|null $token_id
 * @property string|null $actor_id
 * @property string|null $actor_label
 * @property string $action
 * @property array<mixed>|null $details
 * @property string $result
 * @property CarbonImmutable $created_at
 */
class ServiceAccountAudit extends Model
{
    use HasEventId;

    public const UPDATED_AT = null;

    protected $table = 'service_account_audit';

    /** @var list<string> */
    protected $fillable = ['service_account_id', 'token_id', 'actor_id', 'actor_label', 'action', 'details', 'result'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Protokolleinträge sind unveränderlich.'));
    }
}
