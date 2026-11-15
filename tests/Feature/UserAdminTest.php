<?php

namespace Tests\Feature;

use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAudit;
use App\Organizations\OrganizationService;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Benutzer und Rollen in der Administration (#38). */
class UserAdminTest extends TestCase
{
    public function test_nur_mit_recht_benutzer_verwalten(): void
    {
        $this->actingAs($this->userWithRoles([Role::MODERATOR], 'mod@example.org'));
        $this->get('/admin/benutzer')->assertForbidden();
        $this->get('/admin/rollen')->assertForbidden();
    }

    public function test_liste_anlegen_und_rolle_vergeben(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin/benutzer')->assertOk()->assertInertia(fn (Assert $p) => $p->component('admin/users')->has('benutzer', 1));

        $this->post('/admin/benutzer', ['first_name' => 'Neu', 'last_name' => 'Konto', 'email' => 'Neu@Example.org', 'password' => 'ein-langes-Test-Passwort-9'])
            ->assertRedirect();
        $user = User::query()->where('email', 'neu@example.org')->firstOrFail();
        $this->assertSame([Role::USER], $user->roleKeys());   // Rolle neuer Konten (#110)

        $this->put("/admin/benutzer/{$user->id}/rollen", ['rollen' => [Role::USER, Role::MODERATOR]])->assertRedirect();
        $this->assertSame([Role::USER, Role::MODERATOR], $user->roleKeys());

        $this->get("/admin/benutzer/{$user->id}")->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('admin/user-show')
            ->where('benutzer.email', 'neu@example.org')
            ->where('protokoll.0.aktion', 'Rolle vergeben')
            ->where('rollen.5.key', Role::DEVELOPER)
            ->where('rollen.5.aenderbar', false));
    }

    /** Spalte Organisation (#412), Organisationen als Liste und Detail nur lesend (#413, #414). */
    public function test_organisationen_in_der_benutzerverwaltung(): void
    {
        $leitung = $this->userWithRoles([Role::USER], 'leitung@schule.example');
        $org = app(OrganizationService::class)->create($leitung, 'Schule Muster');
        $mitglied = $this->userWithRoles([Role::USER], 'lehrer@schule.example');
        OrganizationMember::query()->create(['organization_id' => $org->id, 'user_id' => $mitglied->id, 'role' => 'member', 'status' => 'active']);
        $org->invitations()->create(['email' => 'neu@schule.example', 'invited_by_id' => $leitung->id, 'expires_at' => now()->addDays(7)]);

        $this->actingAs($this->userWithRoles([Role::MODERATOR], 'mod@example.org'));
        $this->get('/admin/organisationen')->assertForbidden();
        $this->get("/admin/organisationen/{$org->id}")->assertForbidden();

        $this->actingAs($this->admin());
        $this->get('/admin/benutzer')->assertInertia(fn (Assert $p) => $p
            ->where('benutzer', fn ($rows): bool => collect($rows)->contains(fn ($r): bool => $r['email'] === 'leitung@schule.example'
                && $r['organisation']['name'] === 'Schule Muster' && $r['organisation']['rolle'] === 'Organisations-Admin')
                && collect($rows)->contains(fn ($r): bool => $r['email'] === 'lehrer@schule.example' && $r['organisation']['rolle'] === 'Mitglied')));

        $this->get('/admin/organisationen')->assertOk()->assertInertia(fn (Assert $p) => $p->component('admin/organizations')
            ->where('organisationen.0.name', 'Schule Muster')
            ->where('organisationen.0.mitglieder', 2)
            ->where('organisationen.0.einladungen', 1)
            ->where('organisationen.0.admins', [$leitung->name]));

        $this->get("/admin/organisationen/{$org->id}")->assertOk()->assertInertia(fn (Assert $p) => $p->component('admin/organization-show')
            ->where('organisation.name', 'Schule Muster')
            ->has('organisation.mitglieder', 2)
            ->where('organisation.einladungen.0.email', 'neu@schule.example'));

        // Detail des Kontos: alle Angaben lesend, keine Geheimnisse (#414)
        $mitglied->forceFill(['two_factor_secret' => 'GEHEIMNIS123', 'institution_kind' => 'primary_school', 'is_unified_municipality' => true])->save();
        $response = $this->get("/admin/benutzer/{$mitglied->id}")->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('benutzer.angaben.organisation.name', 'Schule Muster')
            ->where('benutzer.angaben.art_einrichtung', 'Primarschule')
            ->where('benutzer.angaben.einheitsgemeinde', true));
        $this->assertStringNotContainsString('GEHEIMNIS123', (string) $response->getContent());
    }

    public function test_developer_vergabe_ueber_oberflaeche_abgewiesen_und_protokolliert(): void
    {
        $this->actingAs($this->admin());
        $user = $this->userWithRoles([], 'x@example.org');

        $this->put("/admin/benutzer/{$user->id}/rollen", ['rollen' => [Role::DEVELOPER]])->assertSessionHasErrors('rollen');
        $this->assertSame([], $user->roleKeys());
        $this->assertTrue(UserAudit::query()->where('target_user_id', $user->id)->where('result', 'denied')->exists());
    }

    public function test_sperren_und_zweiten_faktor_zuruecksetzen(): void
    {
        $this->actingAs($this->admin());
        $user = $this->userWithRoles([Role::MODERATOR], 'mod@example.org');

        $this->post("/admin/benutzer/{$user->id}/zwei-faktor")->assertRedirect();
        $this->assertFalse($user->refresh()->hasTwoFactor());
        $this->post("/admin/benutzer/{$user->id}/sperren")->assertRedirect();
        $this->assertFalse($user->refresh()->isActive());
        $this->post("/admin/benutzer/{$user->id}/entsperren")->assertRedirect();
        $this->assertTrue($user->refresh()->isActive());
    }

    public function test_rollen_matrix(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin/rollen')->assertOk()->assertInertia(fn (Assert $p) => $p->component('admin/roles')->has('rollen', 6)->has('rechte', 14));

        $this->post('/admin/rollen', ['name' => 'Redaktion Schule'])->assertRedirect();
        $role = Role::byKey('redaktion-schule');
        $this->put("/admin/rollen/{$role->id}", ['rechte' => ['propose', 'edit-terms']])->assertRedirect();
        $this->assertEqualsCanonicalizing(['propose', 'edit-terms'], $role->permissionKeys());

        // systemkritisch nicht über die Matrix
        $this->put("/admin/rollen/{$role->id}", ['rechte' => ['system']])->assertSessionHasErrors('rechte');
        // Developer-Rolle nicht einstellbar
        $this->put('/admin/rollen/'.Role::byKey(Role::DEVELOPER)->id, ['rechte' => []])->assertSessionHasErrors('rechte');
    }
}
