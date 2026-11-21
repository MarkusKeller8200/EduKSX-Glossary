<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Gegenstelle im Entwicklungs-Sync (#478, DEC-041 A20): Adresse und Token des Servicekontos dort; `base` hält die
 * Prüfsummen beim letzten Holen bzw. Hochladen (Konfliktprüfung A17).
 *
 * @property string $id
 * @property string $name
 * @property string $url
 * @property string $token verschlüsselt (APP_KEY), nie angezeigt
 * @property string|null $remote_role
 * @property array{terms?: array<string, array{0: int, 1: string}>, categories?: array<string, string>, sources?: array<string, string>}|null $base
 * @property CarbonImmutable|null $last_pulled_at
 * @property CarbonImmutable|null $last_pushed_at
 * @property string|null $last_result
 * @property string|null $created_by_id
 */
class SyncPeer extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = ['name', 'url', 'token'];

    /** @var list<string> */
    protected $hidden = ['token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'base' => 'array',
            'last_pulled_at' => 'immutable_datetime',
            'last_pushed_at' => 'immutable_datetime',
        ];
    }
}
