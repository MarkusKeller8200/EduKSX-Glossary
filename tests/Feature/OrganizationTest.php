<?php

namespace Tests\Feature;

use App\Access\AccessDenied;
use App\Access\Service\ServiceAccountManager;
use App\Glossary\GlossaryService;
use App\Mail\OrganizationInvitationMail;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\OrganizationTermOverride;
use App\Models\Proposal;
use App\Models\Role;
use App\Models\Term;
use App\Models\User;
use App\Organizations\OrganizationService;
use App\Proposals\ProposalService;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Organisationen (#148, DEC-025, docs/KONZEPT-ORGANISATION.md). */
class OrganizationTest extends TestCase
{
    private OrganizationService $organizations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizations = app(OrganizationService::class);
    }

    private function term(): Term
    {
        $glossary = app(GlossaryService::class);

        return $glossary->publish($glossary->save(null, ['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung'], null, true), null);
    }

    /** @return array{0: Organization, 1: User} */
    private function organisation(): array
    {
        $admin = $this->userWithRoles([Role::USER], 'admin@schule.ch');
        $organization = $this->organizations->create($admin, 'Schule Muster');

        return [$organization, $admin->fresh() ?? $admin];
    }

    private function member(Organization $organization, string $email, bool $twoFactor = false): User
    {
        $user = $this->userWithRoles([Role::USER], $email, $twoFactor);
        OrganizationMember::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => 'member', 'status' => 'active']);

        return $user;
    }

    public function test_benutzer_gruendet_und_wird_organisations_admin(): void
    {
        $user = $this->userWithRoles([Role::USER], 'anna@schule.ch');
        $this->actingAs($user)->get('/organisation')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('organization/show')->where('organisation', null)->where('darf_gruenden', true));

        $this->post('/organisation', ['name' => 'Schule Muster'])->assertRedirect()->assertSessionHasNoErrors();
        $member = OrganizationMember::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertTrue($member->isAdmin());
        $this->assertTrue($member->granted_role);
        $this->assertContains(Role::ORG_ADMIN, $user->roleKeys());

        // höchstens eine Organisation, eindeutiger Name
        $this->post('/organisation', ['name' => 'Zweite'])->assertSessionHasErrors('name');
        $other = $this->userWithRoles([Role::USER], 'ben@schule.ch');
        $this->actingAs($other)->post('/organisation', ['name' => 'schule muster'])->assertSessionHasErrors('name');
    }

    public function test_gast_darf_nicht_gruenden(): void
    {
        $this->actingAs($this->userWithRoles([Role::GUEST], 'gast@schule.ch'))->post('/organisation', ['name' => 'X'])
            ->assertSessionHasErrors('name');
        $this->assertSame(0, Organization::query()->count());
    }

    public function test_einladen_mehrere_adressen_und_beitritt_nur_nach_bestaetigung(): void
    {
        [$organization, $admin] = $this->organisation();

        $this->actingAs($admin)->post('/organisation/einladungen', ['adressen' => 'neu@schule.ch; Zwei@Schule.ch,falsch, admin@schule.ch'])
            ->assertRedirect()->assertSessionHas('status');
        $this->assertEqualsCanonicalizing(['neu@schule.ch', 'zwei@schule.ch'], OrganizationInvitation::query()->pluck('email')->all());

        // Anmeldung: Hinweis, aber kein automatischer Beitritt
        $this->app['auth']->forgetGuards();
        $neu = $this->userWithRoles([Role::USER], 'neu@schule.ch', false);
        $this->post('/anmelden', ['email' => 'neu@schule.ch', 'password' => 'ein-langes-Test-Passwort-1'])
            ->assertRedirect()->assertSessionHas('status', fn (string $m): bool => str_contains($m, 'Schule Muster'));
        $this->assertFalse(OrganizationMember::query()->where('user_id', $neu->id)->exists());

        // aktiv annehmen
        $invitation = OrganizationInvitation::query()->where('email', 'neu@schule.ch')->firstOrFail();
        $this->actingAs($neu)->get('/organisation')->assertInertia(fn (Assert $p) => $p->has('meine_einladungen', 1));
        $this->actingAs($neu)->post("/organisation/einladungen/{$invitation->id}/annehmen")->assertSessionHasNoErrors();
        $this->assertSame($organization->id, OrganizationMember::query()->where('user_id', $neu->id)->value('organization_id'));
        $this->assertFalse(OrganizationInvitation::query()->where('email', 'neu@schule.ch')->exists());

        // fremde Einladung nicht annehmbar
        $zwei = OrganizationInvitation::query()->where('email', 'zwei@schule.ch')->firstOrFail();
        $this->actingAs($neu)->post("/organisation/einladungen/{$zwei->id}/ablehnen")->assertSessionHasErrors('organisation');
    }

    public function test_einladung_per_mail_nur_mit_mailversand(): void
    {
        Mail::fake();
        [, $admin] = $this->organisation();
        config(['mail.default' => 'log']);
        $this->actingAs($admin)->post('/organisation/einladungen', ['adressen' => 'eins@schule.ch'])->assertRedirect();
        Mail::assertNothingQueued();

        config(['mail.default' => 'smtp', 'mail.from.address' => 'noreply@eduksx.ch']);
        $this->actingAs($admin)->post('/organisation/einladungen', ['adressen' => 'zwei@schule.ch'])->assertRedirect();
        Mail::assertQueued(OrganizationInvitationMail::class, fn (OrganizationInvitationMail $m): bool => $m->hasTo('zwei@schule.ch')
            && $m->organization === 'Schule Muster');
        $this->assertSame(2, OrganizationInvitation::query()->count());
    }

    public function test_bestehendes_mitglied_wird_nicht_automatisch_umgehaengt(): void
    {
        [$organization, $admin] = $this->organisation();
        $other = $this->userWithRoles([Role::USER], 'x@andere.ch');
        $otherOrg = $this->organizations->create($other, 'Andere');
        $this->organizations->invite($admin, $organization, 'x@andere.ch');

        $this->assertSame([], $this->organizations->pendingInvitationNames($other));
        $this->assertSame($otherOrg->id, OrganizationMember::query()->where('user_id', $other->id)->value('organization_id'));
        $this->actingAs($other)->get('/organisation')->assertInertia(fn (Assert $p) => $p->has('meine_einladungen', 1));
    }

    public function test_mitglieder_sehen_die_darstellung_der_organisation(): void
    {
        $term = $this->term();
        [$organization, $admin] = $this->organisation();
        $member = $this->member($organization, 'm@schule.ch');
        $outsider = $this->userWithRoles([Role::USER], 'o@andere.ch');

        $this->actingAs($admin)->put("/begriffe/{$term->slug}/organisation-darstellung", ['name_override' => 'Schulleitung Muster'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, OrganizationTermOverride::query()->count());

        $this->actingAs($member)->get("/begriffe/{$term->slug}")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('begriff.bezeichnung', 'Schulleitung Muster')
                ->where('begriff.organisation.gesetzt', true)->where('begriff.organisation.bearbeiten', false)
                ->where('begriff.organisation.werte', null));
        $this->actingAs($member)->get('/')->assertInertia(fn (Assert $p) => $p->where('begriffe.0.bezeichnung', 'Schulleitung Muster'));

        // Mitglieder ändern nicht
        $this->actingAs($member)->put("/begriffe/{$term->slug}/organisation-darstellung", ['name_override' => 'X'])->assertSessionHasErrors('organisation');

        // persönliche Darstellung geht vor
        $this->actingAs($member)->put("/begriffe/{$term->slug}/meine-darstellung", ['name_override' => 'Meine SL'])->assertRedirect();
        $this->actingAs($member)->get("/begriffe/{$term->slug}")->assertInertia(fn (Assert $p) => $p->where('begriff.bezeichnung', 'Meine SL'));

        // Aussenstehende, Gäste ohne Organisation und öffentlich: gemeinsame Form
        $this->actingAs($outsider)->get("/begriffe/{$term->slug}")
            ->assertInertia(fn (Assert $p) => $p->where('begriff.bezeichnung', 'Schulleitung')->missing('begriff.organisation'));
        $this->app['auth']->forgetGuards();
        $this->get("/begriffe/{$term->slug}")->assertInertia(fn (Assert $p) => $p->where('begriff.bezeichnung', 'Schulleitung'));
        $this->assertStringNotContainsString('Muster', (string) $this->getJson("/api/v1/terms/{$term->id}")->getContent());
        $this->assertStringNotContainsString('Muster', (string) $this->getJson('/api/v1/exports?format=json')->getContent());

        // entfernen
        $this->actingAs($admin)->delete("/begriffe/{$term->slug}/organisation-darstellung")->assertRedirect();
        $this->assertSame(0, OrganizationTermOverride::query()->count());
    }

    public function test_verwaltung_nur_mit_zweitem_faktor(): void
    {
        $term = $this->term();
        $user = $this->userWithRoles([Role::USER], 'ohne@schule.ch', false);
        $this->organizations->create($user, 'Ohne 2FA');
        $this->actingAs($user)->post('/organisation/einladungen', ['adressen' => 'a@schule.ch'])->assertSessionHasErrors('adressen');
        $this->actingAs($user)->put("/begriffe/{$term->slug}/organisation-darstellung", ['name_override' => 'X'])->assertSessionHasErrors('organisation');
        $this->assertSame(0, OrganizationInvitation::query()->count());
    }

    public function test_sperren_entfernen_und_letzter_admin(): void
    {
        $term = $this->term();
        [$organization, $admin] = $this->organisation();
        $member = $this->member($organization, 'm@schule.ch', true);
        $this->organizations->setDisplay($admin, $organization, $term, ['name_override' => 'SL Muster']);
        $membership = OrganizationMember::query()->where('user_id', $member->id)->firstOrFail();
        $own = OrganizationMember::query()->where('user_id', $admin->id)->firstOrFail();

        // gesperrt: gemeinsame Darstellung
        $this->actingAs($admin)->post("/organisation/mitglieder/{$membership->id}/sperren")->assertSessionHasNoErrors();
        $this->actingAs($member)->get("/begriffe/{$term->slug}")->assertInertia(fn (Assert $p) => $p->where('begriff.bezeichnung', 'Schulleitung'));
        $this->actingAs($admin)->post("/organisation/mitglieder/{$membership->id}/entsperren")->assertSessionHasNoErrors();

        // letzter Admin: nicht entziehen, nicht austreten
        $this->actingAs($admin)->put("/organisation/mitglieder/{$own->id}/rolle", ['admin' => false])->assertSessionHasErrors('organisation');
        $this->actingAs($admin)->post('/organisation/austreten')->assertSessionHasErrors('organisation');

        // anderes Mitglied zum Admin → globale Rolle; dann sich selbst entziehen und austreten
        $this->actingAs($admin)->put("/organisation/mitglieder/{$membership->id}/rolle", ['admin' => true])->assertSessionHasNoErrors();
        $this->assertContains(Role::ORG_ADMIN, $member->roleKeys());
        $this->actingAs($admin)->put("/organisation/mitglieder/{$own->id}/rolle", ['admin' => false])->assertSessionHasNoErrors();
        $this->assertNotContains(Role::ORG_ADMIN, $admin->roleKeys());
        $this->actingAs($admin)->post('/organisation/austreten')->assertSessionHasNoErrors();
        $this->assertFalse(OrganizationMember::query()->where('user_id', $admin->id)->exists());

        // Mitglied entfernen: Konto bleibt, neue Einladung möglich
        $third = $this->member($organization, 'd@schule.ch');
        $thirdMembership = OrganizationMember::query()->where('user_id', $third->id)->firstOrFail();
        $this->actingAs($member)->delete("/organisation/mitglieder/{$thirdMembership->id}")->assertSessionHasNoErrors();
        $this->assertNotNull(User::query()->find($third->id));
        $result = $this->organizations->invite($member->fresh() ?? $member, $organization, 'd@schule.ch');
        $this->assertSame(['d@schule.ch'], $result['eingeladen']);
    }

    public function test_fremde_organisation_nicht_verwaltbar(): void
    {
        [$organization] = $this->organisation();
        $member = $this->member($organization, 'm@schule.ch');
        $membership = OrganizationMember::query()->where('user_id', $member->id)->firstOrFail();
        $other = $this->userWithRoles([Role::USER], 'x@andere.ch');
        $this->organizations->create($other, 'Andere');

        $this->actingAs($other)->post("/organisation/mitglieder/{$membership->id}/sperren")->assertSessionHasErrors('organisation');
        $this->assertTrue($membership->fresh()?->isActive());
    }

    public function test_api_mit_token_der_organisation(): void
    {
        $term = $this->term();
        [$organization, $admin] = $this->organisation();
        $this->organizations->setDisplay($admin, $organization, $term, ['abbreviation_override' => 'SLM', 'name_override' => 'Schulleitung Muster']);
        $manager = app(ServiceAccountManager::class);
        $account = $manager->create($admin, 'Intranet', 'Begriffe der Schule', ['read-org']);
        $this->assertSame($organization->id, $account->organization_id);
        ['plain' => $plain] = $manager->createToken($admin, $account, 'Produktion', 30);
        $header = ['Authorization' => "Bearer {$plain}"];

        $this->getJson('/api/v1/org/terms', $header)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('organization.name', 'Schule Muster')
            ->assertJsonPath('data.0.term.name', 'Schulleitung')
            ->assertJsonPath('data.0.organization_display.abbreviation', 'SLM')
            ->assertJsonPath('meta.customized', 1);
        $this->getJson("/api/v1/org/terms/{$term->slug}", $header)->assertOk()->assertJsonPath('data.organization_display.name', 'Schulleitung Muster');
        $this->getJson('/api/v1/org/terms')->assertUnauthorized();

        // Token ohne Scope «read-org» und Konten ohne Organisation
        $propose = $manager->create($admin, 'Vorschläge', 'Vorschläge', ['propose']);
        ['plain' => $other] = $manager->createToken($admin, $propose, 'P', 30);
        $this->getJson('/api/v1/org/terms', ['Authorization' => "Bearer {$other}"])->assertUnauthorized();

        $platform = $this->userWithRoles([Role::ADMIN], 'plattform@example.org');
        $this->expectException(AccessDenied::class);
        $manager->create($platform, 'Ohne Organisation', 'X', ['read-org']);
    }

    public function test_andere_organisations_admins_verwalten_das_servicekonto(): void
    {
        [$organization, $admin] = $this->organisation();
        $manager = app(ServiceAccountManager::class);
        $account = $manager->create($admin, 'Intranet', 'Begriffe', ['read-org']);
        $second = $this->member($organization, 'zwei@schule.ch', true);
        $this->organizations->setAdmin($admin, OrganizationMember::query()->where('user_id', $second->id)->firstOrFail(), true);

        $second = $second->fresh() ?? $second;
        $this->assertTrue($manager->canManage($second, $account));
        $this->assertSame(1, $manager->visibleFor($second)->count());
    }

    public function test_vorschlaege_der_mitglieder_bestaetigt_ein_organisations_admin(): void
    {
        [$organization, $admin] = $this->organisation();
        $member = $this->member($organization, 'm@schule.ch');
        $proposals = app(ProposalService::class);

        $proposal = $proposals->submit(null, ['official_abbreviation' => 'LP', 'official_name' => 'Lehrperson'], 'fehlt', $member);
        $this->assertSame(Proposal::ORG_REVIEW, $proposal->status);
        $this->assertSame($organization->id, $proposal->organization_id);

        // Redaktion sieht ihn noch nicht und kann nicht entscheiden
        $moderator = $this->userWithRoles([Role::MODERATOR], 'mod@example.org');
        $this->actingAs($moderator)->get('/admin/vorschlaege?status=alle')->assertInertia(fn (Assert $p) => $p->has('vorschlaege', 0));
        $this->actingAs($moderator)->get("/admin/vorschlaege/{$proposal->id}")->assertNotFound();

        // Mitglieder entscheiden nicht; Organisations-Admin sieht und bestätigt
        $this->actingAs($member)->post("/organisation/vorschlaege/{$proposal->id}/bestaetigen")->assertSessionHasErrors('organisation');
        $this->actingAs($admin)->get('/organisation')->assertInertia(fn (Assert $p) => $p->has('vorschlaege', 1));
        $this->actingAs($admin)->post("/organisation/vorschlaege/{$proposal->id}/bestaetigen")->assertSessionHasNoErrors();
        $this->assertSame('eingereicht', $proposal->fresh()?->status);
        $this->actingAs($moderator)->get('/admin/vorschlaege')->assertInertia(fn (Assert $p) => $p->has('vorschlaege', 1));

        // ablehnen mit Begründung
        $second = $proposals->submit(null, ['official_name' => 'Hauswart'], null, $member);
        $this->actingAs($admin)->post("/organisation/vorschlaege/{$second->id}/ablehnen", ['grund' => ''])->assertSessionHasErrors('grund');
        $this->actingAs($admin)->post("/organisation/vorschlaege/{$second->id}/ablehnen", ['grund' => 'gibt es schon'])->assertSessionHasNoErrors();
        $this->assertSame('abgelehnt', $second->fresh()?->status);

        // eigene Vorschläge des Organisations-Admins direkt in die Moderation
        $this->assertSame('eingereicht', $proposals->submit(null, ['official_name' => 'Schulsozialarbeit'], null, $admin)->status);

        // beim Austritt weitergeleitet
        $third = $proposals->submit(null, ['official_name' => 'Tagesstruktur'], null, $member);
        $this->actingAs($member)->post('/organisation/austreten')->assertSessionHasNoErrors();
        $this->assertSame('eingereicht', $third->fresh()?->status);
    }

    public function test_anderer_organisations_admin_widerruft_token(): void
    {
        [$organization, $admin] = $this->organisation();
        $manager = app(ServiceAccountManager::class);
        $account = $manager->create($admin, 'Intranet', 'Begriffe', ['read-org']);
        ['plain' => $plain, 'token' => $token] = $manager->createToken($admin, $account, 'Produktion', 30);
        $second = $this->member($organization, 'zwei@schule.ch', true);
        $this->organizations->setAdmin($admin, OrganizationMember::query()->where('user_id', $second->id)->firstOrFail(), true);

        $this->actingAs($second->fresh() ?? $second)->delete("/admin/servicekonten/token/{$token->id}")->assertRedirect();
        $this->getJson('/api/v1/org/terms', ['Authorization' => "Bearer {$plain}"])->assertUnauthorized();
    }

    public function test_kontoloeschung_mit_nachfolge_und_aufloesung(): void
    {
        [$organization, $admin] = $this->organisation();
        $member = $this->member($organization, 'm@schule.ch');
        $admin->delete();
        $this->assertTrue(OrganizationMember::query()->where('user_id', $member->id)->firstOrFail()->isAdmin());
        $this->assertContains(Role::ORG_ADMIN, $member->roleKeys());

        $member->delete();
        $this->assertSame(0, Organization::query()->count());
    }

    public function test_organisation_loeschen_entfernt_die_rolle(): void
    {
        [$organization, $admin] = $this->organisation();
        $this->actingAs($admin)->post('/organisation/loeschen')->assertSessionHasNoErrors();
        $this->assertSame(0, Organization::query()->count());
        $this->assertNotContains(Role::ORG_ADMIN, $admin->roleKeys());
        $this->assertNotNull($organization->id);
    }
}
