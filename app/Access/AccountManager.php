<?php

namespace App\Access;

use App\Glossary\TwoFactor;
use App\Models\Role;
use App\Models\User;
use App\Support\MailSetup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

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

    /**
     * Link zum Zurücksetzen des Passworts senden (#147): durch die Verwaltung, ohne das Passwort zu sehen. Gleicher
     * Link wie «Passwort vergessen» (60 Minuten gültig); auch für Konten ohne eigenes Passwort (Microsoft).
     */
    public function sendPasswordReset(?User $actor, User $target): void
    {
        $this->authorize($actor, $target, 'password.reset_sent', function () use ($actor, $target): void {
            if ($actor !== null && $actor->id === $target->id) {
                throw new AccessDenied('Das eigene Passwort unter «Mein Konto» ändern.');
            }
            if (! $target->isActive()) {
                throw new AccessDenied('Das Konto ist gesperrt oder noch nicht bestätigt.');
            }
            if (! MailSetup::configured()) {
                throw new AccessDenied('Der Mailversand ist nicht eingerichtet.');
            }
        });
        Password::sendResetLink(['email' => $target->email]);
    }

    /**
     * Konto löschen (#39): selbst (Konto löschen) oder durch die Verwaltung. Sitzungen enden sofort, Rollen und
     * Anmeldedienste werden entfernt; das Protokoll behält den Eintrag mit der ID.
     */
    public function delete(?User $actor, User $target): void
    {
        $self = $actor !== null && $actor->id === $target->id;
        $rules = function () use ($target): void {
            if ($target->isDeveloper() && ! $this->othersActive(fn (User $u): bool => $u->isDeveloper(), $target)) {
                throw new AccessDenied('Das letzte aktive Developer-Konto kann nicht gelöscht werden.');
            }
            if ($target->hasPermission('manage-users') && ! $this->othersActive(fn (User $u): bool => $u->hasPermission('manage-users'), $target)) {
                throw new AccessDenied('Das letzte aktive Konto mit «Benutzer und Rollen verwalten» kann nicht gelöscht werden.');
            }
        };
        if ($self) {
            try {
                $rules();
            } catch (AccessDenied $e) {
                Audit::log($actor, $target, 'account.deleted', ['reason' => $e->getMessage()], 'denied');

                throw $e;
            }
            Audit::log($actor, $target, 'account.deleted', ['self' => true]);
        } else {
            $this->authorize($actor, $target, 'account.deleted', $rules);
        }

        $this->endSessions($target);
        $target->delete();
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
    /**
     * Bearbeitung einschränken bzw. wieder freigeben (#312, DB-Standard B13): Daten bleiben gespeichert, werden aber
     * nicht angezeigt, weitergegeben oder geändert; Sitzungen enden. Nur Verwaltung bzw. Server.
     */
    public function restrictProcessing(?User $actor, User $target, bool $restricted): void
    {
        $action = $restricted ? 'account.processing_restricted' : 'account.processing_unrestricted';
        if ($actor !== null && ! $actor->hasPermission('manage-users')) {
            Audit::log($actor, $target, $action, ['reason' => 'Dafür fehlt das Recht «Benutzer und Rollen verwalten».'], 'denied');

            throw new AccessDenied('Dafür fehlt das Recht «Benutzer und Rollen verwalten».');
        }
        Audit::log($actor, $target, $action);
        $target->forceFill(['is_processing_restricted' => $restricted])->save();
        if ($restricted) {
            $this->endSessions($target);
        }
    }

    private function authorize(?User $actor, User $target, string $action, callable $rules): void
    {
        try {
            if ($target->is_processing_restricted) {
                throw new AccessDenied('Die Bearbeitung dieses Kontos ist eingeschränkt.');
            }
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
