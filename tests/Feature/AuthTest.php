<?php

namespace Tests\Feature;

use App\Models\User;
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

    public function test_anmelden_und_abmelden(): void
    {
        $this->admin();

        $this->post('/anmelden', ['email' => 'Admin@Example.org', 'password' => 'ein-langes-Test-Passwort-1'])
            ->assertRedirect('/admin');
        $this->assertAuthenticated();
        $this->get('/admin')->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/dashboard')
            ->where('stats.terms', 0)->where('stats.published', 0));

        $this->post('/abmelden')->assertRedirect('/');
        $this->assertGuest();
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
        $this->assertTrue($user->is_admin);
        $this->assertTrue(Hash::check('noch-ein-langes-Passwort-3', $user->password));
    }

    public function test_zu_kurzes_passwort_wird_abgelehnt(): void
    {
        $this->artisan('glossary:admin', ['email' => 'kurz@example.org', '--name' => 'Kurz', '--password' => 'kurz'])
            ->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'kurz@example.org']);
    }
}
