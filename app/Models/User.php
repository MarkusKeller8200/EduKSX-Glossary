<?php

namespace App\Models;

use App\Access\Permission;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

/**
 * Persönliches Konto (DEC-014, DEC-017). Rechte ergeben sich aus den Rollen (App\Access\Permission); geprüft wird
 * serverseitig über Gates. Rollen ändern nur über App\Access\RoleManager (Schutzregeln, Audit).
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string|null $two_factor_secret
 * @property list<string>|null $two_factor_recovery_codes Hashes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property int|null $two_factor_last_step
 * @property-read Collection<int, Role> $roles
 */
class User extends Authenticatable
{
    use HasUuids, Notifiable;

    /** @var list<string> */
    protected $fillable = ['name', 'email', 'password'];

    /** @var list<string>|null Rechte dieser Instanz (je Anfrage neu berechnet) */
    private ?array $permissionCache = null;

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'two_factor_last_step' => 'integer',
        ];
    }

    /** Zweiter Faktor eingerichtet und bestätigt (#11). */
    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /** @return list<string> Schlüssel der zugewiesenen Rollen */
    public function roleKeys(): array
    {
        return array_values(array_map('strval', DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $this->id)->orderBy('roles.sort_order')->pluck('roles.key')->all()));
    }

    public function isDeveloper(): bool
    {
        return in_array(Role::DEVELOPER, $this->roleKeys(), true);
    }

    /**
     * Effektive Rechte aus allen Rollen. Ein Konto ohne Rolle (Community) darf Vorschläge einreichen.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        if ($this->permissionCache !== null) {
            return $this->permissionCache;
        }
        if ($this->isDeveloper()) {
            return $this->permissionCache = Permission::keys();
        }
        $granted = DB::table('role_permissions')
            ->join('user_roles', 'user_roles.role_id', '=', 'role_permissions.role_id')
            ->where('user_roles.user_id', $this->id)
            ->pluck('role_permissions.permission')->all();
        $granted = array_unique(['propose', ...array_map('strval', $granted)]);

        return $this->permissionCache = array_values(array_filter(Permission::keys(), fn (string $p): bool => in_array($p, $granted, true)));
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /** Hat mindestens ein Recht, das den zweiten Faktor verlangt (alles ausser Vorschläge einreichen). */
    public function needsTwoFactor(): bool
    {
        return array_intersect($this->permissions(), Permission::privilegedArea()) !== [];
    }

    /** Nach Änderungen an Rollen oder Rechten neu berechnen. */
    public function flushPermissions(): void
    {
        $this->permissionCache = null;
        $this->unsetRelation('roles');
    }
}
