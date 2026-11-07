<?php

namespace App\Models;

use App\Access\Permission;
use App\Notifications\ResetPasswordNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

/**
 * Persönliches Konto (DEC-014, DEC-017). Rechte ergeben sich aus den Rollen (App\Access\Permission); geprüft wird
 * serverseitig über Gates. Rollen ändern nur über App\Access\RoleManager (Schutzregeln, Audit).
 *
 * @property string $id
 * @property string $name Anzeigename (Vor- und Nachname, vom Model nachgeführt)
 * @property string $first_name
 * @property string $last_name
 * @property string|null $community_name
 * @property string|null $function
 * @property string|null $institution_type
 * @property string|null $institution_name
 * @property CarbonImmutable|null $last_login_on
 * @property CarbonImmutable|null $email_verified_at
 * @property string|null $privacy_notice_version
 * @property CarbonImmutable|null $privacy_notice_acknowledged_at
 * @property CarbonImmutable|null $inactivity_notice_sent_at
 * @property bool $password_set false = nur über Anmeldedienst (#45)
 * @property-read Collection<int, UserIdentity> $identities
 * @property string $email
 * @property string|null $two_factor_secret
 * @property list<string>|null $two_factor_recovery_codes Hashes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property int|null $two_factor_last_step
 * @property string $status active, blocked, pending (Registrierung noch nicht bestätigt)
 * @property CarbonImmutable|null $blocked_at
 * @property-read Collection<int, Role> $roles
 */
class User extends Authenticatable
{
    use HasUuids, Notifiable;

    /** Arten öffentlicher Einrichtungen (docs/KONZEPT-BENUTZER.md 4). */
    public const INSTITUTION_TYPES = [
        'schule' => 'Schule', 'schulgemeinde' => 'Schulgemeinde', 'gemeinde' => 'Gemeinde', 'kanton' => 'Kanton',
        'andere' => 'andere',
    ];

    /** @var list<string> */
    protected $fillable = [
        'name', 'first_name', 'last_name', 'email', 'password', 'community_name', 'function', 'institution_type',
        'institution_name',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'active', 'password_set' => true];

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
            'email_verified_at' => 'immutable_datetime',
            'privacy_notice_acknowledged_at' => 'immutable_datetime',
            'inactivity_notice_sent_at' => 'immutable_datetime',
            'password_set' => 'boolean',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'two_factor_last_step' => 'integer',
            'blocked_at' => 'immutable_datetime',
            'last_login_on' => 'immutable_date',
        ];
    }

    protected static function booted(): void
    {
        // Anzeigename aus Vor- und Nachname; ohne diese (z. B. ältere Aufrufe mit «name») den Namen aufteilen
        static::saving(function (User $user): void {
            if (($user->first_name ?? '') === '' && ($user->last_name ?? '') === '' && ($user->name ?? '') !== '') {
                $parts = preg_split('/\s+/', trim($user->name)) ?: [];
                $user->last_name = count($parts) > 1 ? (string) array_pop($parts) : '';
                $user->first_name = implode(' ', $parts);
            }
            $user->name = trim($user->first_name.' '.$user->last_name);
        });
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

    /** @return HasMany<UserIdentity, $this> */
    public function identities(): HasMany
    {
        return $this->hasMany(UserIdentity::class);
    }

    /** @return list<string> Schlüssel der zugewiesenen Rollen */
    public function roleKeys(): array
    {
        return array_values(array_map('strval', DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $this->id)->orderBy('roles.sort_order')->pluck('roles.key')->all()));
    }

    /** Passwort-Link deutsch und über die Warteschlange (#41). */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /** Registrierung noch nicht per E-Mail bestätigt (#41). */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /** Nicht gesperrt (#37). */
    public function isActive(): bool
    {
        return $this->status === 'active';
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
