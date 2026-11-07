<?php

namespace App\Access;

use App\Glossary\TwoFactor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Konten sperren, entsperren und den zweiten Faktor zurücksetzen (#37, docs/KONZEPT-BENUTZER.md 5.3 und 5.4).
 * Schutzregeln wie beim RoleManager; jede Aktion steht im Audit-Protokoll. $actor = null ist der Serverprozess.
 */
final class AccountManager
{
    public function __construct(private readonly TwoFactor $twoFactor) {}

    public function block(?User $actor, User $target): void
    {
        $this->authorize($actor, $target, 'account.blocked', function () use ($actor, $target): void {
            if ($actor !== null && $actor->id === $target->id) {
                throw new AccessDenied('Das eigene Konto kann nicht gesperrt werden.');
            }
            if ($target->isDeveloper() && ! $this->othersActive(fn (User $u): bool => $u->isDeveloper(), $target)) {
                throw new AccessDenied('Das letzte aktive Developer-Konto kann nicht gesperrt werden.');
            }
            if ($target->hasPermission('manage-users') && ! $this->othersActive(fn (User $u): bool => $u->hasPermission('manage-users'), $target)) {
                throw new AccessDenied('Das letzte aktive Konto mit «Benutzer und Rollen verwalten» kann nicht gesperrt werden.');
            }
        });

        $target->forceFill(['status' => 'blocked', 'blocked_at' => now()])->save();
        $this->endSessions($target);
    }

    public function unblock(?User $actor, User $target): void
    {
        $this->authorize($actor, $target, 'account.unblocked', fn () => null);
        $target->forceFill(['status' => 'active', 'blocked_at' => null])->save();
    }

    /** Zweiten Faktor zurücksetzen (Handy verloren): danach bei der nächsten Anmeldung neu einrichten. */
    public function resetTwoFactor(?User $actor, User $target): void
    {
        $this->authorize($actor, $target, 'mfa.reset', function () use ($actor, $target): void {
            if ($actor !== null && $actor->id === $target->id) {
                throw new AccessDenied('Den eigenen zweiten Faktor setzt die Administration oder der Serverbefehl zurück.');
            }
        });
        $this->twoFactor->reset($target);
        $this->endSessions($target);
    }

    /** Alle Sitzungen eines Kontos sofort beenden (Datenbank-Sitzungen). */
    public function endSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
        $user->forceFill(['remember_token' => null])->save();
    }

    /**
     * Gemeinsame Regeln: Recht «manage-users»; Developer nur durch Developer bzw. Server.
     *
     * @param  callable(): void  $rules
     */
    private function authorize(?User $actor, User $target, string $action, callable $rules): void
    {
        try {
            if ($actor !== null) {
                if (! $actor->hasPermission('manage-users')) {
                    throw new AccessDenied('Dafür fehlt das Recht «Benutzer und Rollen verwalten».');
                }
                if ($target->isDeveloper() && ! $actor->isDeveloper()) {
                    throw new AccessDenied('Developer-Konten verwaltet nur ein Developer oder der Serverbefehl.');
                }
                $beyond = array_diff($target->permissions(), $actor->permissions());
                if ($beyond !== []) {
                    throw new AccessDenied('Das Konto hat Rechte ausserhalb der eigenen Befugnis.');
                }
            }
            $rules();
        } catch (AccessDenied $e) {
            Audit::log($actor, $target, $action, ['reason' => $e->getMessage()], 'denied');

            throw $e;
        }
        Audit::log($actor, $target, $action);
    }

    /** @param  callable(User): bool  $match */
    private function othersActive(callable $match, User $except): bool
    {
        $ids = DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->whereIn('roles.key', [Role::DEVELOPER])
            ->orWhereIn('user_roles.role_id', DB::table('role_permissions')->where('permission', 'manage-users')->select('role_id'))
            ->distinct()->pluck('user_roles.user_id')->all();

        return User::query()->whereIn('id', $ids)->where('id', '!=', $except->id)->where('status', 'active')
            ->get()->contains(fn (User $u): bool => $match($u));
    }
}
