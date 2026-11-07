<?php

namespace Tests\Feature;

use App\Access\Sso\MicrosoftLogin;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAudit;
use App\Models\UserIdentity;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

/** Anmeldung mit Microsoft und sichere Kontoverknüpfung (#45, docs/KONZEPT-BENUTZER.md 6.1, 6.3; Abnahmetest 4). */
class SsoTest extends TestCase
{
    private const ISSUER = 'https://login.microsoftonline.com/11111111-2222-3333-4444-555555555555/v2.0';

    /** @return array<string, string|null> */
    private function claims(string $sub = 'sub-anna', string $email = 'anna@schule.example'): array
    {
        return [
            'issuer' => self::ISSUER, 'subject' => $sub, 'tenant_id' => '11111111-2222-3333-4444-555555555555',
            'object_id' => 'oid-1', 'email' => $email, 'given_name' => 'Anna', 'family_name' => 'Muster', 'name' => 'Anna Muster',
        ];
    }

    /** @param  array<string, string|null>|RuntimeException  $claims */
    private function microsoft(array|RuntimeException $claims): void
    {
        $this->mock(MicrosoftLogin::class, function (MockInterface $mock) use ($claims): void {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('redirect')->andReturn(redirect('https://login.microsoftonline.com/common/oauth2/v2.0/authorize'));
            $claims instanceof RuntimeException
                ? $mock->shouldReceive('claims')->andThrow($claims)
                : $mock->shouldReceive('claims')->andReturn($claims);
        });
    }

    public function test_ohne_app_registrierung_aus(): void
    {
        $this->get('/anmelden/microsoft')->assertNotFound();
        $this->get('/anmelden')->assertInertia(fn (Assert $p) => $p->where('microsoft', false));
    }

    public function test_neues_konto_nur_mit_community_rechten(): void
    {
        $this->microsoft($this->claims());
        $this->get('/anmelden')->assertInertia(fn (Assert $p) => $p->where('microsoft', true));
        $this->get('/anmelden/microsoft')->assertRedirect();

        $this->get('/anmelden/microsoft/rueckkehr')->assertRedirect('/konto');

        $user = User::query()->where('email', 'anna@schule.example')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Anna Muster', $user->name);
        $this->assertFalse($user->password_set);
        $this->assertSame(['propose'], $user->permissions());
        $this->assertSame(1, UserIdentity::query()->where('issuer', self::ISSUER)->where('subject', 'sub-anna')->count());
    }

    public function test_gleiche_email_wird_nie_automatisch_verknuepft(): void
    {
        $existing = User::query()->create(['first_name' => 'Anna', 'last_name' => 'Alt', 'email' => 'anna@schule.example', 'password' => 'ein-langes-Test-Passwort-1']);
        $this->microsoft($this->claims());

        $this->get('/anmelden/microsoft/rueckkehr')->assertRedirect('/anmelden')->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, UserIdentity::query()->count());
        $this->assertSame(1, User::query()->where('email', 'anna@schule.example')->count());
        $this->assertSame('Anna Alt', $existing->refresh()->name);
    }

    public function test_bekannte_identitaet_mit_zweitem_faktor(): void
    {
        $moderator = $this->userWithRoles([Role::MODERATOR], 'mod@example.org');
        $moderator->identities()->create(['provider' => 'microsoft', 'issuer' => self::ISSUER, 'subject' => 'sub-mod']);
        $this->microsoft($this->claims('sub-mod', 'irgendwas@example.org'));

        $this->get('/anmelden/microsoft/rueckkehr')->assertRedirect('/anmelden/code');
        $this->assertGuest();
        $this->assertSame($moderator->id, session('login.id'));
    }

    public function test_verknuepfen_nur_nach_passwortbestaetigung(): void
    {
        $this->actingAs($admin = $this->admin());
        $this->microsoft($this->claims('sub-admin'));

        // ohne Bestätigung: Rückkehr verknüpft nichts
        $this->get('/anmelden/microsoft/rueckkehr')->assertRedirect('/konto');
        $this->assertSame(0, UserIdentity::query()->count());

        $this->post('/konto/anmeldedienste/microsoft', ['password' => 'falsch'])->assertSessionHasErrors('identitaet');
        $this->post('/konto/anmeldedienste/microsoft', ['password' => 'ein-langes-Test-Passwort-1'])->assertRedirect(route('sso.microsoft'));
        $this->get('/anmelden/microsoft/rueckkehr')->assertRedirect('/konto')->assertSessionHas('status');

        $this->assertSame(1, $admin->identities()->count());
        $this->assertTrue(UserAudit::query()->where('action', 'identity.linked')->where('result', 'ok')->exists());

        // lösen mit Passwort
        $identity = $admin->identities()->firstOrFail();
        $this->delete("/konto/anmeldedienste/{$identity->id}", ['password' => 'ein-langes-Test-Passwort-1'])->assertRedirect();
        $this->assertSame(0, $admin->identities()->count());
    }

    public function test_identitaet_eines_anderen_kontos_wird_abgewiesen(): void
    {
        $other = $this->userWithRoles([], 'other@example.org');
        $other->identities()->create(['provider' => 'microsoft', 'issuer' => self::ISSUER, 'subject' => 'sub-other']);
        $this->actingAs($this->admin());
        $this->microsoft($this->claims('sub-other'));

        $this->post('/konto/anmeldedienste/microsoft', ['password' => 'ein-langes-Test-Passwort-1']);
        $this->get('/anmelden/microsoft/rueckkehr')->assertRedirect('/konto')->assertSessionHasErrors('identitaet');
        $this->assertSame($other->id, UserIdentity::query()->firstOrFail()->user_id);
    }

    public function test_letzte_anmeldemethode_bleibt(): void
    {
        $this->microsoft($this->claims());
        $this->get('/anmelden/microsoft/rueckkehr');
        $identity = UserIdentity::query()->firstOrFail();

        $this->delete("/konto/anmeldedienste/{$identity->id}", ['password' => 'egal'])->assertSessionHasErrors('identitaet');
        $this->assertSame(1, UserIdentity::query()->count());
    }

    public function test_ungueltige_rueckkehr(): void
    {
        $this->microsoft(new RuntimeException('state stimmt nicht'));

        $this->get('/anmelden/microsoft/rueckkehr')->assertRedirect('/anmelden')->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
