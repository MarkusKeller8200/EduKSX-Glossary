<?php

namespace Tests\Feature;

use App\Mail\InactivityNotice;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Datenschutzseite, Kenntnisnahme je Version und Löschfristen (#42, DEC-019, docs/LOESCHKONZEPT.md). */
class PrivacyTest extends TestCase
{
    public function test_seite_mit_version_und_entwurf(): void
    {
        config(['glossary.privacy_version' => '1.0-test']);

        $this->get('/datenschutz')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('privacy')
            ->where('version', '1.0-test')
            ->where('entwurf', true)
            ->where('html', fn ($html) => str_contains((string) $html, '<h1>') && str_contains((string) $html, '<table>')));
    }

    public function test_kenntnisnahme_bei_neuer_version(): void
    {
        config(['glossary.privacy_version' => '1.0-test']);
        $this->actingAs($admin = $this->admin());

        $this->get('/admin/begriffe')->assertRedirect('/datenschutz/kenntnisnahme');
        $this->get('/datenschutz/kenntnisnahme')->assertOk()->assertInertia(fn (Assert $p) => $p->where('kenntnisnahme', true));
        $this->post('/datenschutz/kenntnisnahme')->assertRedirect('/admin/begriffe');
        $this->assertSame('1.0-test', $admin->refresh()->privacy_notice_version);
        $this->get('/admin/begriffe')->assertOk();
        $this->assertTrue(UserAudit::query()->where('action', 'privacy.acknowledged')->exists());

        // neue Version → erneut
        config(['glossary.privacy_version' => '1.1-test']);
        $this->get('/admin/begriffe')->assertRedirect('/datenschutz/kenntnisnahme');
    }

    public function test_inaktive_community_konten_mit_hinweis_loeschen(): void
    {
        config(['mail.default' => 'smtp', 'mail.from.address' => 'noreply@eduksx.ch']);
        Mail::fake();
        $inaktiv = User::query()->create(['first_name' => 'I', 'last_name' => 'Inaktiv', 'email' => 'i@example.org', 'password' => 'ein-langes-Test-Passwort-1']);
        $inaktiv->forceFill(['last_login_on' => now()->subMonths(24)->toDateString()])->save();
        $inaktiv->roles()->attach(Role::byKey(Role::USER)->id);   // Benutzer zählt als Community (#110)
        $aktiv = User::query()->create(['first_name' => 'A', 'last_name' => 'Aktiv', 'email' => 'a@example.org', 'password' => 'ein-langes-Test-Passwort-1']);
        $aktiv->forceFill(['last_login_on' => now()->subMonths(6)->toDateString()])->save();
        $moderator = $this->userWithRoles([Role::MODERATOR], 'mod@example.org');
        $moderator->forceFill(['last_login_on' => now()->subYears(3)->toDateString()])->save();

        // erster Lauf: nur Hinweis
        $this->artisan('glossary:loeschfristen')->assertSuccessful();
        Mail::assertQueued(InactivityNotice::class, 1);
        Mail::assertQueued(InactivityNotice::class, fn (InactivityNotice $m): bool => $m->hasTo('i@example.org'));
        $this->assertNotNull($inaktiv->refresh()->inactivity_notice_sent_at);

        // 31 Tage später: gelöscht; aktives Konto und Konto mit Rolle bleiben
        $this->travel(31)->days();
        $this->artisan('glossary:loeschfristen')->assertSuccessful();
        $this->assertNull(User::query()->find($inaktiv->id));
        $this->assertNotNull(User::query()->find($aktiv->id));
        $this->assertNotNull(User::query()->find($moderator->id));
        $this->assertTrue(UserAudit::query()->where('target_user_id', $inaktiv->id)->where('action', 'account.deleted')->exists());
    }

    public function test_anmelden_setzt_den_hinweis_zurueck(): void
    {
        $user = User::query()->create(['first_name' => 'I', 'last_name' => 'Inaktiv', 'email' => 'i@example.org', 'password' => 'ein-langes-Test-Passwort-1']);
        $user->forceFill(['last_login_on' => now()->subMonths(24)->toDateString(), 'inactivity_notice_sent_at' => now()])->save();

        $this->post('/anmelden', ['email' => 'i@example.org', 'password' => 'ein-langes-Test-Passwort-1']);

        $user->refresh();
        $this->assertNull($user->inactivity_notice_sent_at);
        $this->assertSame(today()->toDateString(), $user->last_login_on?->toDateString());
    }

    public function test_ohne_mailversand_keine_loeschung_inaktiver_konten(): void
    {
        config(['mail.default' => 'log']);
        $user = User::query()->create(['first_name' => 'I', 'last_name' => 'Inaktiv', 'email' => 'i@example.org', 'password' => 'ein-langes-Test-Passwort-1']);
        $user->forceFill(['last_login_on' => now()->subYears(3)->toDateString(), 'inactivity_notice_sent_at' => now()->subYear()])->save();

        $this->artisan('glossary:loeschfristen')->assertSuccessful();
        $this->assertNotNull(User::query()->find($user->id));
    }

    public function test_alte_protokolleintraege_werden_entfernt(): void
    {
        DB::table('user_audit')->insert([
            ['action' => 'alt', 'result' => 'ok', 'created_at' => now()->subYears(6)],
            ['action' => 'neu', 'result' => 'ok', 'created_at' => now()->subYears(1)],
        ]);

        $this->artisan('glossary:loeschfristen')->assertSuccessful();

        $this->assertSame(['neu'], UserAudit::query()->pluck('action')->all());
    }
}
