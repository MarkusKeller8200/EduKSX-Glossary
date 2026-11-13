<?php

namespace Tests\Feature;

use App\Mail\ConfirmRegistration;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAudit;
use App\Notifications\ResetPasswordNotification;
use App\Support\Settings;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Selbstregistrierung mit Double-Opt-in und «Passwort vergessen» (#41, docs/KONZEPT-BENUTZER.md 5.1, 5.2). */
class RegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['glossary.registration_open' => true]);   // Registrierung offen (#302)
    }

    private const FORM = [
        'first_name' => 'Anna', 'last_name' => 'Muster', 'email' => 'Anna@Example.org',
        'password' => 'ein-langes-Test-Passwort-7', 'password_confirmation' => 'ein-langes-Test-Passwort-7',
        'datenschutz' => true,
    ];

    private function mailOn(): void
    {
        config(['mail.default' => 'smtp', 'mail.from.address' => 'noreply@eduksx.ch', 'glossary.privacy_version' => '1.0-test']);
        Mail::fake();
    }

    public function test_ohne_mailversand_keine_registrierung(): void
    {
        config(['mail.default' => 'log']);
        $this->get('/registrieren')->assertNotFound();
        $this->get('/passwort-vergessen')->assertNotFound();
        $this->get('/anmelden')->assertInertia(fn (Assert $p) => $p->where('registrierung', false));
    }

    public function test_double_opt_in(): void
    {
        $this->mailOn();
        $this->get('/anmelden')->assertInertia(fn (Assert $p) => $p->where('registrierung', true));
        $this->get('/registrieren')->assertOk()->assertInertia(fn (Assert $p) => $p->component('auth/register'));

        $this->post('/registrieren', self::FORM)->assertRedirect('/anmelden')->assertSessionHas('status');
        $user = User::query()->where('email', 'anna@example.org')->firstOrFail();
        $this->assertTrue($user->isPending());
        $this->assertSame([Role::USER], $user->roleKeys());   // Rolle neuer Registrierungen (#110)
        $this->assertSame(config('glossary.privacy_version'), $user->privacy_notice_version);
        $this->assertNotNull($user->privacy_notice_acknowledged_at);

        // vor der Bestätigung keine Anmeldung; Link wird erneut gesendet
        $this->post('/anmelden', ['email' => 'anna@example.org', 'password' => 'ein-langes-Test-Passwort-7'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $url = '';
        Mail::assertQueued(ConfirmRegistration::class, function (ConfirmRegistration $mail) use (&$url): bool {
            $url = $mail->url;

            return $mail->hasTo('anna@example.org');
        });
        Mail::assertQueued(ConfirmRegistration::class, 2);

        $this->get($url)->assertRedirect('/anmelden')->assertSessionHas('status');
        $user->refresh();
        $this->assertTrue($user->isActive());
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(UserAudit::query()->where('action', 'account.verified')->exists());

        $this->post('/anmelden', ['email' => 'anna@example.org', 'password' => 'ein-langes-Test-Passwort-7'])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_abgelaufener_oder_veraenderter_link(): void
    {
        $this->mailOn();
        $this->post('/registrieren', self::FORM);
        $url = '';
        Mail::assertQueued(ConfirmRegistration::class, function (ConfirmRegistration $mail) use (&$url): bool {
            $url = $mail->url;

            return true;
        });

        $this->get($url.'x')->assertRedirect('/anmelden')->assertSessionHasErrors('email');
        $this->travel(61)->minutes();
        $this->get($url)->assertRedirect('/anmelden')->assertSessionHasErrors('email');
        $this->assertTrue(User::query()->where('email', 'anna@example.org')->firstOrFail()->isPending());
    }

    public function test_bekannte_adresse_wird_nicht_verraten(): void
    {
        $this->mailOn();
        $existing = User::query()->create(['first_name' => 'Alt', 'last_name' => 'Konto', 'email' => 'anna@example.org', 'password' => 'ein-langes-Test-Passwort-1']);

        $this->post('/registrieren', self::FORM)->assertRedirect('/anmelden')->assertSessionHas('status');
        Mail::assertNothingQueued();
        $this->assertSame('Alt Konto', $existing->refresh()->name);
        $this->assertTrue(Hash::check('ein-langes-Test-Passwort-1', $existing->password));
    }

    public function test_pflichtangaben_und_spam_schutz(): void
    {
        $this->mailOn();
        $this->post('/registrieren', [...self::FORM, 'datenschutz' => false])->assertSessionHasErrors('datenschutz');

        config(['glossary.turnstile.site_key' => 'site', 'glossary.turnstile.secret' => 'secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::sequence()->push(['success' => false])->push(['success' => true])]);
        $this->post('/registrieren', [...self::FORM, 'cf-turnstile-response' => 'token'])->assertSessionHasErrors('form');
        $this->assertSame(0, User::query()->count());

        $this->post('/registrieren', [...self::FORM, 'cf-turnstile-response' => 'token'])->assertSessionHasNoErrors();
        $this->assertSame(1, User::query()->count());
    }

    public function test_passwort_vergessen(): void
    {
        config(['mail.default' => 'smtp', 'mail.from.address' => 'noreply@eduksx.ch']);
        Notification::fake();
        $user = User::query()->create(['first_name' => 'Anna', 'last_name' => 'Muster', 'email' => 'anna@example.org', 'password' => 'ein-langes-Test-Passwort-1']);

        $this->post('/passwort-vergessen', ['email' => 'niemand@example.org'])->assertSessionHas('status');
        Notification::assertNothingSent();

        $this->post('/passwort-vergessen', ['email' => 'anna@example.org'])->assertSessionHas('status');
        $token = '';
        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $n) use (&$token): bool {
            $token = $n->token;

            return true;
        });

        $this->get("/passwort-zuruecksetzen/{$token}?email=anna@example.org")->assertOk();
        $this->post('/passwort-zuruecksetzen', ['token' => 'falsch', 'email' => 'anna@example.org', 'password' => 'neues-langes-Passwort-3', 'password_confirmation' => 'neues-langes-Passwort-3'])
            ->assertSessionHasErrors('email');
        $this->post('/passwort-zuruecksetzen', ['token' => $token, 'email' => 'anna@example.org', 'password' => 'neues-langes-Passwort-3', 'password_confirmation' => 'neues-langes-Passwort-3'])
            ->assertRedirect('/anmelden');

        $this->assertTrue(Hash::check('neues-langes-Passwort-3', $user->refresh()->password));
        $this->assertTrue(UserAudit::query()->where('action', 'account.password_reset')->exists());
    }

    public function test_unbestaetigte_registrierungen_nach_sieben_tagen_geloescht(): void
    {
        $alt = User::query()->create(['first_name' => 'A', 'last_name' => 'Alt', 'email' => 'alt@example.org', 'password' => 'ein-langes-Test-Passwort-1']);
        $alt->forceFill(['status' => 'pending', 'created_at' => now()->subDays(8)])->save();
        $neu = User::query()->create(['first_name' => 'N', 'last_name' => 'Neu', 'email' => 'neu@example.org', 'password' => 'ein-langes-Test-Passwort-1']);
        $neu->forceFill(['status' => 'pending'])->save();

        $this->artisan('glossary:loeschfristen')->assertSuccessful();

        $this->assertNull(User::query()->find($alt->id));
        $this->assertNotNull(User::query()->find($neu->id));
    }

    /** News-Einwilligung (#169): freiwillig, Vorgabe nein, Antwort mit Zeitpunkt. */
    public function test_news_bei_registrierung_und_nach_der_anmeldung(): void
    {
        $this->mailOn();
        $this->post('/registrieren', self::FORM + ['news' => true])->assertRedirect('/anmelden');
        $anna = User::query()->where('email', 'anna@example.org')->firstOrFail();
        $this->assertTrue($anna->has_news_opt_in);
        $this->assertNotNull($anna->news_answered_at);

        // bestehendes Konto ohne Antwort: einmal fragen (ohne Pflicht zur Kenntnisnahme der Datenschutzerklärung)
        config(['glossary.privacy_version' => '']);
        $alt = $this->userWithRoles([Role::USER], 'alt@example.org', false);
        $this->actingAs($alt)->get('/konto')->assertInertia(fn (Assert $p) => $p->where('auth.newsFrage', true)->where('konto.news', false));
        $this->actingAs($alt)->post('/konto/news', ['news' => false])->assertRedirect()->assertSessionHas('status');
        $alt->refresh();
        $this->assertFalse($alt->has_news_opt_in);
        $this->assertNotNull($alt->news_answered_at);
        $this->actingAs($alt)->get('/konto')->assertInertia(fn (Assert $p) => $p->where('auth.newsFrage', false));

        $this->actingAs($alt)->post('/konto/news', ['news' => true]);
        $this->assertTrue($alt->fresh()?->has_news_opt_in);
    }

    /** #302: Registrierung nur mit Schalter; «Passwort vergessen» hängt nur am Versand. */
    public function test_schalter_registrierung_getrennt_vom_versand(): void
    {
        $this->mailOn();
        config(['glossary.registration_open' => false]);
        $this->get('/anmelden')->assertInertia(fn (Assert $p) => $p->where('registrierung', false));
        $this->get('/registrieren')->assertNotFound();
        $this->post('/registrieren', self::FORM)->assertNotFound();
        $this->get('/passwort-vergessen')->assertOk();

        Settings::set($this->admin(), ['registration.open' => true]);
        $this->get('/registrieren')->assertOk();
    }
}
