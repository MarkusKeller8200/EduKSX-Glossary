<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\ServiceAccount;
use App\Models\User;
use App\Organizations\OrganizationService;
use App\Support\History;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Organisationen in der Administration (#413, #414): Liste neben «Benutzer» und Detail nur lesend. Ändern bleibt bei
 * den Organisations-Admins («Meine Organisation») bzw. bei Sperren und Rollen der Konten; Token und Geheimnisse der
 * Servicekonten erscheinen nie.
 */
class OrganizationAdminController extends Controller
{
    public function index(): Response
    {
        $organizations = Organization::query()->withCount([
            'members as mitglieder' => fn ($q) => $q->where('status', 'active'),
            'invitations as einladungen' => fn ($q) => $q->where('expires_at', '>', now()),
        ])->with(['members' => fn ($q) => $q->where('role', 'admin')->where('status', 'active')->with('user:id,first_name,last_name,name')])
            ->orderBy('name')->get()
            ->map(fn (Organization $o): array => [
                'id' => $o->id,
                'name' => $o->name,
                'mitglieder' => (int) $o->getAttribute('mitglieder'),
                'einladungen' => (int) $o->getAttribute('einladungen'),
                'admins' => $o->members->map(fn (OrganizationMember $m): string => $m->user->name)->filter()->values()->all(),
                'microsoft' => $o->entra_tenant_id !== null,
                'angelegt' => $o->created_at?->toDateString(),
            ])->all();

        return Inertia::render('admin/organizations', ['organisationen' => $organizations]);
    }

    public function show(Organization $organization): Response
    {
        $creator = $organization->created_by_id !== null ? User::query()->find($organization->created_by_id) : null;

        return Inertia::render('admin/organization-show', [
            'organisation' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'plattform_id' => $organization->platform_id,
                'microsoft_tenant' => $organization->entra_tenant_id,
                'domain' => $organization->email_domain,
                'domain_automatisch' => $organization->is_domain_auto_join,
                'angelegt' => $organization->created_at?->toIso8601String(),
                'angelegt_von' => $creator?->name,
                'mitglieder' => $organization->members()->with('user')->orderBy('created_at')->get()
                    ->map(fn (OrganizationMember $m): array => [
                        'user_id' => $m->user_id,
                        'name' => $m->user->is_processing_restricted ? 'Bearbeitung eingeschränkt' : $m->user->name,
                        'email' => $m->user->is_processing_restricted ? '' : $m->user->email,
                        'rolle' => $m->isAdmin() ? 'Organisations-Admin' : 'Mitglied',
                        'status' => $m->status,
                        'zweiter_faktor' => $m->user->hasTwoFactor(),
                        'seit' => $m->created_at?->toDateString(),
                    ])->all(),
                'einladungen' => $organization->invitations()->orderBy('created_at')->get()
                    ->map(fn (OrganizationInvitation $i): array => [
                        'email' => $i->email,
                        'gueltig_bis' => $i->expires_at->toDateString(),
                        'abgelaufen' => $i->expires_at->isPast(),
                    ])->all(),
                'servicekonten' => ServiceAccount::query()->where('organization_id', $organization->id)->orderBy('name')->get()
                    ->map(fn (ServiceAccount $a): array => [
                        'id' => $a->id,
                        'name' => $a->name,
                        'zweck' => $a->purpose,
                        'status' => $a->status,
                        'scopes' => $a->scopes,
                    ])->all(),
            ],
            // Verlauf (#417)
            'verlauf' => History::forOrganization($organization),
        ]);
    }

    /** Domain einer Organisation lösen (#462), z. B. wenn sie zu Unrecht beansprucht wurde. */
    public function clearDomain(Request $request, Organization $organization, OrganizationService $organizations): RedirectResponse
    {
        $organizations->clearDomainByAdmin($this->currentUser($request), $organization);

        return back()->with('status', 'Domain gelöst; die automatische Aufnahme ist aus.');
    }
}
