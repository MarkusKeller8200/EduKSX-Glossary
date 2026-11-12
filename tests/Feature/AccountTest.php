<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserAudit;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Kontoangaben und «Mein Konto» (#39, docs/KONZEPT-BENUTZER.md 4 und 5.4). */
class AccountTest extends TestCase
{
    private function community(string $email = 'c@example.org'): User
    {
        return User::query()->create([
            'first_name' => 'Anna', 'last_name' => 'Muster', 'email' => $email, 'password' => 'ein-langes-Test-Passwort-1',
        ]);
    }

    public function test_anzeigename_aus_vor_und_nachname(): void
    {
        $user = $this->community();
        $this->assertSame('Anna Muster', $user->name);

        $legacy = User::query()->create(['name' => 'Hans Peter Meier', 'email' => 'h@example.org', 'password' => 'ein-langes-Test-Passwort-1']);
        $this->assertSame('Hans Peter', $legacy->first_name);
        $this->assertSame('Meier', $legacy->last_name);
    }

    public function test_angaben_aendern(): void
    {
        $this->actingAs($user = $this->community());
        $this->community('b@example.org')->forceFill(['community_name' => 'Besetzt'])->save();

        $this->get('/konto')->assertOk()->assertInertia(fn (Assert $p) => $p->component('account/show')
            ->where('konto.first_name', 'Anna')->has('einrichtungen', 5));

        $this->put('/konto', ['first_name' => 'Anna', 'last_name' => 'Muster', 'community_name' => 'Besetzt'])
            ->assertSessionHasErrors('community_name');
        $this->put('/konto', ['first_name' => 'Anna', 'last_name' => 'Muster', 'institution_type' => 'firma'])
            ->assertSessionHasErrors('institution_type');

        $this->put('/konto', [
            'first_name' => 'Anna', 'last_name' => 'Beispiel', 'community_name' => 'Anna B.', 'function' => 'Schulleitung',
            'institution_type' => 'school', 'institution_name' => 'Primarschule Musterdorf',
        ])->assertRedirect();
        $user->refresh();
        $this->assertSame('Anna Beispiel', $user->name);
        $this->assertSame('Anna B.', $user->community_name);
        $this->assertSame('school', $user->institution_type);
        $this->assertSame('c@example.org', $user->email);   // E-Mail nur mit Bestätigung (#41)
    }

    public function test_passwort_aendern(): void
    {
        $this->actingAs($user = $this->community());

        $this->put('/konto/passwort', ['current_password' => 'falsch', 'password' => 'neues-langes-Passwort-2', 'password_confirmation' => 'neues-langes-Passwort-2'])
            ->assertSessionHasErrors('current_password');
        $this->put('/konto/passwort', ['current_password' => 'ein-langes-Test-Passwort-1', 'password' => 'neues-langes-Passwort-2', 'password_confirmation' => 'neues-langes-Passwort-2'])
            ->assertRedirect();
        $this->assertTrue(Hash::check('neues-langes-Passwort-2', $user->refresh()->password));
    }

    public function test_konto_loeschen(): void
    {
        $this->actingAs($user = $this->community());

        $this->post('/konto/loeschen', ['password' => 'falsch'])->assertSessionHasErrors('password');
        $this->post('/konto/loeschen', ['password' => 'ein-langes-Test-Passwort-1'])->assertRedirect('/');
        $this->assertGuest();
        $this->assertNull(User::query()->find($user->id));
        $this->assertTrue(UserAudit::query()->where('target_user_id', $user->id)->where('action', 'account.deleted')->exists());
        $this->post('/anmelden', ['email' => 'c@example.org', 'password' => 'ein-langes-Test-Passwort-1'])->assertSessionHasErrors('email');
    }

    public function test_letzter_developer_kann_sich_nicht_loeschen(): void
    {
        $this->actingAs($dev = $this->userWithRoles([Role::DEVELOPER], 'dev@example.org'));

        $this->post('/konto/loeschen', ['password' => 'ein-langes-Test-Passwort-1'])->assertSessionHasErrors('password');
        $this->assertNotNull(User::query()->find($dev->id));
    }

    public function test_letzte_anmeldung_nur_als_datum(): void
    {
        $this->community();
        $this->post('/anmelden', ['email' => 'c@example.org', 'password' => 'ein-langes-Test-Passwort-1']);

        $this->assertSame(today()->toDateString(), User::query()->where('email', 'c@example.org')->firstOrFail()->last_login_on?->toDateString());
    }

    public function test_community_kann_zweiten_faktor_freiwillig_einrichten(): void
    {
        $this->actingAs($this->community());
        $this->get('/konto/zwei-faktor')->assertOk()->assertInertia(fn (Assert $p) => $p->component('account/two-factor')->where('pflicht', false));
    }
}
