<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserAudit;
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
