<?php

namespace App\Access;

use App\Models\Role;
use App\Models\User;
use App\Models\UserAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Einzige Stelle, die Rollen und Rechte ändert (#36, docs/KONZEPT-BENUTZER.md Abschnitt 3). Durchgesetzt werden:
 *
 * - Developer vergeben/entziehen nur durch einen Developer oder den Serverprozess ($actor = null).
 * - Niemand erhöht eigene Rechte, weder direkt (eigene Rollen) noch indirekt (Rechte einer eigenen Rolle).
 * - Vergeben nur innerhalb der eigenen Grenze: eine Rolle zuweisen bzw. ein Recht setzen nur, wenn man es selbst hat.
 * - Systemkritische Rechte nie über die Rollen-Matrix; die Developer-Rolle ist nicht einstellbar.
 * - Schutz vor Aussperren: Es bleibt immer mindestens ein Developer und ein Konto mit «manage-users».
 *
 * Jeder Versuch – erfolgreich oder abgewiesen – landet im Audit-Protokoll.
 */
final class RoleManager
{
    /** Bezeichnung im Protokoll für Aktionen des Serverprozesses (Artisan-Befehl). */
    public const SERVER = 'Serverbefehl';

    public function assign(?User $actor, User $target, Role $role, ?string $label = null): void
    {
        $this->guard($actor, $target, $role, 'role.assigned', function () use ($actor, $target, $role): void {
            if ($role->key === Role::DEVELOPER) {
                $this->requireDeveloperOrServer($actor, 'Developer vergeben darf nur ein Developer oder der Serverbefehl.');

                return;
            }
            $this->requireManager($actor);
            $this->requireNotSelf($actor, $target);
            $this->requireWithinBoundary($actor, $role->permissionKeys());
        }, $label);

        if (! $target->roles()->whereKey($role->id)->exists()) {
            $target->roles()->attach($role->id);
        }
        $target->flushPermissions();
    }

    /**
     * Rolle für neue Konten (Role::DEFAULT_FOR_NEW, #110) durch den Serverprozess, protokolliert mit $label
     * (z. B. «Registrierung»). Fehlt die Rolle, bleibt das Konto ohne Rolle (nur lesen).
     */
    public function assignDefault(User $target, string $label): void
    {
        $role = Role::query()->where('key', Role::DEFAULT_FOR_NEW)->first();
        if ($role !== null) {
            $this->assign(null, $target, $role, $label);
        }
    }

    public function revoke(?User $actor, User $target, Role $role, ?string $label = null): void
    {
        $this->guard($actor, $target, $role, 'role.revoked', function () use ($actor, $target, $role): void {
            if ($role->key === Role::DEVELOPER) {
                $this->requireDeveloperOrServer($actor, 'Developer entziehen darf nur ein Developer oder der Serverbefehl.');
                if ($this->countWithRole(Role::DEVELOPER, except: $target) === 0) {
                    throw new AccessDenied('Das letzte Developer-Konto kann seine Rolle nicht verlieren.');
                }

                return;
            }
            $this->requireManager($actor);
            $this->requireNotSelf($actor, $target);
            $this->requireWithinBoundary($actor, $role->permissionKeys());
            if (in_array('manage-users', $role->permissionKeys(), true) && ! $this->managerRemains($target, $role)) {
                throw new AccessDenied('Das letzte Konto mit «Benutzer und Rollen verwalten» kann diese Rolle nicht verlieren.');
            }
        }, $label);

        $target->roles()->detach($role->id);
        $target->flushPermissions();
    }

    /**
     * Rechte einer Rolle setzen (Rollen-Matrix).
     *
     * @param  list<string>  $permissions
     */
    public function setPermissions(?User $actor, Role $role, array $permissions): void
    {
        $permissions = array_values(array_unique($permissions));
        $old = $role->permissionKeys();
        $added = array_values(array_diff($permissions, $old));
        $removed = array_values(array_diff($old, $permissions));

        $this->guard($actor, null, $role, 'role.permissions', function () use ($actor, $role, $permissions, $added, $removed): void {
            if ($role->key === Role::DEVELOPER) {
                throw new AccessDenied('Die Developer-Rolle ist eine Systemrolle und nicht einstellbar.');
            }
            foreach ($permissions as $permission) {
                if (! Permission::exists($permission)) {
                    throw new AccessDenied("Unbekanntes Recht «{$permission}».");
                }
                if (Permission::classOf($permission) === Permission::SYSTEM) {
                    throw new AccessDenied('Systemkritische Rechte sind nur über die Developer-Rolle möglich.');
                }
            }
            $this->requireManager($actor);
            $this->requireWithinBoundary($actor, [...$added, ...$removed]);
            if ($actor !== null && ! $actor->isDeveloper() && $added !== [] && in_array($role->key, $actor->roleKeys(), true)) {
                throw new AccessDenied('Einer eigenen Rolle dürfen keine Rechte hinzugefügt werden.');
            }
            if (in_array('manage-users', $removed, true) && ! $this->managerRemains(null, $role)) {
                throw new AccessDenied('Mindestens ein Konto muss «Benutzer und Rollen verwalten» behalten.');
            }
        }, null, ['permissions' => $old], ['permissions' => $permissions]);

        DB::transaction(function () use ($role, $permissions): void {
            DB::table('role_permissions')->where('role_id', $role->id)->delete();
            foreach ($permissions as $permission) {
                DB::table('role_permissions')->insert(['role_id' => $role->id, 'permission' => $permission]);
            }
        });
    }

    /** Neue Rolle ohne Rechte anlegen (Rechte danach mit setPermissions). */
    public function create(?User $actor, string $name, ?string $description = null): Role
    {
        $key = Str::slug($name);
        $this->guard($actor, null, null, 'role.created', function () use ($actor, $key): void {
            $this->requireManager($actor);
            if ($key === '' || Role::query()->where('key', $key)->exists()) {
                throw new AccessDenied('Diese Rolle gibt es schon oder der Name ist leer.');
            }
        }, null, null, ['key' => $key, 'name' => $name]);

        return Role::query()->create([
            'key' => $key, 'name' => $name, 'description' => $description, 'is_system' => false,
            'sort_order' => (int) Role::query()->max('sort_order') + 10,
        ]);
    }

    // ---------- Regeln ----------

    private function requireDeveloperOrServer(?User $actor, string $message): void
    {
        if ($actor !== null && ! $actor->isDeveloper()) {
            throw new AccessDenied($message);
        }
    }

    private function requireManager(?User $actor): void
    {
        if ($actor !== null && ! $actor->hasPermission('manage-users')) {
            throw new AccessDenied('Dafür fehlt das Recht «Benutzer und Rollen verwalten».');
        }
    }

    private function requireNotSelf(?User $actor, User $target): void
    {
        if ($actor !== null && $actor->id === $target->id && ! $actor->isDeveloper()) {
            throw new AccessDenied('Eigene Rollen kann nur ein Developer ändern.');
        }
    }

    /** @param  list<string>  $permissions */
    private function requireWithinBoundary(?User $actor, array $permissions): void
    {
        if ($actor === null || $actor->isDeveloper()) {
            return;
        }
        $missing = array_diff($permissions, $actor->permissions());
        if ($missing !== []) {
            throw new AccessDenied('Ausserhalb der eigenen Befugnis: '.implode(', ', $missing).'.');
        }
    }

    private function countWithRole(string $key, ?User $except = null): int
    {
        return DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('roles.key', $key)
            ->when($except !== null, fn ($q) => $q->where('user_roles.user_id', '!=', $except?->id))
            ->distinct()->count('user_roles.user_id');
    }

    /**
     * Bleibt nach dem Entzug ein Konto mit «manage-users»? Fall 1 ($target gesetzt): $target verliert $role. Fall 2
     * ($target null): $role verliert das Recht. Developer zählen immer mit.
     */
    private function managerRemains(?User $target, Role $role): bool
    {
        $roleIds = DB::table('roles')->where(function ($q): void {
            $q->where('key', Role::DEVELOPER)
                ->orWhereIn('id', DB::table('role_permissions')->where('permission', 'manage-users')->select('role_id'));
        })->pluck('id')->map(fn ($id): string => (string) $id)->all();

        if ($target === null) {
            $roleIds = array_values(array_diff($roleIds, [$role->id]));

            return DB::table('user_roles')->whereIn('role_id', $roleIds)->exists();
        }

        $otherUsers = DB::table('user_roles')->whereIn('role_id', $roleIds)->where('user_id', '!=', $target->id)->exists();
        $targetKeeps = DB::table('user_roles')->where('user_id', $target->id)
            ->whereIn('role_id', array_values(array_diff($roleIds, [$role->id])))->exists();

        return $otherUsers || $targetKeeps;
    }

    /**
     * Regeln prüfen und protokollieren: Erfolg als «ok», Abweisung als «denied» (dann AccessDenied weiterwerfen).
     *
     * @param  callable(): void  $rules
     * @param  array<mixed>|null  $old
     * @param  array<mixed>|null  $new
     */
    private function guard(?User $actor, ?User $target, ?Role $role, string $action, callable $rules, ?string $label = null, ?array $old = null, ?array $new = null): void
    {
        $entry = [
            'actor_id' => $actor?->id,
            'actor_label' => $actor === null ? ($label ?? self::SERVER) : $label,
            'target_user_id' => $target?->id,
            'target_role_id' => $role?->id,
            'action' => $action,
            'old_values' => $old,
            'new_values' => $new ?? ($role !== null ? ['role' => $role->key] : null),
        ];
        try {
            $rules();
        } catch (AccessDenied $e) {
            UserAudit::query()->create(array_merge($entry, [
                'result' => 'denied',
                'new_values' => ['reason' => $e->getMessage()] + ($entry['new_values'] ?? []),
            ]));

            throw $e;
        }
        UserAudit::query()->create($entry + ['result' => 'ok']);
    }
}
