<?php

namespace App\Access;

use App\Mail\RoleRequestDecided;
use App\Mail\RoleRequestSubmitted;
use App\Models\Role;
use App\Models\RoleRequest;
use App\Models\User;
use App\Support\MailSetup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Als Moderator/in bewerben (#460): einmaliger Hinweis nach der Anmeldung, Bewerbung mit Begründung (Voraussetzung:
 * zweiter Faktor und bestätigte Adresse), Entscheid nur durch Konten mit «manage-users» (Admins).
 */
final class RoleRequests
{
    /** Diese Rollen haben schon die Rechte der Moderation oder mehr */
    private const AT_LEAST_MODERATOR = [Role::MODERATOR, Role::ADMIN, Role::DEVELOPER];

    public function __construct(private readonly RoleManager $roles) {}

    public function isModeratorOrMore(User $user): bool
    {
        return array_intersect($user->roleKeys(), self::AT_LEAST_MODERATOR) !== [];
    }

    /**
     * Was fehlt zum Bewerben? Leere Liste = alles erfüllt.
     *
     * @return list<string>
     */
    public function missing(User $user): array
    {
        $missing = [];
        if (! $user->hasTwoFactor()) {
            $missing[] = 'zweiter Faktor';
        }
        if ($user->email_verified_at === null) {
            $missing[] = 'bestätigte E-Mail-Adresse';
        }

        return $missing;
    }

    public function openFor(User $user): ?RoleRequest
    {
        return RoleRequest::query()->where('user_id', $user->id)->where('status', RoleRequest::OPEN)->first();
    }

    /**
     * Einmaliger Hinweis (#460): true genau beim ersten Aufruf nach der Einführung bzw. nach dem Anlegen; danach nie
     * wieder. Nicht für Konten mit Moderation oder mehr.
     */
    public function consumeNotice(User $user): bool
    {
        if (! $user->isActive() || $user->moderator_notice_seen_at !== null) {
            return false;
        }
        $user->forceFill(['moderator_notice_seen_at' => now()])->saveQuietly();

        return ! $this->isModeratorOrMore($user);
    }

    public function apply(User $user, string $reason): RoleRequest
    {
        $reason = trim($reason);
        if ($this->isModeratorOrMore($user)) {
            throw new AccessDenied('Sie haben die Rechte der Moderation bereits.');
        }
        if (($missing = $this->missing($user)) !== []) {
            throw new AccessDenied('Für die Bewerbung fehlt: '.implode(', ', $missing).'.');
        }
        if ($this->openFor($user) !== null) {
            throw new AccessDenied('Es gibt schon eine offene Bewerbung.');
        }
        if (mb_strlen($reason) < 10) {
            throw new AccessDenied('Bitte kurz begründen (mindestens 10 Zeichen).');
        }
        $request = new RoleRequest(['role' => Role::MODERATOR, 'reason' => mb_substr($reason, 0, 1000)]);
        $request->forceFill(['user_id' => $user->id, 'status' => RoleRequest::OPEN])->save();
        Audit::log($user, $user, 'role.requested', ['rolle' => 'Moderator']);
        $this->notifyAdmins($user);

        return $request;
    }

    /** Mail an alle aktiven Konten, die entscheiden dürfen (Recht «manage-users»), Entscheid 16.11.2026. */
    private function notifyAdmins(User $applicant): void
    {
        if (! MailSetup::configured()) {
            return;
        }
        $roleIds = DB::table('role_permissions')->where('permission', 'manage-users')->pluck('role_id');
        $admins = User::query()->where('status', 'active')->whereKeyNot($applicant->id)
            ->whereIn('id', DB::table('user_roles')->whereIn('role_id', $roleIds)->select('user_id'))->get();
        foreach ($admins as $admin) {
            try {
                Mail::to($admin->email)->send(new RoleRequestSubmitted($applicant->name));
            } catch (Throwable $e) {
                Log::warning('Mail «Neue Bewerbung» fehlgeschlagen', ['exception' => $e::class]);
            }
        }
    }

    public function withdraw(User $user, RoleRequest $request): void
    {
        if ($request->user_id !== $user->id || $request->status !== RoleRequest::OPEN) {
            throw new AccessDenied('Diese Bewerbung kann nicht zurückgezogen werden.');
        }
        $request->delete();
        Audit::log($user, $user, 'role.request_withdrawn', ['rolle' => 'Moderator']);
    }

    public function decide(User $actor, RoleRequest $request, bool $accept, ?string $note): void
    {
        if (! $actor->hasPermission('manage-users')) {
            throw new AccessDenied('Über Bewerbungen entscheiden nur Admins.');
        }
        if ($request->status !== RoleRequest::OPEN) {
            throw new AccessDenied('Über diese Bewerbung wurde schon entschieden.');
        }
        $note = $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 1000) : null;
        if (! $accept && $note === null) {
            throw new AccessDenied('Bitte die Ablehnung kurz begründen.');
        }
        $target = $request->user;
        DB::transaction(function () use ($actor, $request, $accept, $note, $target): void {
            if ($accept) {
                $this->roles->assign($actor, $target, Role::byKey(Role::MODERATOR), 'Bewerbung angenommen');
            }
            $request->forceFill([
                'status' => $accept ? RoleRequest::ACCEPTED : RoleRequest::REJECTED,
                'decided_by_id' => $actor->id, 'decided_at' => now(), 'decision_note' => $note,
            ])->save();
            Audit::log($actor, $target, $accept ? 'role.request_accepted' : 'role.request_rejected', ['rolle' => 'Moderator']);
        });
        if (MailSetup::configured()) {
            try {
                Mail::to($target->email)->send(new RoleRequestDecided($accept, $note));
            } catch (Throwable $e) {
                Log::warning('Mail «Bewerbung entschieden» fehlgeschlagen', ['exception' => $e::class]);
            }
        }
    }

    public static function openCount(): int
    {
        return RoleRequest::query()->where('status', RoleRequest::OPEN)->count();
    }

    /** Abgeschlossene Anfragen nach der Frist löschen; liefert die Anzahl. */
    public static function prune(): int
    {
        return RoleRequest::query()->where('status', '!=', RoleRequest::OPEN)
            ->where('decided_at', '<', now()->subMonths(RoleRequest::RETENTION_MONTHS))->delete();
    }
}
