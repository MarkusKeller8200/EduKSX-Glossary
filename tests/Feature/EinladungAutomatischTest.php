<?php

namespace Tests\Feature;

use App\Mail\ConfirmRegistration;
use App\Mail\InvitationAccepted;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\User;
use App\Organizations\OrganizationService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Einladung beim Anlegen eines Kontos mit der eingeladenen Adresse automatisch annehmen (#461). */
final class EinladungAutomatischTest extends TestCase
{
    private const FORM = [
        'first_name' => 'Nora', 'last_name' => 'Neu', 'email' => 'Neu@Schule.ch',
        'password' => 'ein-langes-Test-Passwort-7', 'password_confirmation' => 'ein-langes-Test-Passwort-7',
        'datenschutz' => true,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['glossary.registration_open' => true, 'mail.default' => 'smtp', 'mail.from.address' => 'noreply@eduksx.ch']);
        Mail::fake();
    }

    private function bestaetigen(string $email): void
    {
        $url = '';
        Mail::assertQueued(ConfirmRegistration::class, function (ConfirmRegistration $m) use (&$url, $email): bool {
            $url = $m->url;

            return $m->hasTo($email);
        });
        $this->get($url)->assertRedirect('/anmelden');
    }

    public function test_registrierung_mit_eingeladener_adresse_wird_mitglied(): void
    {
        $admin = $this->userWithRoles([Role::USER], 'admin@schule.ch');
        $organization = app(OrganizationService::class)->create($admin, 'Schule Muster');
        $this->actingAs($admin)->post('/organisation/einladungen', ['adressen' => 'neu@schule.ch'])->assertRedirect();
        auth()->logout();
        $this->travel(5)->seconds();

        $this->post('/registrieren', self::FORM)->assertRedirect('/anmelden');
        $user = User::query()->where('email', 'neu@schule.ch')->firstOrFail();
        $this->assertNull(OrganizationMember::query()->where('user_id', $user->id)->first());   // erst nach Bestätigung

        $this->bestaetigen('neu@schule.ch');
        $this->assertSame($organization->id, OrganizationMember::query()->where('user_id', $user->id)->value('organization_id'));
        $this->assertSame(0, OrganizationInvitation::query()->count());
        $this->assertStringContainsString('Mitglied von «Schule Muster»', (string) session('status'));
        Mail::assertQueued(InvitationAccepted::class, fn (InvitationAccepted $m): bool => $m->hasTo('admin@schule.ch') && $m->organization === 'Schule Muster');
    }

    public function test_ohne_einladung_oder_andere_adresse_keine_mitgliedschaft(): void
    {
        $admin = $this->userWithRoles([Role::USER], 'admin@schule.ch');
        app(OrganizationService::class)->create($admin, 'Schule Muster');
        $this->actingAs($admin)->post('/organisation/einladungen', ['adressen' => 'jemand@schule.ch'])->assertRedirect();
        auth()->logout();

        $this->post('/registrieren', self::FORM)->assertRedirect('/anmelden');
        $this->bestaetigen('neu@schule.ch');
        $user = User::query()->where('email', 'neu@schule.ch')->firstOrFail();
        $this->assertNull(OrganizationMember::query()->where('user_id', $user->id)->first());
        $this->assertSame(1, OrganizationInvitation::query()->count());
        Mail::assertNotQueued(InvitationAccepted::class);
    }

    public function test_bestehendes_konto_nimmt_weiterhin_selbst_an(): void
    {
        $admin = $this->userWithRoles([Role::USER], 'admin@schule.ch');
        $bestehend = $this->userWithRoles([Role::USER], 'alt@schule.ch');
        $organization = app(OrganizationService::class)->create($admin, 'Schule Muster');
        $this->actingAs($admin)->post('/organisation/einladungen', ['adressen' => 'alt@schule.ch'])->assertRedirect();

        // Einladung jünger als das Konto: keine automatische Mitgliedschaft
        $this->assertNull(app(OrganizationService::class)->joinOnVerifiedEmail($bestehend, 'Test'));
        $this->assertSame(1, OrganizationInvitation::query()->where('organization_id', $organization->id)->count());
    }
}
