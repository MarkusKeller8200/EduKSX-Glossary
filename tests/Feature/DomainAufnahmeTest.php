<?php

namespace Tests\Feature;

use App\Mail\DomainMembersJoined;
use App\Mail\JoinedByDomain;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\User;
use App\Organizations\OrganizationService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Konten mit der Domain der Organisation automatisch aufnehmen (#462). */
final class DomainAufnahmeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'smtp', 'mail.from.address' => 'noreply@eduksx.ch']);
        Mail::fake();
    }

    /** @return array{0: Organization, 1: User} */
    private function organisation(string $email = 'admin@schule-muster.ch'): array
    {
        $admin = $this->userWithRoles([Role::USER], $email, true);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $organization = app(OrganizationService::class)->create($admin, 'Schule Muster');

        return [$organization, $admin->fresh() ?? $admin];
    }

    private function konto(string $email, bool $bestaetigt = true): User
    {
        $u = $this->userWithRoles([Role::USER], $email);
        $u->forceFill(['email_verified_at' => $bestaetigt ? now() : null])->save();

        return $u;
    }

    public function test_domain_aus_bestaetigter_adresse_und_aufnahme_bestehender_konten(): void
    {
        [$organization, $admin] = $this->organisation();
        $lea = $this->konto('lea@schule-muster.ch');
        $unbestaetigt = $this->konto('ben@schule-muster.ch', false);
        $fremd = $this->konto('eva@andere.ch');

        $this->actingAs($admin)->put('/organisation/domain', ['setzen' => true])->assertSessionHasNoErrors();
        $this->assertSame('schule-muster.ch', $organization->fresh()->email_domain);

        $this->put('/organisation/domain/automatisch', ['an' => true])->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertSame('domain', OrganizationMember::query()->where('user_id', $lea->id)->value('joined_via'));
        $this->assertNull(OrganizationMember::query()->where('user_id', $unbestaetigt->id)->first());
        $this->assertNull(OrganizationMember::query()->where('user_id', $fremd->id)->first());
        Mail::assertQueued(JoinedByDomain::class, fn (JoinedByDomain $m): bool => $m->hasTo('lea@schule-muster.ch'));
        Mail::assertQueued(DomainMembersJoined::class, fn (DomainMembersJoined $m): bool => $m->hasTo('admin@schule-muster.ch') && $m->names === [$lea->name]);

        // Hinweis bei der nächsten Anmeldung genau einmal
        $this->assertSame('Schule Muster', app(OrganizationService::class)->pendingDomainNotice($lea));
        $this->assertNull(app(OrganizationService::class)->pendingDomainNotice($lea));
    }

    public function test_neues_konto_nach_bestaetigung_aufgenommen(): void
    {
        [$organization, $admin] = $this->organisation();
        $organization->forceFill(['email_domain' => 'schule-muster.ch', 'is_domain_auto_join' => true])->save();
        $neu = $this->konto('neu@schule-muster.ch');

        $this->assertSame($organization->id, app(OrganizationService::class)->joinOnDomain($neu)?->id);
        Mail::assertQueued(DomainMembersJoined::class);
    }

    public function test_oeffentliche_anbieter_und_doppelte_domain_abgewiesen(): void
    {
        [, $admin] = $this->organisation('lehrerin@gmail.com');
        $this->actingAs($admin)->put('/organisation/domain', ['setzen' => true])->assertSessionHasErrors('domain');

        [$erste] = $this->organisation2('chef@schule-x.ch', 'Erste');
        $erste->forceFill(['email_domain' => 'schule-x.ch'])->save();
        [, $zweiter] = $this->organisation2('andere@schule-x.ch', 'Zweite');
        $this->actingAs($zweiter)->put('/organisation/domain', ['setzen' => true])->assertSessionHasErrors('domain');
    }

    public function test_ohne_bestaetigte_adresse_keine_domain(): void
    {
        [, $admin] = $this->organisation();
        $admin->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($admin)->put('/organisation/domain', ['setzen' => true])->assertSessionHasErrors('domain');
    }

    public function test_admin_kann_domain_loesen(): void
    {
        [$organization] = $this->organisation();
        $organization->forceFill(['email_domain' => 'schule-muster.ch', 'is_domain_auto_join' => true])->save();
        $this->actingAs($this->userWithRoles([Role::ADMIN], 'chef@example.org', true));
        $this->delete("/admin/organisationen/{$organization->id}/domain")->assertRedirect();
        $this->assertNull($organization->fresh()->email_domain);
        $this->assertFalse($organization->fresh()->is_domain_auto_join);
    }

    /** @return array{0: Organization, 1: User} */
    private function organisation2(string $email, string $name): array
    {
        $admin = $this->userWithRoles([Role::USER], $email, true);
        $admin->forceFill(['email_verified_at' => now()])->save();

        return [app(OrganizationService::class)->create($admin, $name), $admin->fresh() ?? $admin];
    }
}
