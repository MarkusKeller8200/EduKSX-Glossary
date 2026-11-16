<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\UserAudit;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Ausgeblendete Hinweise wieder anzeigen (#469). */
final class HinweiseTest extends TestCase
{
    public function test_moderation_und_news_wieder_anzeigen(): void
    {
        $this->actingAs($u = $this->userWithRoles([Role::USER], 'lea@schule.ch', true));
        $u->forceFill(['email_verified_at' => now(), 'news_answered_at' => now()])->save();
        $this->get('/konto');   // Hinweis Moderation einmal gezeigt
        $this->get('/konto')->assertInertia(fn (Assert $p) => $p
            ->where('moderatorHinweis', null)
            ->where('hinweise.moderation', true)->where('hinweise.news', true)
            ->where('hinweise.domain', null)->where('hinweise.datenschutz', null));

        $this->post('/konto/hinweise', ['hinweis' => 'moderation'])->assertSessionHasNoErrors();
        $this->get('/konto')->assertInertia(fn (Assert $p) => $p->where('moderatorHinweis.fehlt', []));

        $this->post('/konto/hinweise', ['hinweis' => 'alle'])->assertSessionHasNoErrors();
        $this->assertNull($u->refresh()->news_answered_at);
        $this->assertTrue(UserAudit::query()->where('action', 'account.notices_reset')->exists());
    }

    public function test_datenschutz_nur_mit_recht_system(): void
    {
        $this->actingAs($u = $this->userWithRoles([Role::USER], 'lea@schule.ch'));
        $u->forceFill(['privacy_notice_version' => '1.0'])->save();
        $this->post('/konto/hinweise', ['hinweis' => 'datenschutz']);
        $this->assertSame('1.0', $u->refresh()->privacy_notice_version);

        $this->actingAs($dev = $this->userWithRoles([Role::DEVELOPER], 'dev@example.org', true));
        $this->post('/konto/hinweise', ['hinweis' => 'datenschutz']);
        $this->assertNull($dev->refresh()->privacy_notice_version);
    }

    public function test_ungueltiger_hinweis(): void
    {
        $this->actingAs($this->userWithRoles([Role::USER], 'lea@schule.ch'));
        $this->post('/konto/hinweise', ['hinweis' => 'irgendwas'])->assertSessionHasErrors('hinweis');
    }
}
