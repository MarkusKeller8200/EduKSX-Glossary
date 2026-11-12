<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\UserAudit;
use App\Support\Settings;
use App\Support\UpdateCheck;
use App\Support\UpdateJobs;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Hinweis auf neue Versionen (#176), Update aus der Administration (#177), automatische Updates (#178). */
class UpdatesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        File::deleteDirectory(UpdateJobs::dir());
        config(['glossary.version' => '0.9.1', 'glossary.update_check' => true, 'glossary.update_key' => '']);
        Http::fake(['api.github.com/*' => Http::response(['tag_name' => 'v0.9.2'])]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(UpdateJobs::dir());
        parent::tearDown();
    }

    private function serviceActive(): void
    {
        File::ensureDirectoryExists(UpdateJobs::dir());
        file_put_contents(UpdateJobs::dir().'/stand.json', json_encode(['gemeldet' => now()->toIso8601String(), 'status' => 'bereit', 'meldung' => 'wartet']));
    }

    public function test_neue_version_wird_erkannt(): void
    {
        $status = UpdateCheck::status();

        $this->assertTrue($status['neu']);
        $this->assertSame('0.9.2', $status['neueste']);
        $this->assertSame('korrektur', $status['art']);
        $this->assertSame('funktion', UpdateCheck::kind('0.9.1', '0.10.0'));
        $this->assertSame('hauptversion', UpdateCheck::kind('0.9.1', '1.0.0'));
    }

    public function test_pruefung_ausgeschaltet_fragt_nicht_nach(): void
    {
        config(['glossary.update_check' => false]);

        $this->assertNull(UpdateCheck::latest());
        Http::assertNothingSent();
    }

    public function test_richtlinie_fuer_automatische_updates(): void
    {
        $this->assertSame('0.9.2', UpdateJobs::automaticTarget('0.9.1', '0.9.2', 'korrektur'));
        $this->assertNull(UpdateJobs::automaticTarget('0.9.1', '0.10.0', 'korrektur'));
        $this->assertSame('0.10.0', UpdateJobs::automaticTarget('0.9.1', '0.10.0', 'funktion'));
        $this->assertNull(UpdateJobs::automaticTarget('0.9.1', '1.0.0', 'funktion'));
        $this->assertNull(UpdateJobs::automaticTarget('0.9.1', '0.9.2', 'aus'));
        $this->assertNull(UpdateJobs::automaticTarget('0.9.2', '0.9.2', 'korrektur'));
    }

    public function test_update_aus_der_administration_legt_auftrag_ab(): void
    {
        $this->serviceActive();
        $this->actingAs($this->userWithRoles([Role::DEVELOPER], 'dev@example.org'));

        $this->get('/admin/system')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('update.neu', true)->where('update.dienst', true));
        $this->post('/admin/system/update', ['ziel' => '0.9.2'])->assertSessionHasNoErrors();

        $this->assertSame('0.9.2', UpdateJobs::pending()['ziel'] ?? null);
        $this->assertTrue(UserAudit::query()->where('action', 'system.update.requested')->exists());
        $this->post('/admin/system/update', ['ziel' => '0.9.2'])->assertSessionHasErrors('ziel');
    }

    public function test_ohne_dienst_oder_mit_falschem_schluessel_kein_auftrag(): void
    {
        $this->actingAs($this->userWithRoles([Role::DEVELOPER], 'dev@example.org'));
        $this->post('/admin/system/update', ['ziel' => '0.9.2'])->assertSessionHasErrors('ziel');

        $this->serviceActive();
        config(['glossary.update_key' => 'richtig']);
        $this->post('/admin/system/update', ['ziel' => '0.9.2', 'schluessel' => 'falsch'])->assertSessionHasErrors('schluessel');
        $this->assertNull(UpdateJobs::pending());

        $this->post('/admin/system/update', ['ziel' => '0.9.2', 'schluessel' => 'richtig'])->assertSessionHasNoErrors();
        $this->assertNotNull(UpdateJobs::pending());
    }

    public function test_nur_mit_recht_system(): void
    {
        $this->serviceActive();
        $this->actingAs($this->admin());
        $this->post('/admin/system/update', ['ziel' => '0.9.2'])->assertForbidden();
    }

    public function test_automatisch_im_zeitfenster(): void
    {
        $this->serviceActive();
        DB::table('settings')->insert(['key' => 'updates.window', 'value' => json_encode((int) now()->format('G'))]);
        Settings::flush();

        $this->artisan('glossary:updates-pruefen')->assertSuccessful();
        $this->assertSame('0.9.2', UpdateJobs::pending()['ziel'] ?? null);
        $this->assertSame('automatisch', UpdateJobs::pending()['angefordert_von'] ?? null);
    }

    public function test_automatisch_aus(): void
    {
        $this->serviceActive();
        DB::table('settings')->insert([
            ['key' => 'updates.window', 'value' => json_encode((int) now()->format('G'))],
            ['key' => 'updates.policy', 'value' => json_encode('aus')],
        ]);
        Settings::flush();

        $this->artisan('glossary:updates-pruefen')->assertSuccessful();
        $this->assertNull(UpdateJobs::pending());
    }
}
