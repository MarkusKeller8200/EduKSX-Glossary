<?php

namespace Tests\Feature;

use App\Access\AccessDenied;
use App\Access\Permission;
use App\Access\RoleManager;
use App\Glossary\GlossaryService;
use App\Models\Role;
use App\Models\Term;
use App\Models\User;
use App\Models\UserAudit;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/** Rollen und Rechte mit Schutzregeln (#36, docs/KONZEPT-BENUTZER.md Abschnitt 3, Abnahmetests 1 und 2). */
class AccessTest extends TestCase
{
    private RoleManager $roles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roles = app(RoleManager::class);
    }

    public function test_voreingestellte_rollen_und_rechte(): void
    {
        $this->assertEqualsCanonicalizing(['propose', 'export', 'personal-display', 'moderate', 'edit-terms'], Role::byKey(Role::MODERATOR)->permissionKeys());
        $this->assertContains('service-tokens', Role::byKey(Role::ADMIN)->permissionKeys());
        $this->assertNotContains('system', Role::byKey(Role::ADMIN)->permissionKeys());
        $this->assertSame(Permission::keys(), Role::byKey(Role::DEVELOPER)->permissionKeys());

        // Rollen Gast, Benutzer, Organisations-Admin (#110)
        $this->assertSame([], Role::byKey(Role::GUEST)->permissionKeys());
        $this->assertEqualsCanonicalizing(Permission::BASIC, Role::byKey(Role::USER)->permissionKeys());
        $this->assertEqualsCanonicalizing([...Permission::BASIC, 'service-tokens'], Role::byKey(Role::ORG_ADMIN)->permissionKeys());
        $this->assertSame('privileged', Permission::classOf('service-tokens'));

        // ohne Rolle keine Rechte mehr (bisher fest «propose»)
        $ohne = User::query()->create(['name' => 'C', 'email' => 'c@example.org', 'password' => 'ein-langes-Test-Passwort-1']);
        $this->assertSame([], $ohne->permissions());
        $this->assertFalse($ohne->needsTwoFactor());
    }

    public function test_gast_liest_benutzer_mit_grundrechten_ohne_zweiten_faktor(): void
    {
        $gast = $this->userWithRoles([Role::GUEST], 'g@example.org', twoFactor: false);
        $benutzer = $this->userWithRoles([Role::USER], 'b@example.org', twoFactor: false);
        $orgAdmin = $this->userWithRoles([Role::ORG_ADMIN], 'o@example.org', twoFactor: false);

        foreach (Permission::BASIC as $permission) {
            $this->assertFalse(Gate::forUser($gast)->allows($permission));
            $this->assertTrue(Gate::forUser($benutzer)->allows($permission));
        }
        $this->assertFalse($benutzer->needsTwoFactor());
        $this->assertFalse(Gate::forUser($benutzer)->allows('administer'));

        // Servicekonten sind privilegiert: zweiter Faktor nötig
        $this->assertTrue($orgAdmin->needsTwoFactor());
        $this->assertFalse(Gate::forUser($orgAdmin)->allows('service-tokens'));
        $orgAdmin->forceFill(['two_factor_secret' => self::TOTP_SECRET, 'two_factor_confirmed_at' => now()])->save();
        $orgAdmin->flushPermissions();
        $this->assertTrue(Gate::forUser($orgAdmin)->allows('service-tokens'));
    }

    public function test_migration_macht_konten_ohne_rolle_zu_benutzern(): void
    {
        $ohne = User::query()->create(['name' => 'Alt', 'email' => 'alt@example.org', 'password' => 'ein-langes-Test-Passwort-1']);
        $moderator = $this->userWithRoles([Role::MODERATOR], 'm@example.org');

        $migration = require database_path('migrations/2026_11_08_000100_add_base_roles.php');
        $migration->up();   // idempotent: Rollen bestehen schon, nur Konten ohne Rolle werden Benutzer

        $this->assertSame([Role::USER], $ohne->roleKeys());
        $this->assertSame([Role::MODERATOR], $moderator->roleKeys());
        $this->assertTrue(UserAudit::query()->where('target_user_id', $ohne->id)->where('actor_label', 'Migration')->exists());
        $this->assertSame(1, Role::query()->where('key', Role::USER)->count());
    }

    public function test_developer_nur_per_befehl_und_protokolliert(): void
    {
        $admin = $this->admin();
        $this->artisan('glossary:developer', ['email' => 'admin@example.org', '--grund' => 'Erstes Setup'])->assertSuccessful();

        $this->assertTrue($admin->refresh()->isDeveloper());
        $entry = UserAudit::query()->where('action', 'role.assigned')->where('target_user_id', $admin->id)->latest('id')->firstOrFail();
        $this->assertSame('Serverbefehl: Erstes Setup', $entry->actor_label);
        $this->assertSame('ok', $entry->result);
        $this->assertTrue($admin->hasPermission('system'));
    }

    public function test_admin_kann_niemandem_developer_geben(): void
    {
        $admin = $this->admin();
        $other = $this->userWithRoles([], 'other@example.org');

        foreach ([$admin, $other] as $target) {
            try {
                $this->roles->assign($admin, $target, Role::byKey(Role::DEVELOPER));
                $this->fail('Admin durfte Developer vergeben.');
            } catch (AccessDenied) {
                $this->assertFalse($target->refresh()->isDeveloper());
            }
        }
        $this->assertSame(2, UserAudit::query()->where('result', 'denied')->count());
    }

    public function test_letzter_developer_ist_geschuetzt(): void
    {
        $dev = $this->userWithRoles([Role::DEVELOPER], 'dev@example.org');

        $this->artisan('glossary:developer', ['email' => 'dev@example.org', '--entziehen' => true])->assertFailed();
        $this->assertTrue($dev->refresh()->isDeveloper());

        $second = $this->userWithRoles([Role::DEVELOPER], 'dev2@example.org');
        $this->roles->revoke($dev, $second, Role::byKey(Role::DEVELOPER));
        $this->assertFalse($second->refresh()->isDeveloper());
    }

    public function test_keine_eigenen_rechte_erhoehen(): void
    {
        $admin = $this->admin();
        $moderator = $this->userWithRoles([Role::MODERATOR], 'mod@example.org');

        // eigene Rollen nicht ändern
        $this->expectDenied(fn () => $this->roles->revoke($admin, $admin, Role::byKey(Role::ADMIN)));
        // einer eigenen Rolle keine Rechte hinzufügen, systemkritische Rechte nie über die Matrix
        $this->expectDenied(fn () => $this->roles->setPermissions($admin, Role::byKey(Role::ADMIN), [...Role::byKey(Role::ADMIN)->permissionKeys(), 'system']));
        $this->expectDenied(fn () => $this->roles->setPermissions($admin, Role::byKey(Role::DEVELOPER), ['propose']));
        // Moderator ohne «manage-users» verwaltet nichts
        $this->expectDenied(fn () => $this->roles->assign($moderator, $moderator, Role::byKey(Role::ADMIN)));

        // innerhalb der Grenze erlaubt: neue Rolle mit eigenen Rechten anlegen und vergeben
        $role = $this->roles->create($admin, 'Redaktion Schule');
        $this->roles->setPermissions($admin, $role, ['propose', 'edit-terms']);
        $this->roles->assign($admin, $moderator, $role);
        $this->assertContains('redaktion-schule', $moderator->refresh()->roleKeys());
        $this->assertFalse($moderator->hasPermission('publish'));
    }

    public function test_letzte_verwaltung_bleibt(): void
    {
        $admin = $this->admin();
        $other = $this->userWithRoles([Role::ADMIN], 'admin2@example.org');

        // anderer Admin kann entzogen werden, solange ein Verwalter bleibt
        $this->roles->revoke($admin, $other, Role::byKey(Role::ADMIN));
        // «manage-users» der einzigen Verwaltungsrolle kann nicht entfernt werden (Serverprozess)
        $this->expectDenied(fn () => $this->roles->setPermissions(null, Role::byKey(Role::ADMIN), ['propose', 'publish']));
        $this->assertContains('manage-users', Role::byKey(Role::ADMIN)->permissionKeys());
    }

    public function test_moderator_darf_bearbeiten_aber_nicht_veroeffentlichen(): void
    {
        $this->actingAs($this->userWithRoles([Role::MODERATOR], 'mod@example.org'));
        $term = app(GlossaryService::class)->save(null, ['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung'], null, true);

        $this->get('/admin')->assertOk();
        $this->get('/admin/begriffe')->assertOk();
        $this->get("/admin/begriffe/{$term->id}/bearbeiten")->assertOk();
        $this->post("/admin/begriffe/{$term->id}/veroeffentlichen")->assertForbidden();
        $this->post('/admin/begriffe/veroeffentlichen', ['ids' => [$term->id]])->assertForbidden();
        $this->get('/admin/listen')->assertForbidden();
        $this->get('/admin/import')->assertForbidden();
        $this->assertNull(Term::query()->findOrFail($term->id)->published_revision_id);
    }

    public function test_admin_darf_veroeffentlichen(): void
    {
        $this->actingAs($this->admin());
        $term = app(GlossaryService::class)->save(null, ['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung'], null, true);

        $this->post("/admin/begriffe/{$term->id}/veroeffentlichen")->assertRedirect();
        $this->assertNotNull($term->refresh()->published_revision_id);
    }

    public function test_benutzer_ohne_administration(): void
    {
        $this->actingAs($this->userWithRoles([Role::USER], 'c@example.org', twoFactor: false));
        $this->get('/admin')->assertForbidden();
        $this->get('/admin/begriffe')->assertForbidden();
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
