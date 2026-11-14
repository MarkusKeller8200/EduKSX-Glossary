<?php

namespace Tests\Feature;

use App\Mail\TestMail;
use App\Models\Role;
use App\Models\UserAudit;
use App\Support\MailSettings;
use App\Support\MailSetup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Mail-Versand in der Administration einrichten (#219). */
class MailSettingsTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function data(array $override = []): array
    {
        return $override + [
            'mailer' => 'smtp', 'host' => 'mail.infomaniak.com', 'port' => 465, 'scheme' => 'smtps',
            'username' => 'noreply@eduksx.ch', 'password' => 'Sehr-Geheim-123', 'passwort_loeschen' => false,
            'from_address' => 'noreply@eduksx.ch', 'from_name' => 'EduKSX-Glossary', 'an' => 'dev@example.org',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['glossary.mail_env' => false]);
        $this->actingAs($this->userWithRoles([Role::DEVELOPER], 'dev@example.org'));
    }

    public function test_speichern_sendet_testmail_und_gilt_danach_als_eingerichtet(): void
    {
        Mail::fake();
        $this->post('/admin/system/mail', $this->data())->assertSessionHasNoErrors()->assertSessionHas('status');

        Mail::assertSent(TestMail::class);
        $raw = (string) DB::table('settings')->where('key', MailSettings::KEY)->value('value');
        $this->assertStringNotContainsString('Sehr-Geheim-123', $raw);
        $this->assertSame('Sehr-Geheim-123', MailSettings::stored()['password'] ?? null);
        $this->assertNotNull(MailSettings::stored()['verified_at'] ?? null);
        $this->assertTrue(MailSetup::configured());
        $this->assertSame('mail.infomaniak.com', config('mail.mailers.smtp.host'));
    }

    public function test_passwort_erscheint_weder_auf_der_seite_noch_im_protokoll(): void
    {
        Mail::fake();
        $this->post('/admin/system/mail', $this->data());

        $this->get('/admin/system')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('mailEinstellung.quelle', 'administration')
            ->where('mailEinstellung.passwort_gesetzt', true)
            ->missing('mailEinstellung.password'));
        $audit = UserAudit::query()->where('action', 'system.mail.changed')->firstOrFail();
        $this->assertStringNotContainsString('Sehr-Geheim-123', json_encode($audit->new_values, JSON_THROW_ON_ERROR));
        $this->assertSame('neu gesetzt', $audit->new_values['passwort'] ?? null);
    }

    public function test_leeres_passwort_behaelt_das_bisherige(): void
    {
        Mail::fake();
        $this->post('/admin/system/mail', $this->data());
        $this->post('/admin/system/mail', $this->data(['password' => '', 'from_name' => 'Glossary']));

        $this->assertSame('Sehr-Geheim-123', MailSettings::stored()['password'] ?? null);
        $this->assertSame('Glossary', MailSettings::stored()['from_name'] ?? null);
    }

    public function test_ohne_testmail_gilt_der_versand_nicht_als_eingerichtet(): void
    {
        MailSettings::save($this->userWithRoles([Role::DEVELOPER], 'zwei@example.org'), [
            'mailer' => 'smtp', 'host' => 'mail.example.ch', 'port' => 587, 'from_address' => 'noreply@eduksx.ch',
        ]);

        $this->assertTrue(MailSettings::unverified());
        $this->assertFalse(MailSetup::configured());
    }

    public function test_umgebung_hat_vorrang_und_sperrt_das_formular(): void
    {
        config(['glossary.mail_env' => true]);
        Mail::fake();

        $this->post('/admin/system/mail', $this->data())->assertSessionHasErrors('mailer');
        Mail::assertNothingSent();
        $this->assertNull(MailSettings::stored());
    }

    public function test_einstellung_entfernen(): void
    {
        Mail::fake();
        $this->post('/admin/system/mail', $this->data());
        $this->post('/admin/system/mail/entfernen')->assertSessionHas('status');

        $this->assertNull(MailSettings::stored());
        $this->assertSame('vorgabe', MailSettings::source());
    }

    public function test_nur_mit_recht_system(): void
    {
        $this->actingAs($this->admin());
        $this->post('/admin/system/mail', $this->data())->assertForbidden();
    }
}
