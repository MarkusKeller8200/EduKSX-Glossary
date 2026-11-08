<?php

namespace App\Http\Controllers;

use App\Access\AccessDenied;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\OrganizationTermOverride;
use App\Models\ServiceAccount;
use App\Models\User;
use App\Organizations\OrganizationService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Meine Organisation (#148, DEC-025): gründen, einladen, Mitglieder verwalten, austreten. Regeln und Protokoll im
 * OrganizationService; hier nur Anzeige und Weiterleitung der Meldungen.
 */
class OrganizationController extends Controller
{
    private const ROLLEN = [OrganizationMember::ADMIN => 'Organisations-Admin', OrganizationMember::MEMBER => 'Mitglied'];

    public function __construct(private readonly OrganizationService $organizations) {}

    public function show(Request $request): Response
    {
        $user = $this->currentUser($request);
        $member = $this->organizations->membershipOf($user);
        $organization = $member?->organization;
        $admin = $member !== null && $member->isAdmin() && $member->isActive();

        $props = [
            'organisation' => null,
            'mitglieder' => [],
            'einladungen' => [],
            'anpassungen' => [],
            'servicekonten' => 0,
            'meine_einladungen' => array_map(fn (OrganizationInvitation $i): array => [
                'id' => $i->id,
                'organisation' => $i->organization->name,
                'gueltig_bis' => $i->expires_at->toDateString(),
            ], $this->organizations->invitationsFor($user)),
            'darf_gruenden' => $member === null && $user->hasPermission('create-organization'),
        ];
        if ($member === null || $organization === null) {
            return Inertia::render('organization/show', $props);
        }

        $props['organisation'] = [
            'name' => $organization->name,
            'rolle' => self::ROLLEN[$member->role] ?? $member->role,
            'gesperrt' => ! $member->isActive(),
            'admin' => $admin,
            'zwei_faktor' => $user->hasTwoFactor(),
            'gegruendet' => $organization->created_at?->toDateString(),
        ];
        if (! $member->isActive()) {
            return Inertia::render('organization/show', $props);
        }

        $props['mitglieder'] = $organization->members()->with('user')->orderByRaw("case when role = 'admin' then 0 else 1 end")->get()
            ->sortBy(fn (OrganizationMember $m): string => mb_strtolower($m->user->name))->values()
            ->map(fn (OrganizationMember $m): array => [
                'id' => $m->id,
                'name' => $m->user->name,
                // E-Mail und Status nur für Organisations-Admins
                'email' => $admin ? $m->user->email : null,
                'rolle' => self::ROLLEN[$m->role] ?? $m->role,
                'admin' => $m->isAdmin(),
                'gesperrt' => ! $m->isActive(),
                'ich' => $m->user_id === $user->id,
                'seit' => $m->created_at?->toDateString(),
            ])->all();
        $props['anpassungen'] = OrganizationTermOverride::query()->where('organization_id', $organization->id)->with('term')->get()
            ->map(fn (OrganizationTermOverride $o): array => [
                'slug' => $o->term->slug,
                'titel' => trim(($o->abbreviation_override ?? '').' '.($o->name_override ?? '')) ?: $o->term->slug,
                'geaendert' => $o->updated_at?->toDateString(),
            ])->sortBy('slug')->values()->all();
        if ($admin) {
            $names = User::query()->whereIn('id', $organization->invitations()->whereNotNull('invited_by')->select('invited_by'))->pluck('name', 'id');
            $props['einladungen'] = $organization->invitations()->where('expires_at', '>', now())->orderBy('email')->get()
                ->map(fn (OrganizationInvitation $i): array => [
                    'id' => $i->id,
                    'email' => $i->email,
                    'gueltig_bis' => $i->expires_at->toDateString(),
                    'von' => $i->invited_by !== null ? ($names[$i->invited_by] ?? null) : null,
                ])->all();
            $props['servicekonten'] = ServiceAccount::query()->where('organization_id', $organization->id)->count();
        }

        return Inertia::render('organization/show', $props);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);

        return $this->run(fn () => $this->organizations->create($this->currentUser($request), (string) $data['name']),
            'Organisation gegründet – Sie sind Organisations-Admin. Für die Verwaltung braucht es den zweiten Faktor.', 'name');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $user = $this->currentUser($request);

        return $this->run(fn () => $this->organizations->rename($user, $this->organizationOf($user), (string) $data['name']), 'Name gespeichert.', 'name');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $this->currentUser($request);

        return $this->run(fn () => $this->organizations->delete($user, $this->organizationOf($user)), 'Organisation gelöscht.');
    }

    public function invite(Request $request): RedirectResponse
    {
        $data = $request->validate(['adressen' => ['required', 'string', 'max:5000']]);
        $user = $this->currentUser($request);
        $result = null;
        $response = $this->run(function () use ($user, $data, &$result): void {
            $result = $this->organizations->invite($user, $this->organizationOf($user), (string) $data['adressen']);
        }, '', 'adressen');
        if ($result === null) {
            return $response;
        }
        $message = count($result['eingeladen']).' eingeladen';
        if ($result['uebersprungen'] !== []) {
            $message .= ', schon Mitglied: '.implode(', ', $result['uebersprungen']);
        }
        if ($result['ungueltig'] !== []) {
            $message .= ', ungültig: '.implode(', ', $result['ungueltig']);
        }

        return back()->with('status', $message.'.');
    }

    public function revokeInvitation(Request $request, OrganizationInvitation $invitation): RedirectResponse
    {
        return $this->run(fn () => $this->organizations->revokeInvitation($this->currentUser($request), $invitation), 'Einladung zurückgezogen.');
    }

    public function accept(Request $request, OrganizationInvitation $invitation): RedirectResponse
    {
        return $this->run(fn () => $this->organizations->accept($this->currentUser($request), $invitation), 'Willkommen in der Organisation.');
    }

    public function decline(Request $request, OrganizationInvitation $invitation): RedirectResponse
    {
        return $this->run(fn () => $this->organizations->decline($this->currentUser($request), $invitation), 'Einladung abgelehnt.');
    }

    public function block(Request $request, OrganizationMember $member): RedirectResponse
    {
        return $this->run(fn () => $this->organizations->setBlocked($this->currentUser($request), $member, true), 'Mitglied gesperrt.');
    }

    public function unblock(Request $request, OrganizationMember $member): RedirectResponse
    {
        return $this->run(fn () => $this->organizations->setBlocked($this->currentUser($request), $member, false), 'Mitglied entsperrt.');
    }

    public function role(Request $request, OrganizationMember $member): RedirectResponse
    {
        $admin = $request->boolean('admin');

        return $this->run(fn () => $this->organizations->setAdmin($this->currentUser($request), $member, $admin),
            $admin ? 'Zum Organisations-Admin ernannt.' : 'Organisations-Admin entzogen.');
    }

    public function remove(Request $request, OrganizationMember $member): RedirectResponse
    {
        return $this->run(fn () => $this->organizations->remove($this->currentUser($request), $member), 'Mitglied aus der Organisation entfernt.');
    }

    public function leave(Request $request): RedirectResponse
    {
        return $this->run(fn () => $this->organizations->leave($this->currentUser($request)), 'Sie haben die Organisation verlassen.');
    }

    private function organizationOf(User $user): Organization
    {
        $organization = $this->organizations->membershipOf($user)?->organization;
        if ($organization === null) {
            throw new AccessDenied('Sie gehören keiner Organisation an.');
        }

        return $organization;
    }

    /** Aktion ausführen; Abweisungen als Fehlermeldung zurück zur Seite. */
    private function run(Closure $action, string $message, string $field = 'organisation'): RedirectResponse
    {
        try {
            $action();
        } catch (AccessDenied $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }

        return $message === '' ? back() : back()->with('status', $message);
    }
}
