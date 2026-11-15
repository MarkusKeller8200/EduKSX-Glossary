<?php

namespace Tests\Feature;

use App\Mail\TwoFactorRemoved;
use App\Mail\TwoFactorResetLink;
use App\Models\Role;
use App\Models\UserAudit;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Zweiten Faktor selbst zurücksetzen (#403): Link per Mail, dann E-Mail und Passwort; nur ohne erhöhte Rechte. */
final class ZweiterFaktorZuruecksetzenTest extends TestCase
{
    private function mailOn(): void
    {
        config(['mail.default' => 'smtp', 'mail.from.address' => 'noreply@eduksx.ch']);
        Mail::fake();
    }

    private function bisZumCode(string $email): void
    {
        $this->post('/anmelden', ['email' => $email, 'password' => 'ein-langes-Test-Passwort-1'])->assertRedirect('/anmelden/code');
    }

    public function test_benutzer_setzt_zweiten_faktor_ueber_link_zurueck(): void
    {
        $this->mailOn();
        $user = $this->userWithRoles([Role::USER], 'lea@schule.example');
        $this->assertTrue($user->hasTwoFactor());

        // ohne Anmeldeschritt «Code» kein Link
        $this->post('/anmelden/code/zuruecksetzen')->assertRedirect('/anmelden');

        $this->bisZumCode('lea@schule.example');
        $this->post('/anmelden/code/zuruecksetzen')->assertRedirect()->assertSessionHas('status');
        $url = '';
        Mail::assertSent(TwoFactorResetLink::class, function (TwoFactorResetLink $m) use (&$url): bool {
            $url = $m->url;

            return $m->hasTo('lea@schule.example');
        });
        $path = (string) parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);

        // ohne Signatur wertlos
        $this->get((string) parse_url($url, PHP_URL_PATH))->assertForbidden();

        $this->get($path)->assertOk()->assertInertia(fn (Assert $p) => $p->component('auth/two-factor-reset'));
        $this->post($path, ['email' => 'lea@schule.example', 'password' => 'falsch'])->assertSessionHasErrors('email');
        $this->assertTrue($user->refresh()->hasTwoFactor());

        $this->post($path, ['email' => 'LEA@schule.example', 'password' => 'ein-langes-Test-Passwort-1'])
            ->assertRedirect('/anmelden')->assertSessionHas('status');
        $this->assertFalse($user->refresh()->hasTwoFactor());
        Mail::assertQueued(TwoFactorRemoved::class, fn (TwoFactorRemoved $m): bool => $m->hasTo('lea@schule.example'));
        $this->assertTrue(UserAudit::query()->where('target_user_id', $user->id)->where('action', 'mfa.reset')->where('result', 'ok')->exists());

        // Link nur einmal gültig
        $this->get($path)->assertRedirect('/anmelden');

        // nächste Anmeldung ohne Code, danach das Einrichten anbieten
        $this->post('/anmelden', ['email' => 'lea@schule.example', 'password' => 'ein-langes-Test-Passwort-1'])
            ->assertRedirect('/konto/zwei-faktor');
        $this->assertAuthenticatedAs($user);
    }

    public function test_nicht_fuer_konten_mit_erhoehten_rechten(): void
    {
        $this->mailOn();
        $this->userWithRoles([Role::MODERATOR], 'mod@schule.example');

        $this->bisZumCode('mod@schule.example');
        $this->post('/anmelden/code/zuruecksetzen')->assertSessionHasErrors('code');
        Mail::assertNothingSent();
    }
}
