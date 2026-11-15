<?php

namespace Tests\Feature;

use App\Mail\ConfirmAccountDeletion;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Konto löschen ohne eigenes Passwort (#445, Security-Review SEC-01). */
final class KontoLoeschenOhnePasswortTest extends TestCase
{
    private function ssoKonto(): User
    {
        $user = User::query()->create([
            'first_name' => 'Sara', 'last_name' => 'Sso', 'email' => 'sso@schule.example', 'password' => Str::random(40),
        ]);
        $user->forceFill(['has_password' => false])->save();

        return $user->refresh();
    }

    private function pfad(string $url): string
    {
        return (string) parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
    }

    public function test_loeschen_nur_ueber_link_an_die_eigene_adresse(): void
    {
        config(['mail.default' => 'smtp', 'mail.from.address' => 'noreply@eduksx.ch']);
        Mail::fake();
        $this->actingAs($user = $this->ssoKonto());

        // «LÖSCHEN» allein löscht nicht mehr – es geht ein Link hinaus
        $this->post('/konto/loeschen', ['password' => 'LÖSCHEN'])->assertRedirect()->assertSessionHas('status');
        $this->assertNotNull(User::query()->find($user->id));
        $url = '';
        Mail::assertSent(ConfirmAccountDeletion::class, function (ConfirmAccountDeletion $m) use (&$url): bool {
            $url = $m->url;

            return $m->hasTo('sso@schule.example');
        });

        $this->get((string) parse_url($url, PHP_URL_PATH))->assertForbidden();   // ohne Signatur
        $this->get($this->pfad($url))->assertOk();                                 // Seite, noch nicht gelöscht
        $this->assertNotNull(User::query()->find($user->id));

        $this->post($this->pfad($url))->assertRedirect('/');
        $this->assertNull(User::query()->find($user->id));
        $this->assertGuest();

        // ein zweites Mal: nichts mehr zu löschen
        $this->get($this->pfad($url))->assertNotFound();
    }

    public function test_ohne_mailversand_kein_loeschen(): void
    {
        config(['mail.default' => 'log']);
        $this->actingAs($user = $this->ssoKonto());

        $this->post('/konto/loeschen', ['password' => 'LÖSCHEN'])->assertSessionHasErrors('password');
        $this->assertNotNull(User::query()->find($user->id));
    }

    public function test_mit_zweitem_faktor_direkt_mit_code(): void
    {
        $user = $this->ssoKonto();
        $user->forceFill(['two_factor_secret' => self::TOTP_SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user);

        $this->post('/konto/loeschen', ['password' => 'LÖSCHEN'])->assertSessionHasErrors('password');
        $this->post('/konto/loeschen', ['password' => Totp::code(self::TOTP_SECRET)])->assertRedirect('/');
        $this->assertNull(User::query()->find($user->id));
    }
}
