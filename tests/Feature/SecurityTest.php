<?php

namespace Tests\Feature;

use App\Access\AccessDenied;
use App\Access\AccountManager;
use App\Access\RoleManager;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAudit;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/** MFA nach effektiven Rechten, Sperren, sofort wirksame Rechte (#37, docs/KONZEPT-BENUTZER.md 5.2–5.4). */
class SecurityTest extends TestCase
{
    public function test_privilegierte_rechte_nur_mit_zweitem_faktor(): void
    {
        $moderator = $this->userWithRoles([Role::MODERATOR], 'mod@example.org', twoFactor: false);
        $this->actingAs($moderator);

        $this->assertFalse(Gate::allows('edit-terms'));
        $this->get('/admin')->assertRedirect('/admin/zwei-faktor');
        $this->get('/admin/begriffe')->assertRedirect('/admin/zwei-faktor');
        $this->get('/admin/zwei-faktor')->assertOk();
    }

    public function test_community_nach_anmeldung_auf_die_startseite(): void
    {
        User::query()->create(['name' => 'C', 'email' => 'c@example.org', 'password' => 'ein-langes-Test-Passwort-1']);

        $this->post('/anmelden', ['email' => 'c@example.org', 'password' => 'ein-langes-Test-Passwort-1'])->assertRedirect('/');
        $this->get('/admin')->assertForbidden();
    }

    public function test_rollenaenderung_wirkt_sofort(): void
    {
        $admin = $this->admin();
        $moderator = $this->userWithRoles([Role::MODERATOR], 'mod@example.org');
        $this->actingAs($moderator)->get('/admin/begriffe')->assertOk();

        app(RoleManager::class)->revoke($admin, $moderator, Role::byKey(Role::MODERATOR));

        $this->actingAs($moderator)->get('/admin/begriffe')->assertForbidden();
    }

    public function test_gesperrtes_konto_verliert_den_zugang(): void
    {
        $admin = $this->admin();
        $moderator = $this->userWithRoles([Role::MODERATOR], 'mod@example.org');
        $this->actingAs($moderator)->get('/admin/begriffe')->assertOk();

        app(AccountManager::class)->block($admin, $moderator);

        $this->actingAs($moderator->refresh())->get('/admin/begriffe')->assertRedirect('/anmelden');
        $this->assertGuest();
        $this->post('/anmelden', ['email' => 'mod@example.org', 'password' => 'ein-langes-Test-Passwort-1'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertTrue(UserAudit::query()->where('action', 'account.blocked')->where('result', 'ok')->exists());

        $this->artisan('glossary:admin', ['email' => 'mod@example.org', '--entsperren' => true])->assertSuccessful();
        $this->assertTrue($moderator->refresh()->isActive());
    }

    public function test_sperren_mit_schutzregeln(): void
    {
        $admin = $this->admin();
        $dev = $this->userWithRoles([Role::DEVELOPER], 'dev@example.org');
        $accounts = app(AccountManager::class);

        foreach ([[$admin, $dev], [$admin, $admin], [$dev, $dev]] as [$actor, $target]) {
            try {
                $accounts->block($actor, $target);
                $this->fail('Sperren hätte abgewiesen werden müssen.');
            } catch (AccessDenied) {
                $this->assertTrue($target->refresh()->isActive());
            }
        }
        // Developer sperrt den einzigen Admin nicht, wenn sonst niemand verwalten kann? Developer zählt als Verwalter.
        $accounts->block($dev, $admin);
        $this->assertFalse($admin->refresh()->isActive());
    }

    public function test_mfa_reset_wird_protokolliert(): void
    {
        $admin = $this->admin();
        $this->artisan('glossary:admin', ['email' => 'admin@example.org', '--reset-2fa' => true])->assertSuccessful();

        $this->assertFalse($admin->refresh()->hasTwoFactor());
        $entry = UserAudit::query()->where('action', 'mfa.reset')->firstOrFail();
        $this->assertSame($admin->id, $entry->target_user_id);
        $this->assertSame('Serverbefehl', $entry->actor_label);
    }
}
