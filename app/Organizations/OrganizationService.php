<?php

namespace App\Organizations;

use App\Access\AccessDenied;
use App\Access\Audit;
use App\Access\RoleManager;
use App\Enums\MemberRole;
use App\Enums\MemberStatus;
use App\Mail\OrganizationInvitationMail;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\OrganizationTermOverride;
use App\Models\Proposal;
use App\Models\Role;
use App\Models\ServiceAccount;
use App\Models\Term;
use App\Models\TermUserOverride;
use App\Models\User;
use App\Models\UserIdentity;
use App\Proposals\ProposalService;
use App\Support\MailSetup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Einzige Stelle, die Organisationen ändert (#148, DEC-025, docs/KONZEPT-ORGANISATION.md).
 *
 * - Gründen ab Recht «create-organization» (Benutzer); die gründende Person wird Organisations-Admin.
 * - Höchstens eine Organisation je Konto.
 * - Verwalten (Name, Einladungen, Mitglieder, Darstellung) nur aktive Organisations-Admins dieser Organisation mit
 *   bestätigtem zweitem Faktor.
 * - Es bleibt immer mindestens ein Organisations-Admin, solange die Organisation Mitglieder hat.
 * - Einladungen nimmt die eingeladene Person aktiv an (bestätigt 08.11.2026); nach dem Entfernen ist eine neue
 *   Einladung möglich.
 * - Vorschläge von Mitgliedern bestätigt ein Organisations-Admin, bevor sie in die Moderation gehen.
 * - Die persönliche Darstellung der Mitglieder gibt ein Organisations-Admin frei (eigene Texte ohne Freigabe).
 * - Wer Organisations-Admin wird, erhält die globale Rolle «Organisations-Admin» (Servicekonten, zweiter Faktor);
 *   beim Entzug wird sie nur entfernt, wenn die Organisation sie vergeben hat.
 *
 * Jede Änderung und jeder abgewiesene Versuch landet im Audit-Protokoll (Aktionen «organization.*»).
 */
final class OrganizationService
{
    /** Bezeichnung im Rollen-Protokoll, wenn die Organisation die Rolle vergibt oder entzieht. */
    public const LABEL = 'Organisation';

    public function __construct(private readonly RoleManager $roles) {}

    /** Mitgliedschaft eines Kontos (auch gesperrt). */
    public function membershipOf(User $user): ?OrganizationMember
    {
        return OrganizationMember::query()->where('user_id', $user->id)->with('organization')->first();
    }

    /** Organisation, deren Darstellung für das Konto gilt: nur aktive Mitglieder eines aktiven Kontos. */
    public function activeOrganizationOf(?User $user): ?Organization
    {
        if ($user === null || ! $user->isActive()) {
            return null;
        }
        $member = $this->membershipOf($user);

        return $member !== null && $member->isActive() ? $member->organization : null;
    }

    public function isAdmin(User $user, Organization $organization): bool
    {
        $member = $this->membershipOf($user);

        return $member !== null && $member->organization_id === $organization->id && $member->isAdmin() && $member->isActive();
    }

    public function create(User $actor, string $name): Organization
    {
        $name = trim($name);
        $this->guard($actor, null, 'organization.created', function () use ($actor, $name): void {
            if (! $actor->isActive() || ! $actor->hasPermission('create-organization')) {
                throw new AccessDenied('Dafür fehlt das Recht «Organisation gründen».');
            }
            if ($this->membershipOf($actor) !== null) {
                throw new AccessDenied('Ein Konto gehört höchstens einer Organisation an.');
            }
            $this->requireFreeName($name);
        }, ['name' => $name]);

        return DB::transaction(function () use ($actor, $name): Organization {
            $organization = Organization::query()->create(['name' => $name, 'created_by_id' => $actor->id]);
            $member = $organization->members()->create(['user_id' => $actor->id, 'role' => OrganizationMember::ADMIN, 'status' => 'active']);
            $this->grantRole($member);

            return $organization;
        });
    }

    public function rename(User $actor, Organization $organization, string $name): void
    {
        $name = trim($name);
        $this->guard($actor, null, 'organization.renamed', function () use ($actor, $organization, $name): void {
            $this->requireAdmin($actor, $organization);
            if ($name !== $organization->name) {
                $this->requireFreeName($name);
            }
        }, ['organization' => $organization->name, 'name' => $name]);
        $organization->forceFill(['name' => $name])->save();
    }

    /**
     * Microsoft-Tenant der Organisation setzen oder lösen (X3, #284). Nur ein Tenant, mit dem der Organisations-Admin
     * selbst mit Microsoft verknüpft ist (Nachweis), nie der Tenant privater Konten; je Tenant eine Organisation.
     */
    public function setTenant(User $actor, Organization $organization, ?string $tenant): void
    {
        $tenant = $tenant !== null && trim($tenant) !== '' ? mb_strtolower(trim($tenant)) : null;
        $this->guard($actor, null, 'organization.tenant', function () use ($actor, $organization, $tenant): void {
            $this->requireAdmin($actor, $organization);
            if ($tenant === null) {
                return;
            }
            if ($tenant === UserIdentity::MICROSOFT_CONSUMER_TENANT || ! in_array($tenant, $this->tenantsOf($actor), true)) {
                throw new AccessDenied('Nur ein Tenant, mit dem Sie selbst über Microsoft angemeldet sind (Geschäfts- oder Schulkonto).');
            }
            if (Organization::query()->where('entra_tenant_id', $tenant)->whereKeyNot($organization->id)->exists()) {
                throw new AccessDenied('Dieser Tenant gehört schon zu einer anderen Organisation.');
            }
        }, ['organization' => $organization->name, 'tenant' => $tenant]);
        $organization->forceFill(['entra_tenant_id' => $tenant])->save();
    }

    /**
     * Microsoft-Tenants der verknüpften Identitäten (ohne private Konten).
     *
     * @return list<string>
     */
    public function tenantsOf(User $user): array
    {
        return array_values(array_unique(array_map('strval', $user->identities()->where('provider', 'microsoft')->whereNotNull('tenant_id')
            ->where('tenant_id', '!=', UserIdentity::MICROSOFT_CONSUMER_TENANT)->pluck('tenant_id')->all())));
    }

    /**
     * Bei der Microsoft-Anmeldung (X3, #284): Gehört der Tenant zu einer Organisation und ist das Konto in keiner,
     * entsteht eine Einladung – die Person entscheidet selbst (keine automatische Mitgliedschaft, keine Rechte).
     */
    public function inviteByTenant(User $user, ?string $tenant): void
    {
        if ($tenant === null || $tenant === UserIdentity::MICROSOFT_CONSUMER_TENANT || $this->membershipOf($user) !== null) {
            return;
        }
        $organization = Organization::query()->where('entra_tenant_id', mb_strtolower($tenant))->first();
        if ($organization === null) {
            return;
        }
        $invitation = OrganizationInvitation::query()->firstOrNew(['organization_id' => $organization->id, 'email' => mb_strtolower($user->email)]);
        if (! $invitation->exists || $invitation->expires_at->isPast()) {
            $invitation->forceFill(['invited_by_id' => null, 'expires_at' => now()->addDays(OrganizationInvitation::DAYS)])->save();
            Audit::log(null, $user, 'organization.invited_by_tenant', ['organization' => $organization->name]);
        }
    }

    /** Organisation mit Darstellung, Einladungen und Servicekonten löschen; Konten bleiben bestehen. */
    public function delete(User $actor, Organization $organization): void
    {
        $this->guard($actor, null, 'organization.deleted', fn () => $this->requireAdmin($actor, $organization), ['organization' => $organization->name]);
        $this->dissolve($organization);
    }

    /**
     * Einladen: Adressen getrennt mit «;», «,», Leerzeichen oder Zeilenumbruch. Bestehende Einladungen werden
     * verlängert; Mitglieder dieser Organisation übersprungen.
     *
     * @return array{eingeladen: list<string>, uebersprungen: list<string>, ungueltig: list<string>}
     */
    public function invite(User $actor, Organization $organization, string $addresses): array
    {
        $this->guard($actor, null, 'organization.invited', fn () => $this->requireAdmin($actor, $organization), ['organization' => $organization->name]);

        $result = ['eingeladen' => [], 'uebersprungen' => [], 'ungueltig' => []];
        $parts = preg_split('/[\s;,]+/', $addresses) ?: [];
        foreach (array_unique(array_map(fn (string $a): string => mb_strtolower(trim($a)), $parts)) as $email) {
            if ($email === '') {
                continue;
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 255) {
                $result['ungueltig'][] = $email;

                continue;
            }
            $isMember = OrganizationMember::query()->where('organization_id', $organization->id)
                ->whereIn('user_id', User::query()->whereRaw('lower(email) = ?', [$email])->select('id'))->exists();
            if ($isMember) {
                $result['uebersprungen'][] = $email;

                continue;
            }
            $expires = now()->addDays(OrganizationInvitation::DAYS);
            OrganizationInvitation::query()->updateOrCreate(
                ['organization_id' => $organization->id, 'email' => $email],
                ['invited_by_id' => $actor->id, 'expires_at' => $expires],
            );
            if (MailSetup::configured()) {
                Mail::to($email)->send(new OrganizationInvitationMail($organization->name, $actor->name, $expires->format('d.m.Y')));
            }
            $result['eingeladen'][] = $email;
        }
        Audit::log($actor, null, 'organization.invitations', ['organization' => $organization->name, 'count' => count($result['eingeladen'])]);

        return $result;
    }

    public function revokeInvitation(User $actor, OrganizationInvitation $invitation): void
    {
        $organization = $invitation->organization;
        $this->guard($actor, null, 'organization.invitation_revoked', fn () => $this->requireAdmin($actor, $organization), ['organization' => $organization->name]);
        $invitation->delete();
    }

    /**
     * Bei der Anmeldung: abgelaufene Einladungen löschen; gibt die Namen der Organisationen zurück, deren Einladung
     * das Konto annehmen kann (nur ohne Mitgliedschaft). Beitreten erst nach Bestätigung unter «Meine Organisation».
     *
     * @return list<string>
     */
    public function pendingInvitationNames(User $user): array
    {
        OrganizationInvitation::query()->where('expires_at', '<=', now())->delete();
        if (! $user->isActive() || $this->membershipOf($user) !== null) {
            return [];
        }

        return array_map(fn (OrganizationInvitation $i): string => $i->organization->name, $this->invitationsFor($user));
    }

    /**
     * Gültige Einladungen an die Adresse des Kontos, älteste zuerst.
     *
     * @return list<OrganizationInvitation>
     */
    public function invitationsFor(User $user): array
    {
        return array_values(OrganizationInvitation::query()->whereRaw('lower(email) = ?', [mb_strtolower($user->email)])
            ->where('expires_at', '>', now())->with('organization')->orderBy('created_at')->orderBy('id')->get()->all());
    }

    /** Einladung ausdrücklich annehmen (nur ohne andere Mitgliedschaft). */
    public function accept(User $user, OrganizationInvitation $invitation): void
    {
        $this->guard($user, $user, 'organization.joined', function () use ($user, $invitation): void {
            $this->requireOwnInvitation($user, $invitation);
            if ($this->membershipOf($user) !== null) {
                throw new AccessDenied('Zuerst die bisherige Organisation verlassen.');
            }
        }, ['organization' => $invitation->organization->name]);
        DB::transaction(function () use ($user, $invitation): void {
            OrganizationMember::query()->create([
                'organization_id' => $invitation->organization_id, 'user_id' => $user->id,
                'role' => OrganizationMember::MEMBER, 'status' => 'active',
            ]);
            $invitation->delete();
        });
    }

    public function decline(User $user, OrganizationInvitation $invitation): void
    {
        $this->guard($user, $user, 'organization.declined', fn () => $this->requireOwnInvitation($user, $invitation), ['organization' => $invitation->organization->name]);
        $invitation->delete();
    }

    public function setBlocked(User $actor, OrganizationMember $member, bool $blocked): void
    {
        $this->guard($actor, $member->user, $blocked ? 'organization.member_blocked' : 'organization.member_unblocked', function () use ($actor, $member, $blocked): void {
            $this->requireAdmin($actor, $member->organization);
            $this->requireNotSelf($actor, $member);
            if ($blocked && $member->isAdmin() && ! $this->otherAdminRemains($member)) {
                throw new AccessDenied('Der letzte Organisations-Admin kann nicht gesperrt werden.');
            }
        }, ['organization' => $member->organization->name]);

        $member->forceFill(['status' => $blocked ? 'blocked' : 'active'])->save();
        // Gesperrte Organisations-Admins verlieren die Verwaltung und damit die globale Rolle
        if ($member->isAdmin()) {
            $blocked ? $this->revokeRole($member) : $this->grantRole($member);
        }
    }

    /** Mitglied aus der Organisation entfernen; das Konto bleibt bestehen. */
    public function remove(User $actor, OrganizationMember $member): void
    {
        $this->guard($actor, $member->user, 'organization.member_removed', function () use ($actor, $member): void {
            $this->requireAdmin($actor, $member->organization);
            $this->requireNotSelf($actor, $member);
            if ($member->isAdmin() && ! $this->otherAdminRemains($member)) {
                throw new AccessDenied('Der letzte Organisations-Admin kann nicht entfernt werden.');
            }
        }, ['organization' => $member->organization->name]);
        $this->drop($member);
    }

    /** Organisations-Admin ernennen oder entziehen (auch sich selbst, wenn ein anderer Admin bleibt). */
    public function setAdmin(User $actor, OrganizationMember $member, bool $admin): void
    {
        $this->guard($actor, $member->user, $admin ? 'organization.admin_granted' : 'organization.admin_revoked', function () use ($actor, $member, $admin): void {
            $this->requireAdmin($actor, $member->organization);
            if ($admin && ! $member->isActive()) {
                throw new AccessDenied('Gesperrte Mitglieder können nicht Organisations-Admin werden.');
            }
            if (! $admin && $member->isAdmin() && ! $this->otherAdminRemains($member)) {
                throw new AccessDenied('Es braucht mindestens einen Organisations-Admin.');
            }
        }, ['organization' => $member->organization->name]);

        if ($admin === $member->isAdmin()) {
            return;
        }
        $member->forceFill(['role' => $admin ? OrganizationMember::ADMIN : OrganizationMember::MEMBER])->save();
        $admin ? $this->grantRole($member) : $this->revokeRole($member);
    }

    /** Selbst austreten; der letzte Organisations-Admin nur, wenn kein anderes Mitglied bleibt (dann löschen). */
    public function leave(User $user): void
    {
        $member = $this->membershipOf($user);
        if ($member === null) {
            return;
        }
        $this->guard($user, $user, 'organization.left', function () use ($member): void {
            if ($member->isAdmin() && ! $this->otherAdminRemains($member)) {
                $others = OrganizationMember::query()->where('organization_id', $member->organization_id)->whereKeyNot($member->id)->exists();
                throw new AccessDenied($others
                    ? 'Zuerst ein anderes Mitglied zum Organisations-Admin ernennen.'
                    : 'Als einziges Mitglied die Organisation löschen statt austreten.');
            }
        }, ['organization' => $member->organization->name]);
        $this->drop($member);
    }

    /**
     * Darstellung eines Begriffs für die Organisation setzen; leere Werte entfernen die Anpassung.
     *
     * @param  array<string, string|list<string>|null>  $values
     */
    public function setDisplay(User $actor, Organization $organization, Term $term, array $values): ?OrganizationTermOverride
    {
        $this->guard($actor, null, 'organization.display', fn () => $this->requireAdmin($actor, $organization), ['organization' => $organization->name, 'term' => $term->slug]);

        $override = OrganizationTermOverride::query()->where('organization_id', $organization->id)->where('term_id', $term->id)->first()
            ?? new OrganizationTermOverride;
        $override->organization_id = $organization->id;
        $override->term_id = $term->id;
        $override->updated_by_id = $actor->id;
        $override->fill($values);
        if ($override->isEmpty()) {
            if ($override->exists) {
                $override->delete();
            }

            return null;
        }
        $override->save();

        return $override;
    }

    /**
     * Kontolöschung (jeder Weg): Ist das Konto der letzte Organisations-Admin, übernimmt das am längsten aktive
     * Mitglied; ohne weitere Mitglieder wird die Organisation gelöscht.
     */
    public static function forget(User $user): void
    {
        $member = OrganizationMember::query()->where('user_id', $user->id)->with('organization')->first();
        if ($member === null) {
            return;
        }
        $service = app(self::class);
        if ($member->isAdmin() && ! $service->otherAdminRemains($member)) {
            $successor = OrganizationMember::query()->where('organization_id', $member->organization_id)->whereKeyNot($member->id)
                ->orderByRaw("case when status = 'active' then 0 else 1 end")->orderBy('created_at')->orderBy('id')->first();
            if ($successor === null) {
                $service->dissolve($member->organization);

                return;
            }
            $successor->forceFill(['role' => OrganizationMember::ADMIN, 'status' => 'active'])->save();
            $service->grantRole($successor);
            Audit::log(null, $successor->user, 'organization.admin_granted', ['organization' => $member->organization->name, 'reason' => 'Kontolöschung'], 'ok', self::LABEL);
        }
        $member->delete();
    }

    /**
     * Vorschlag eines Mitglieds bestätigen (→ Moderation) oder mit Begründung ablehnen.
     */
    public function decideProposal(User $actor, Proposal $proposal, bool $confirm, ?string $reason = null): void
    {
        $organization = $proposal->organization;
        $this->guard($actor, null, $confirm ? 'organization.proposal_confirmed' : 'organization.proposal_rejected', function () use ($actor, $organization): void {
            if ($organization === null) {
                throw new AccessDenied('Dieser Vorschlag gehört zu keiner Organisation.');
            }
            $this->requireAdmin($actor, $organization);
        }, ['organization' => $organization?->name, 'proposal' => $proposal->id]);
        app(ProposalService::class)->decideForOrganization($proposal, $actor, $confirm, $reason);
    }

    /**
     * Persönliche Darstellungen der aktiven Mitglieder, die auf die Freigabe warten.
     *
     * @return list<TermUserOverride>
     */
    public function pendingPersonal(Organization $organization): array
    {
        $members = OrganizationMember::query()->where('organization_id', $organization->id)->where('status', 'active')->select('user_id');

        return array_values(TermUserOverride::query()->whereIn('user_id', $members)
            ->where(fn ($q) => $q->whereNull('approved_organization_id')->orWhere('approved_organization_id', '!=', $organization->id))
            ->with(['user', 'term'])->orderBy('updated_at')->get()->all());
    }

    /** Persönliche Darstellung eines Mitglieds freigeben oder ablehnen (ablehnen löscht sie). */
    public function decidePersonal(User $actor, TermUserOverride $override, bool $approve): void
    {
        $member = $this->membershipOf($override->user);
        $organization = $member?->organization;
        $this->guard($actor, $override->user, $approve ? 'organization.personal_approved' : 'organization.personal_rejected', function () use ($actor, $member, $organization): void {
            if ($member === null || $organization === null || ! $member->isActive()) {
                throw new AccessDenied('Die Person ist kein aktives Mitglied einer Organisation.');
            }
            $this->requireAdmin($actor, $organization);
        }, ['organization' => $organization?->name, 'term' => $override->term->slug]);

        if (! $approve) {
            $override->delete();

            return;
        }
        $override->forceFill(['approved_organization_id' => $organization?->id, 'approved_by_id' => $actor->id, 'approved_at' => now()])->save();
    }

    // ---------- intern ----------

    private function drop(OrganizationMember $member): void
    {
        if ($member->isAdmin()) {
            $this->revokeRole($member);
        }
        // ohne Organisation gilt der normale Weg: offene Vorschläge direkt in die Moderation
        $this->forwardProposals($member->organization_id, $member->user_id, 'Nicht mehr Mitglied der Organisation');
        $member->delete();
    }

    private function forwardProposals(string $organizationId, ?string $userId, string $reason): void
    {
        $ids = Proposal::query()->where('organization_id', $organizationId)->where('status', Proposal::ORG_REVIEW)
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
        ProposalService::forwardFromOrganization($ids, $reason);
    }

    private function dissolve(Organization $organization): void
    {
        DB::transaction(function () use ($organization): void {
            foreach ($organization->members()->with('user')->get() as $member) {
                if ($member->isAdmin()) {
                    $this->revokeRole($member);
                }
            }
            $this->forwardProposals($organization->id, null, 'Organisation gelöscht');
            // Papierkorb (#286): Mitgliedschaften, Einladungen und Servicekonten enden sofort (Konten sind frei, Token
            // ungültig); Name und Darstellung der Organisation bleiben bis zur endgültigen Löschung wiederherstellbar.
            foreach ($organization->members()->get() as $member) {
                $member->delete();
            }
            foreach ($organization->invitations()->get() as $invitation) {
                $invitation->delete();
            }
            foreach (ServiceAccount::query()->where('organization_id', $organization->id)->get() as $account) {
                $account->delete();
            }
            $organization->forceFill(['entra_tenant_id' => null])->save();
            $organization->delete();
        });
    }

    /**
     * Aus dem Papierkorb wiederherstellen (#286, nur Developer): mit Darstellung, ohne Mitglieder; wer gelöscht hat,
     * wird wieder Organisations-Admin, sofern er keiner anderen Organisation angehört.
     */
    public function restore(User $actor, Organization $organization): void
    {
        $this->guard($actor, null, 'organization.restored', function () use ($actor, $organization): void {
            if (! $actor->isDeveloper()) {
                throw new AccessDenied('Wiederherstellen dürfen nur Developer.');
            }
            if (Organization::query()->whereRaw('lower(name) = ?', [mb_strtolower($organization->name)])->exists()) {
                throw new AccessDenied('Eine andere Organisation trägt inzwischen diesen Namen.');
            }
        }, ['organization' => $organization->name]);
        DB::transaction(function () use ($organization): void {
            $by = $organization->deleted_by_id !== null ? User::query()->find($organization->deleted_by_id) : null;
            $organization->restoreFromTrash();
            if ($by !== null && $this->membershipOf($by) === null) {
                $member = OrganizationMember::query()->create([
                    'organization_id' => $organization->id, 'user_id' => $by->id, 'role' => MemberRole::Admin->value, 'status' => MemberStatus::Active->value,
                ]);
                $this->grantRole($member);
            }
        });
    }

    /** Globale Rolle «Organisations-Admin» vergeben, falls das Konto sie noch nicht hat. */
    private function grantRole(OrganizationMember $member): void
    {
        $user = $member->user;
        if (in_array(Role::ORG_ADMIN, $user->roleKeys(), true)) {
            return;
        }
        $this->roles->assign(null, $user, Role::byKey(Role::ORG_ADMIN), self::LABEL);
        $member->forceFill(['is_role_granted' => true])->save();
    }

    /** Globale Rolle nur entziehen, wenn die Organisation sie vergeben hat. */
    private function revokeRole(OrganizationMember $member): void
    {
        if (! $member->is_role_granted) {
            return;
        }
        $this->roles->revoke(null, $member->user, Role::byKey(Role::ORG_ADMIN), self::LABEL);
        $member->forceFill(['is_role_granted' => false])->save();
    }

    private function otherAdminRemains(OrganizationMember $member): bool
    {
        return OrganizationMember::query()->where('organization_id', $member->organization_id)->whereKeyNot($member->id)
            ->where('role', OrganizationMember::ADMIN)->where('status', 'active')->exists();
    }

    private function requireAdmin(User $actor, Organization $organization): void
    {
        if (! $actor->isActive() || ! $this->isAdmin($actor, $organization)) {
            throw new AccessDenied('Das dürfen nur Organisations-Admins dieser Organisation.');
        }
        if (! $actor->hasTwoFactor()) {
            throw new AccessDenied('Für die Verwaltung der Organisation zuerst den zweiten Faktor einrichten.');
        }
    }

    private function requireNotSelf(User $actor, OrganizationMember $member): void
    {
        if ($member->user_id === $actor->id) {
            throw new AccessDenied('Für das eigene Konto «Austreten» verwenden.');
        }
    }

    private function requireOwnInvitation(User $user, OrganizationInvitation $invitation): void
    {
        if (mb_strtolower($invitation->email) !== mb_strtolower($user->email) || ! $invitation->isValid()) {
            throw new AccessDenied('Diese Einladung gilt nicht für dieses Konto.');
        }
    }

    private function requireFreeName(string $name): void
    {
        if ($name === '' || mb_strlen($name) > 120) {
            throw new AccessDenied('Name mit 1 bis 120 Zeichen angeben.');
        }
        if (Organization::query()->withTrashed()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
            throw new AccessDenied('Diesen Namen verwendet schon eine Organisation (auch im Papierkorb).');
        }
    }

    /**
     * @param  callable(): void  $rules
     * @param  array<mixed>  $details
     */
    private function guard(User $actor, ?User $target, string $action, callable $rules, array $details): void
    {
        try {
            $rules();
        } catch (AccessDenied $e) {
            Audit::log($actor, $target, $action, ['reason' => $e->getMessage()] + $details, 'denied');

            throw $e;
        }
        Audit::log($actor, $target, $action, $details);
    }
}
