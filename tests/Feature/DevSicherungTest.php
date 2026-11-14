<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Support\DevBackups;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Sicherung und Wiederherstellung in der Administration, nur auf dev (#355). SQLite und PostgreSQL. */
final class DevSicherungTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['glossary.dev_backups' => true]);
        $this->leeren();
    }

    protected function tearDown(): void
    {
        $this->leeren();
        parent::tearDown();
    }

    private function leeren(): void
    {
        foreach (glob(DevBackups::dir().'/*.zip') ?: [] as $file) {
            @unlink($file);
        }
    }

    private function developer(): User
    {
        $user = $this->userWithRoles([Role::DEVELOPER], 'dev@example.org');
        $this->actingAs($user);

        return $user;
    }

    private function begriff(string $abbreviation, string $name): void
    {
        $glossary = app(GlossaryService::class);
        $glossary->publish($glossary->save(null, ['official_abbreviation' => $abbreviation, 'official_name' => $name], null, true), null);
    }

    private function sichern(): string
    {
        $this->post('/admin/system/sicherungen', ['bezeichnung' => 'Vor Test'])->assertSessionHasNoErrors()->assertRedirect();
        $liste = DevBackups::list();
        $this->assertCount(1, $liste);
        $this->assertStringEndsWith('-vor-test.zip', $liste[0]['name']);

        return $liste[0]['name'];
    }

    public function test_ausgeschaltet_nicht_erreichbar(): void
    {
        config(['glossary.dev_backups' => false]);
        $this->developer();
        $this->post('/admin/system/sicherungen')->assertNotFound();
        $this->post('/admin/system/wiederherstellen', ['sicherung' => 'x', 'bestaetigung' => 'WIEDERHERSTELLEN'])->assertNotFound();
        $this->get('/admin/system')->assertInertia(fn ($page) => $page->where('sicherungen', null));
    }

    public function test_nur_mit_recht_system(): void
    {
        $this->actingAs($this->admin());
        $this->post('/admin/system/sicherungen')->assertForbidden();
    }

    public function test_wiederherstellen_behaelt_konten_rollen_und_sitzungen(): void
    {
        $dev = $this->developer();
        $lea = $this->userWithRoles([Role::USER], 'lea@schule.example');
        $lea->identities()->create(['provider' => 'microsoft', 'issuer' => 'https://login.example/v2.0', 'subject' => 'sub-lea']);
        $org = Organization::query()->create(['name' => 'Schule Muster', 'created_by_id' => $lea->id]);
        DB::table('organization_members')->insert(['organization_id' => $org->id, 'user_id' => $lea->id]);
        $this->begriff('SL', 'Schulleitung');
        $name = $this->sichern();

        // danach: neuer Begriff, neues Konto mit Rolle, Lea gelöscht, Passwort geändert, Sitzung offen
        $this->begriff('LP', 'Lehrperson');
        $neu = $this->userWithRoles([Role::ADMIN], 'neu@schule.example');
        DB::table('organization_members')->where('user_id', $lea->id)->delete();
        DB::table('organizations')->where('id', $org->id)->update(['created_by_id' => null]);
        DB::table('user_identities')->where('user_id', $lea->id)->delete();
        DB::table('user_roles')->where('user_id', $lea->id)->delete();
        DB::table('users')->where('id', $lea->id)->delete();
        DB::table('users')->where('id', $dev->id)->update(['password' => 'neuer-hash']);
        DB::table('sessions')->insert(['id' => 'sitzung-dev', 'user_id' => null, 'ip_address' => null, 'user_agent' => null, 'payload' => 'x', 'last_activity' => time()]);
        $users = DB::table('users')->count();

        $this->post('/admin/system/wiederherstellen', ['sicherung' => $name, 'bestaetigung' => 'WIEDERHERSTELLEN'])
            ->assertSessionHasNoErrors()->assertSessionHas('status');

        // Daten aus der Sicherung
        $this->assertSame(1, DB::table('terms')->count());
        $this->assertSame(1, DB::table('organizations')->count());
        // Konten, Rollen, Sitzungen wie vorher
        $this->assertSame($users, DB::table('users')->count());
        $this->assertSame('neuer-hash', DB::table('users')->where('id', $dev->id)->value('password'));
        $this->assertSame(1, DB::table('user_roles')->where('user_id', $neu->id)->count());
        $this->assertSame(0, DB::table('users')->where('id', $lea->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('id', 'sitzung-dev')->count());
        // Verweise auf das gelöschte Konto: Mitgliedschaft entfällt, Ersteller leer
        $this->assertSame(0, DB::table('organization_members')->count());
        $this->assertNull(DB::table('organizations')->value('created_by_id'));
        // vorheriger Stand automatisch gesichert, Protokoll
        $this->assertTrue(collect(DevBackups::list())->contains('automatisch', true));
        $this->assertSame(1, DB::table('user_audit')->where('action', 'system.restore')->where('result', 'ok')->count());
    }

    public function test_hochladen_mit_passwort(): void
    {
        $this->developer();
        $this->begriff('SL', 'Schulleitung');
        $this->post('/admin/system/sicherungen', ['passwort' => 'ein-langes-passwort', 'passwort_confirmation' => 'ein-langes-passwort'])
            ->assertSessionHasNoErrors();
        $file = DevBackups::path(DevBackups::list()[0]['name']);
        $this->begriff('LP', 'Lehrperson');
        $upload = new UploadedFile($file, 'sicherung.zip', 'application/zip', null, true);

        $this->post('/admin/system/wiederherstellen', ['datei' => $upload, 'bestaetigung' => 'WIEDERHERSTELLEN'])
            ->assertSessionHasErrors('passwort');
        $this->assertSame(2, DB::table('terms')->count());

        $upload = new UploadedFile($file, 'sicherung.zip', 'application/zip', null, true);
        $this->post('/admin/system/wiederherstellen', ['datei' => $upload, 'passwort' => 'ein-langes-passwort', 'bestaetigung' => 'WIEDERHERSTELLEN'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('terms')->count());
    }

    public function test_bestaetigung_noetig_und_loeschen(): void
    {
        $this->developer();
        $this->begriff('SL', 'Schulleitung');
        $name = $this->sichern();
        $this->begriff('LP', 'Lehrperson');

        $this->post('/admin/system/wiederherstellen', ['sicherung' => $name, 'bestaetigung' => 'ja'])->assertSessionHasErrors('bestaetigung');
        $this->post('/admin/system/wiederherstellen', ['sicherung' => '../../.env', 'bestaetigung' => 'WIEDERHERSTELLEN'])->assertSessionHasErrors('sicherung');
        $this->assertSame(2, DB::table('terms')->count());

        $this->delete('/admin/system/sicherungen/'.$name)->assertRedirect();
        $this->assertSame([], DevBackups::list());
        $this->delete('/admin/system/sicherungen/'.$name)->assertNotFound();
    }
}
