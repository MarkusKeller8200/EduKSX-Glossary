<?php

namespace App\Http\Controllers\Admin;

use App\Access\AccessDenied;
use App\Access\AccountManager;
use App\Access\Audit;
use App\Access\RoleManager;
use App\Enums\InstitutionKind;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\Controller;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAudit;
use App\Models\UserIdentity;
use Illuminate\Http\JsonResponse;
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
        'mfa.reset_requested' => 'Link zum Zurücksetzen des zweiten Faktors angefordert',
        'password.reset_sent' => 'Link zum Zurücksetzen des Passworts gesendet',
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

        // Organisation je Konto (#412): aktive Mitgliedschaft in einer nicht gelöschten Organisation
        $memberships = self::memberships();

        $users = User::query()->orderBy('last_name')->orderBy('first_name')->get()->map(fn (User $u): array => $u->is_processing_restricted ? [
            // Bearbeitung eingeschränkt (#312, DB B13): Daten nicht anzeigen
            'id' => $u->id, 'name' => 'Bearbeitung eingeschränkt', 'community_name' => null, 'einrichtung' => '', 'email' => '',
            'rollen' => [], 'status' => $u->status, 'zweiter_faktor' => false,
            'organisation' => null, 'letzte_anmeldung' => null, 'angelegt' => null,
        ] : [
            'id' => $u->id,
            'name' => $u->name,
            'community_name' => $u->community_name,
            'einrichtung' => $u->institutionLabel(),
            'email' => $u->email,
            'rollen' => ($assignments[$u->id] ?? collect())->map(fn ($a): string => (string) ($roleNames[$a->role_id] ?? ''))->values()->all(),
            'status' => $u->status,
            'zweiter_faktor' => $u->hasTwoFactor(),
            'organisation' => $memberships[$u->id] ?? null,
            'letzte_anmeldung' => $u->last_login_on?->toDateString(),
            'angelegt' => $u->created_at?->toDateString(),
        ])->all();

        return Inertia::render('admin/users', ['benutzer' => $users]);
    }

    /**
     * Organisation je Konto (#412, #414): aktive Mitgliedschaft in einer nicht gelöschten Organisation.
     *
     * @return array<string, array{id: string, name: string, rolle: string}>
     */
    private static function memberships(?string $userId = null): array
    {
        $out = [];
        OrganizationMember::query()->with('organization')
            ->where('status', 'active')
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->whereHas('organization')
            ->get()
            ->each(function (OrganizationMember $m) use (&$out): void {
                $out[$m->user_id] = [
                    'id' => $m->organization->id,
                    'name' => $m->organization->name,
                    'rolle' => $m->isAdmin() ? 'Organisations-Admin' : 'Mitglied',
                ];
            });

        return $out;
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
        Audit::log($this->currentUser($request), $user, 'account.created');
        $this->roles->assignDefault($user, 'Kontoanlage');

        return redirect("/admin/benutzer/{$user->id}")->with('status', 'Konto angelegt (Rolle Benutzer). Weitere Rollen jetzt zuweisen.');
    }

    /** Daten eines Kontos für eine Anfrage per Brief (#312, DB-Standard B8); Aufruf im Sicherheitsprotokoll. */
    public function data(Request $request, User $user): JsonResponse
    {
        Audit::log($this->currentUser($request), $user, 'account.data_exported');

        return AccountController::download($user);
    }

    public function show(Request $request, User $user): Response|RedirectResponse
    {
        $actor = $this->currentUser($request);
        if ($user->is_processing_restricted) {
            // Bearbeitung eingeschränkt (#312, DB B13): nicht anzeigen; Auskunft über «Daten exportieren» bleibt
            return redirect('/admin/benutzer')->with('status', 'Die Bearbeitung dieses Kontos ist eingeschränkt (Aufheben: php artisan glossary:einschraenken --aufheben).');
        }
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
                'grund' => is_array($e->new_values) && isset($e->new_values['reason']) ? (string) $e->new_values['reason'] : null,
            ])->all();

        return Inertia::render('admin/user-show', [
            'benutzer' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'community_name' => $user->community_name,
                'funktion' => $user->function,
                'einrichtung' => $user->institutionLabel(),
                'letzte_anmeldung' => $user->last_login_on?->toDateString(),
                'status' => $user->status,
                'gesperrt_am' => $user->blocked_at?->toIso8601String(),
                'zweiter_faktor' => $user->hasTwoFactor(),
                'angelegt' => $user->created_at?->toIso8601String(),
                // Alle Angaben lesend (#414), ohne Geheimnisse (Passwort, zweiter Faktor, Tokens) – docs/DATENKLASSEN.md
                'angaben' => [
                    'vorname' => $user->first_name,
                    'nachname' => $user->last_name,
                    'art_einrichtung' => InstitutionKind::tryFrom((string) $user->institution_kind)?->label(),
                    'einrichtung_name' => $user->institution_name,
                    'einheitsgemeinde' => $user->is_unified_municipality,
                    'organisation' => self::memberships($user->id)[$user->id] ?? null,
                    'passwort_gesetzt' => $user->has_password,
                    'zweiter_faktor_seit' => $user->two_factor_confirmed_at?->toIso8601String(),
                    'anmeldedienste' => $user->identities()->orderBy('created_at')->get()->map(fn (UserIdentity $i): array => [
                        'anbieter' => UserIdentity::PROVIDERS[$i->provider] ?? $i->provider,
                        'seit' => $i->created_at?->toDateString(),
                        'zuletzt' => $i->last_used_at?->toDateString(),
                    ])->all(),
                    'datenschutz_version' => $user->privacy_notice_version,
                    'datenschutz_am' => $user->privacy_notice_acknowledged_at?->toIso8601String(),
                    'news' => $user->has_news_opt_in,
                    'news_am' => $user->news_answered_at?->toIso8601String(),
                    'hinweis_inaktiv_am' => $user->inactivity_notice_sent_at?->toIso8601String(),
                ],
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
        $actor = $this->currentUser($request);

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
        return $this->account(fn () => $this->accounts->block($this->currentUser($request), $user), 'Konto gesperrt – Sitzungen beendet.');
    }

    public function unblock(Request $request, User $user): RedirectResponse
    {
        return $this->account(fn () => $this->accounts->unblock($this->currentUser($request), $user), 'Konto entsperrt.');
    }

    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        return $this->account(fn () => $this->accounts->sendPasswordReset($this->currentUser($request), $user), 'Link zum Zurücksetzen des Passworts gesendet (60 Minuten gültig).');
    }

    public function resetTwoFactor(Request $request, User $user): RedirectResponse
    {
        return $this->account(fn () => $this->accounts->resetTwoFactor($this->currentUser($request), $user), 'Zweiter Faktor zurückgesetzt – bei der nächsten Anmeldung neu einrichten.');
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
}
