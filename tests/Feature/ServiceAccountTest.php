<?php

namespace Tests\Feature;

use App\Access\AccessDenied;
use App\Access\Service\ServiceAccountManager;
use App\Models\Role;
use App\Models\ServiceAccount;
use App\Models\ServiceAccountAudit;
use App\Models\ServiceAccountToken;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Servicekonten mit Token (#47, KONZEPT-BENUTZER 2.2, Abnahmetest 3). */
class ServiceAccountTest extends TestCase
{
    private ServiceAccountManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(ServiceAccountManager::class);
    }

    /** @return array{0: ServiceAccount, 1: string} */
    private function kontoMitToken(int $days = 30): array
    {
        $owner = $this->userWithRoles([Role::ORG_ADMIN], 'org@example.org');
        $account = $this->manager->create($owner, 'Intranet', 'Vorschläge übermitteln', ['propose']);
        ['plain' => $plain] = $this->manager->createToken($owner, $account, 'Produktion', $days);

        return [$account, $plain];
    }

    public function test_organisations_admin_legt_konto_und_token_an(): void
    {
        $this->actingAs($this->userWithRoles([Role::ORG_ADMIN], 'org@example.org'));
        $this->get('/admin/servicekonten')->assertOk()->assertInertia(fn (Assert $p) => $p->component('admin/service-accounts'));

        $this->post('/admin/servicekonten', ['name' => 'Intranet', 'zweck' => 'Vorschläge', 'scopes' => ['propose']])->assertRedirect();
        $account = ServiceAccount::query()->firstOrFail();

        $this->post("/admin/servicekonten/{$account->id}/token", ['name' => 'Produktion', 'tage' => 90])
            ->assertRedirect()->assertSessionHas('neues_token');
        $token = ServiceAccountToken::query()->firstOrFail();
        $plain = (string) session('neues_token');
        $this->assertStringStartsWith('gls_', $plain);
        $this->assertSame(hash('sha256', $plain), $token->token_hash);
        $this->assertStringNotContainsString($plain, (string) json_encode($token->toArray()));
        $this->assertTrue(ServiceAccountAudit::query()->where('action', 'token.created')->exists());

        $this->getJson('/api/v1/service/whoami', ['Authorization' => "Bearer {$plain}"])->assertOk()
            ->assertJsonPath('service_account.name', 'Intranet')->assertJsonPath('service_account.scopes', ['propose']);
    }

    public function test_benutzer_und_moderator_ohne_servicekonten(): void
    {
        $this->actingAs($this->userWithRoles([Role::USER], 'b@example.org'));
        $this->get('/admin/servicekonten')->assertForbidden();
        $this->actingAs($this->userWithRoles([Role::MODERATOR], 'm@example.org'));
        $this->get('/admin/servicekonten')->assertForbidden();
    }

    public function test_abgelaufenes_widerrufenes_oder_gesperrtes_token_abgewiesen(): void
    {
        [$account, $plain] = $this->kontoMitToken(1);
        $header = ['Authorization' => "Bearer {$plain}"];
        $this->getJson('/api/v1/service/whoami', $header)->assertOk();

        $this->travel(2)->days();
        $this->getJson('/api/v1/service/whoami', $header)->assertUnauthorized();
        $this->assertTrue(ServiceAccountAudit::query()->where('action', 'token.used')->where('result', 'denied')->exists());
        $this->travelBack();

        $owner = $account->responsible;
        $this->assertNotNull($owner);
        ['plain' => $second, 'token' => $token] = $this->manager->createToken($owner, $account, 'Zweites', 30);
        $this->getJson('/api/v1/service/whoami', ['Authorization' => "Bearer {$second}"])->assertOk();
        $this->manager->revokeToken($owner, $token);
        $this->getJson('/api/v1/service/whoami', ['Authorization' => "Bearer {$second}"])->assertUnauthorized();

        ['plain' => $third] = $this->manager->createToken($owner, $account, 'Drittes', 30);
        $this->manager->setStatus($owner, $account, false);
        $this->getJson('/api/v1/service/whoami', ['Authorization' => "Bearer {$third}"])->assertUnauthorized();
        $this->getJson('/api/v1/service/whoami')->assertUnauthorized()->assertHeader('WWW-Authenticate');
        $this->getJson('/api/v1/service/whoami', ['Authorization' => 'Bearer gls_falsch'])->assertUnauthorized();
    }

    public function test_kein_browser_login(): void
    {
        [, $plain] = $this->kontoMitToken();
        // Token als Anmeldedaten oder Cookie: keine Sitzung
        $this->post('/anmelden', ['email' => 'Intranet', 'password' => $plain])->assertSessionHasErrors();
        $this->assertGuest();
        $this->get('/admin', ['Authorization' => "Bearer {$plain}"])->assertRedirect('/anmelden');
        $this->get('/konto', ['Authorization' => "Bearer {$plain}"])->assertRedirect('/anmelden');
        $this->assertGuest();
    }

    public function test_scope_nur_im_rahmen_der_eigenen_rechte_und_fremde_konten_geschuetzt(): void
    {
        [$account] = $this->kontoMitToken();
        $other = $this->userWithRoles([Role::ORG_ADMIN], 'other@example.org');

        // fremdes Konto: nicht sichtbar, nicht verwaltbar
        $this->assertSame(0, $this->manager->visibleFor($other)->count());
        $this->actingAs($other)->get("/admin/servicekonten/{$account->id}")->assertForbidden();
        $this->expectDenied(fn () => $this->manager->createToken($other, $account, 'X', 30));

        // Admin (manage-users) sieht alle
        $this->assertSame(1, $this->manager->visibleFor($this->admin())->count());

        // unbekannter Scope, zu lange Gültigkeit
        $this->expectDenied(fn () => $this->manager->create($other, 'X', 'Y', ['publish']));
        $this->expectDenied(fn () => $this->manager->createToken($account->responsible ?? $other, $account, 'X', 400));
    }

    private function expectDenied(callable $action): void
    {
        try {
            $action();
            $this->fail('Aktion hätte abgewiesen werden müssen.');
        } catch (AccessDenied) {
            $this->addToAssertionCount(1);
        }
    }
}
