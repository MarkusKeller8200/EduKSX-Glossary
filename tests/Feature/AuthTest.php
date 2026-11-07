<?php

namespace Tests\Feature;

use App\Glossary\TwoFactor;
use App\Models\Role;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Lokaler Admin von Beginn an (DEC-014): ohne Anmeldung keine Administration. */
class AuthTest extends TestCase
{
    public function test_administration_verlangt_anmeldung(): void
    {
        $this->get('/admin')->assertRedirect('/anmelden');
    }

    public function test_keine_registrierung(): void
    {
        $this->get('/registrieren')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }

    public function test_anmelden_mit_zweitem_faktor_und_abmelden(): void
    {
        $this->admin();

        $this->post('/anmelden', ['email' => 'Admin@Example.org', 'password' => 'ein-langes-Test-Passwort-1'])
            ->assertRedirect('/anmelden/code');
        $this->assertGuest();

        $this->post('/anmelden/code', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/anmelden/code', ['code' => Totp::code(self::TOTP_SECRET)])->assertRedirect('/admin');
        $this->assertAuthenticated();
        $this->get('/admin')->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/dashboard')
            ->where('stats.terms', 0)->where('stats.published', 0));

        $this->post('/abmelden')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_code_abfrage_nur_nach_passwort(): void
    {
        $this->get('/anmelden/code')->assertRedirect('/anmelden');
    }

    public function test_ohne_zweiten_faktor_zuerst_einrichten(): void
    {
        $user = User::query()->create(['name' => 'Neu', 'email' => 'neu@example.org', 'password' => 'ein-langes-Test-Passwort-4']);
        $user->roles()->attach(Role::byKey(Role::ADMIN)->id);

        $this->post('/anmelden', ['email' => 'neu@example.org', 'password' => 'ein-langes-Test-Passwort-4'])->assertRedirect('/admin');
        $this->get('/admin')->assertRedirect('/admin/zwei-faktor');
        $this->get('/admin/begriffe')->assertRedirect('/admin/zwei-faktor');

        $this->get('/admin/zwei-faktor')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/two-factor')->where('eingerichtet', false)->has('schluessel'));
        $secret = (string) $user->refresh()->two_factor_secret;

        $this->post('/admin/zwei-faktor', ['code' => '123'])->assertSessionHasErrors('code');
        $this->post('/admin/zwei-faktor', ['code' => Totp::code($secret)])
            ->assertRedirect('/admin/zwei-faktor')
            ->assertSessionHas('recovery_codes');
        $this->assertTrue($user->refresh()->hasTwoFactor());
        $this->get('/admin')->assertOk();
    }

    public function test_wiederherstellungscode_gilt_einmal(): void
    {
        $user = $this->admin();
        $codes = app(TwoFactor::class)->newRecoveryCodes($user);

        $this->post('/anmelden', ['email' => 'admin@example.org', 'password' => 'ein-langes-Test-Passwort-1']);
        $this->post('/anmelden/code', ['code' => strtolower($codes[0])])->assertRedirect('/admin');
        $this->post('/abmelden');

        $this->post('/anmelden', ['email' => 'admin@example.org', 'password' => 'ein-langes-Test-Passwort-1']);
        $this->post('/anmelden/code', ['code' => $codes[0]])->assertSessionHasErrors('code');
        $this->assertCount(7, $user->refresh()->two_factor_recovery_codes ?? []);
    }

    public function test_zweiten_faktor_per_befehl_zuruecksetzen(): void
    {
        $user = $this->admin();
        $this->artisan('glossary:admin', ['email' => 'admin@example.org', '--reset-2fa' => true])->assertSuccessful();

        $this->assertFalse($user->refresh()->hasTwoFactor());
    }

    public function test_falsches_passwort(): void
    {
        $this->admin();

        $this->post('/anmelden', ['email' => 'admin@example.org', 'password' => 'falsch'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_konto_ohne_adminrecht_sieht_die_administration_nicht(): void
    {
        $user = User::query()->create([
            'name' => 'Ohne Recht', 'email' => 'user@example.org', 'password' => 'ein-langes-Test-Passwort-2',
        ]);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_admin_per_befehl_anlegen(): void
    {
        $this->artisan('glossary:admin', [
            'email' => 'Neu@Example.org', '--name' => 'Neu', '--password' => 'noch-ein-langes-Passwort-3',
        ])->assertSuccessful();

        $user = User::query()->where('email', 'neu@example.org')->firstOrFail();
        $this->assertSame([Role::ADMIN], $user->roleKeys());   // nie automatisch Developer (#36)
        $this->assertTrue(Hash::check('noch-ein-langes-Passwort-3', $user->password));
    }

    public function test_zu_kurzes_passwort_wird_abgelehnt(): void
    {
        $this->artisan('glossary:admin', ['email' => 'kurz@example.org', '--name' => 'Kurz', '--password' => 'kurz'])
            ->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'kurz@example.org']);
    }
}
