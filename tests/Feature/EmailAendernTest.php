<?php

namespace Tests\Feature;

use App\Mail\ConfirmEmailChange;
use App\Mail\EmailChangeNotice;
use App\Models\Role;
use App\Models\UserAudit;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Eigene E-Mail-Adresse ändern mit Bestätigung (#416). */
final class EmailAendernTest extends TestCase
{
    private function mailOn(): void
    {
        config(['mail.default' => 'smtp', 'mail.from.address' => 'noreply@eduksx.ch']);
        Mail::fake();
    }

    private function pfad(string $url): string
    {
        return (string) parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
    }

    public function test_neue_adresse_gilt_erst_nach_bestaetigung(): void
    {
        $this->mailOn();
        $this->actingAs($user = $this->userWithRoles([Role::USER], 'alt@schule.example'));

        $this->post('/konto/email', ['email' => 'neu@schule.example', 'current_password' => 'falsch'])->assertSessionHasErrors('current_password');
        $this->post('/konto/email', ['email' => 'Neu@Schule.example', 'current_password' => 'ein-langes-Test-Passwort-1'])
            ->assertRedirect()->assertSessionHas('status');
        $this->assertSame('alt@schule.example', $user->refresh()->email);

        $url = '';
        Mail::assertSent(ConfirmEmailChange::class, function (ConfirmEmailChange $m) use (&$url): bool {
            $url = $m->url;

            return $m->hasTo('neu@schule.example');
        });
        Mail::assertSent(EmailChangeNotice::class, fn (EmailChangeNotice $m): bool => $m->hasTo('alt@schule.example') && $m->neu === 'n***@schule.example');
        $this->assertStringNotContainsString('neu@schule.example', $url);   // Adresse nur verschlüsselt im Link

        $this->get((string) parse_url($url, PHP_URL_PATH))->assertForbidden();   // ohne Signatur
        $this->get($this->pfad($url))->assertRedirect('/konto')->assertSessionHas('status');
        $this->assertSame('neu@schule.example', $user->refresh()->email);
        $this->assertTrue(UserAudit::query()->where('target_user_id', $user->id)->where('action', 'account.email_changed')->exists());

        // nur einmal gültig
        $this->get($this->pfad($url))->assertSessionHas('status', 'Der Link ist abgelaufen oder wurde schon verwendet.');
    }

    public function test_vergebene_adresse_verraet_nichts_und_sendet_keinen_link(): void
    {
        $this->mailOn();
        $this->userWithRoles([Role::USER], 'besetzt@schule.example');
        $this->actingAs($this->userWithRoles([Role::USER], 'ich@schule.example'));

        $this->post('/konto/email', ['email' => 'besetzt@schule.example', 'current_password' => 'ein-langes-Test-Passwort-1'])
            ->assertSessionHas('status');
        Mail::assertNotSent(ConfirmEmailChange::class);
    }

    public function test_ohne_mailversand_nicht_moeglich(): void
    {
        config(['mail.default' => 'log']);
        $this->actingAs($this->userWithRoles([Role::USER], 'ich@schule.example'));
        $this->post('/konto/email', ['email' => 'neu@schule.example', 'current_password' => 'ein-langes-Test-Passwort-1'])
            ->assertSessionHasErrors('email');
    }
}
