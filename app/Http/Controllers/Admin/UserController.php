<?php

namespace App\Http\Controllers\Admin;

use App\Access\AccessDenied;
use App\Access\AccountManager;
use App\Access\Audit;
use App\Access\RoleManager;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Benutzerverwaltung (#38, docs/KONZEPT-BENUTZER.md 3 und 5.4): Liste, Detail mit Rollen, sperren, zweiten Faktor
 * zurücksetzen, Protokoll. Alle Änderungen über RoleManager/AccountManager (Schutzregeln, Audit).
 */
class UserController extends Controller
{
    /** Bezeichnungen der Protokoll-Aktionen. */
    public const ACTIONS = [
        'role.assigned' => 'Rolle vergeben',
        'role.revoked' => 'Rolle entzogen',
        'role.permissions' => 'Rechte der Rolle geändert',
        'role.created' => 'Rolle angelegt',
        'account.created' => 'Konto angelegt',
        'account.blocked' => 'Konto gesperrt',
        'account.unblocked' => 'Konto entsperrt',
        'mfa.enabled' => 'Zweiter Faktor eingerichtet',
        'mfa.reset' => 'Zweiter Faktor zurückgesetzt',
        'mfa.recovery_codes' => 'Neue Wiederherstellungscodes',
        'mfa.recovery_used' => 'Wiederherstellungscode verwendet',
        'account.password' => 'Passwort geändert',
        'account.deleted' => 'Konto gelöscht',
        'system.testmail' => 'Testmail ausgelöst',
        'account.registered' => 'Registriert',
        'account.verified' => 'E-Mail bestätigt',
        'account.password_reset' => 'Passwort über Link zurückgesetzt',
        'privacy.acknowledged' => 'Datenschutzerklärung zur Kenntnis genommen',
        'identity.linked' => 'Anmeldedienst verknüpft',
        'identity.unlinked' => 'Anmeldedienst gelöst',
    ];

    public function __construct(private readonly RoleManager $roles, private readonly AccountManager $accounts) {}

    public function index(): Response
    {
        $roleNames = Role::query()->pluck('name', 'id');
        $assignments = DB::table('user_roles')->get()->groupBy('user_id');

        $users = User::query()->orderBy('last_name')->orderBy('first_name')->get()->map(fn (User $u): array => [
            'id' => $u->id,
            'name' => $u->name,
            'community_name' => $u->community_name,
            'einrichtung' => trim(($u->institution_name ?? '').($u->institution_type !== null ? ' ('.(User::INSTITUTION_TYPES[$u->institution_type] ?? $u->institution_type).')' : '')),
            'email' => $u->email,
            'rollen' => ($assignments[$u->id] ?? collect())->map(fn ($a): string => (string) ($roleNames[$a->role_id] ?? ''))->values()->all(),
            'status' => $u->status,
            'zweiter_faktor' => $u->hasTwoFactor(),
        ])->all();

        return Inertia::render('admin/users', ['benutzer' => $users]);
    }

    /** Konto administrativ anlegen (solange keine Selbstregistrierung möglich ist). */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
        ]);
        $user = User::query()->create([
            'first_name' => $data['first_name'], 'last_name' => $data['last_name'],
            'email' => strtolower((string) $data['email']), 'password' => $data['password'],
        ]);
        Audit::log($this->actor($request), $user, 'account.created');
        $this->roles->assignDefault($user, 'Kontoanlage');

        return redirect("/admin/benutzer/{$user->id}")->with('status', 'Konto angelegt (Rolle Benutzer). Weitere Rollen jetzt zuweisen.');
    }

    public function show(Request $request, User $user): Response
    {
        $actor = $this->actor($request);
        $assigned = $user->roleKeys();
        $roles = Role::query()->orderBy('sort_order')->get()->map(fn (Role $r): array => [
            'key' => $r->key,
            'name' => $r->name,
            'beschreibung' => $r->description,
            'zugewiesen' => in_array($r->key, $assigned, true),
            // Anzeige: nur innerhalb der eigenen Befugnis änderbar (geprüft wird im RoleManager)
            'aenderbar' => $actor->isDeveloper()
                || ($r->key !== Role::DEVELOPER && $actor->id !== $user->id
                    && array_diff($r->permissionKeys(), $actor->permissions()) === []),
        ])->all();

        $names = User::query()->pluck('name', 'id');
        $roleNames = Role::query()->pluck('name', 'id');
        $audit = UserAudit::query()->where('target_user_id', $user->id)->latest('id')->limit(100)->get()
            ->map(fn (UserAudit $e): array => [
                'id' => $e->id,
                'am' => $e->created_at->toIso8601String(),
                'aktion' => self::ACTIONS[$e->action] ?? $e->action,
                'rolle' => $e->target_role_id !== null ? ($roleNames[$e->target_role_id] ?? null) : null,
                'von' => $e->actor_id !== null ? ($names[$e->actor_id] ?? 'gelöschtes Konto') : ($e->actor_label ?? 'System'),
                'ergebnis' => $e->result,
                'grund' => is_array($e->new) && isset($e->new['reason']) ? (string) $e->new['reason'] : null,
            ])->all();

        return Inertia::render('admin/user-show', [
            'benutzer' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'community_name' => $user->community_name,
                'funktion' => $user->function,
                'einrichtung' => trim(($user->institution_name ?? '').($user->institution_type !== null ? ' ('.(User::INSTITUTION_TYPES[$user->institution_type] ?? $user->institution_type).')' : '')),
                'letzte_anmeldung' => $user->last_login_on?->toDateString(),
                'status' => $user->status,
                'gesperrt_am' => $user->blocked_at?->toIso8601String(),
                'zweiter_faktor' => $user->hasTwoFactor(),
                'angelegt' => $user->created_at?->toIso8601String(),
                'rechte' => $user->permissions(),
                'selbst' => $actor->id === $user->id,
            ],
            'rollen' => $roles,
            'protokoll' => $audit,
        ]);
    }

    /** Rollen setzen: Unterschiede einzeln über den RoleManager (jede Änderung geprüft und protokolliert). */
    public function updateRoles(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['rollen' => ['array'], 'rollen.*' => ['string']]);
        $wanted = array_values(array_map('strval', (array) ($data['rollen'] ?? [])));
        $current = $user->roleKeys();
        $actor = $this->actor($request);

        try {
            DB::transaction(function () use ($actor, $user, $wanted, $current): void {
                foreach (array_diff($wanted, $current) as $key) {
                    $this->roles->assign($actor, $user, Role::byKey($key));
                }
                foreach (array_diff($current, $wanted) as $key) {
                    $this->roles->revoke($actor, $user, Role::byKey($key));
                }
            });
        } catch (AccessDenied $e) {
            // abgewiesener Versuch bleibt im Protokoll (ausserhalb der zurückgerollten Transaktion)
            Audit::log($actor, $user, 'role.assigned', ['reason' => $e->getMessage(), 'requested' => $wanted], 'denied');

            throw ValidationException::withMessages(['rollen' => $e->getMessage()]);
        }

        return back()->with('status', 'Rollen gespeichert.');
    }

    public function block(Request $request, User $user): RedirectResponse
    {
        return $this->account(fn () => $this->accounts->block($this->actor($request), $user), 'Konto gesperrt – Sitzungen beendet.');
    }

    public function unblock(Request $request, User $user): RedirectResponse
    {
        return $this->account(fn () => $this->accounts->unblock($this->actor($request), $user), 'Konto entsperrt.');
    }

    public function resetTwoFactor(Request $request, User $user): RedirectResponse
    {
        return $this->account(fn () => $this->accounts->resetTwoFactor($this->actor($request), $user), 'Zweiter Faktor zurückgesetzt – bei der nächsten Anmeldung neu einrichten.');
    }

    // ---------- intern ----------

    private function account(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (AccessDenied $e) {
            throw ValidationException::withMessages(['konto' => $e->getMessage()]);
        }

        return back()->with('status', $message);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
