<?php

namespace App\Models;

use App\Access\Permission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

/**
 * Rolle mit Rechten (DEC-017). «developer» ist die geschützte Systemrolle mit allen Rechten; ihre Rechte stehen
 * nicht in role_permissions und sind nicht einstellbar. Ändern nur über App\Access\RoleManager.
 *
 * @property string $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property bool $is_system
 * @property int $sort_order
 * @property-read Collection<int, User> $users
 */
class Role extends Model
{
    use HasUuids;

    public const DEVELOPER = 'developer';

    public const ADMIN = 'admin';

    public const MODERATOR = 'moderator';

    /** Organisations-Admin (#110, #148): wie Benutzer, dazu Servicekonten; wird durch die Organisation vergeben (DEC-025). */
    public const ORG_ADMIN = 'org-admin';

    /** Benutzer (#110): exportieren, Vorschläge einreichen, persönliche Darstellung. */
    public const USER = 'benutzer';

    /** Gast (#110): angemeldet, nur lesen. */
    public const GUEST = 'gast';

    /** Rolle neuer Konten (Registrierung, Microsoft, administrativ angelegt), bestätigt am 08.11.2026 (#110). */
    public const DEFAULT_FOR_NEW = self::USER;

    /** @var list<string> */
    protected $fillable = ['key', 'name', 'description', 'is_system', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }

    /** @return list<string> */
    public function permissionKeys(): array
    {
        if ($this->key === self::DEVELOPER) {
            return Permission::keys();
        }

        return array_values(array_map('strval', DB::table('role_permissions')->where('role_id', $this->id)
            ->orderBy('permission')->pluck('permission')->all()));
    }

    public static function byKey(string $key): self
    {
        return self::query()->where('key', $key)->firstOrFail();
    }
}
