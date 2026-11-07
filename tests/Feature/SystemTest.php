<?php

namespace Tests\Feature;

use App\Mail\TestMail;
use App\Models\Role;
use App\Models\UserAudit;
use App\Support\MailSetup;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** E-Mail-Versand providerunabhängig, Systemseite mit Testmail (#40). */
class SystemTest extends TestCase
{
    public function test_systemseite_nur_mit_recht_system(): void
    {
        $this->actingAs($this->admin());
        $this->get('/admin/system')->assertForbidden();

        $this->actingAs($this->userWithRoles([Role::DEVELOPER], 'dev@example.org'));
        $this->get('/admin/system')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('admin/system')
            ->where('mail.configured', false)
            ->missing('mail.password'));
    }

    public function test_versand_gilt_nur_mit_echtem_mailer_und_absender_als_eingerichtet(): void
    {
        config(['mail.default' => 'log', 'mail.from.address' => 'noreply@eduksx.ch']);
        $this->assertFalse(MailSetup::configured());

        config(['mail.default' => 'smtp', 'mail.from.address' => 'hello@example.com']);
        $this->assertFalse(MailSetup::configured());

        config(['mail.default' => 'smtp', 'mail.from.address' => 'noreply@eduksx.ch']);
        $this->assertTrue(MailSetup::configured());
    }

    public function test_testmail_wird_versendet_und_protokolliert(): void
    {
        Mail::fake();
        $this->actingAs($this->userWithRoles([Role::DEVELOPER], 'dev@example.org'));

        $this->post('/admin/system/testmail', ['an' => 'empfang@example.org'])->assertRedirect()->assertSessionHas('status');

        Mail::assertQueued(TestMail::class, fn (TestMail $mail): bool => $mail->hasTo('empfang@example.org'));
        $this->assertTrue(UserAudit::query()->where('action', 'system.testmail')->where('result', 'ok')->exists());
    }

    public function test_testmail_im_log_ohne_versanddienst(): void
    {
        config(['mail.default' => 'log', 'queue.default' => 'sync']);
        $this->actingAs($this->userWithRoles([Role::DEVELOPER], 'dev@example.org'));

        $this->post('/admin/system/testmail', ['an' => 'empfang@example.org'])->assertRedirect()->assertSessionHasNoErrors();
    }
}
