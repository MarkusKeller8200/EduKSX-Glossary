<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\UserAudit;
use App\Support\AppVersion;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Alle Einstellungen auf einer Seite, Umgebung sichtbar, Geheimnisse in der Administration (#366). */
final class EinstellungenUebersichtTest extends TestCase
{
    private string|false $appKey = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->appKey = getenv('APP_KEY');
    }

    protected function tearDown(): void
    {
        $this->appKey === false ? putenv('APP_KEY') : putenv('APP_KEY='.$this->appKey);
        putenv('GLOSSARY_SOURCE_URL');
        putenv('GLOSSARY_DEV_BACKUPS');
        parent::tearDown();
    }

    public function test_umgebung_sichtbar_geheimnisse_ohne_wert(): void
    {
        putenv('GLOSSARY_DEV_BACKUPS=true');
        putenv('APP_KEY=base64:geheim-geheim-geheim');
        $this->actingAs($this->userWithRoles([Role::DEVELOPER], 'dev@example.org'));
        $page = $this->get('/admin/einstellungen')->assertOk();
        $page->assertInertia(fn (Assert $p) => $p->component('admin/settings')->has('umgebung')->whereNot('ordner', null));
        $items = collect($page->viewData('page')['props']['umgebung'])->keyBy('name');
        $this->assertSame('true', $items['GLOSSARY_DEV_BACKUPS']['wert']);
        $this->assertTrue($items['APP_KEY']['gesetzt']);
        $this->assertNull($items['APP_KEY']['wert']);
        $this->assertStringNotContainsString('geheim-geheim', (string) $page->getContent());
    }

    public function test_metrik_token_in_der_administration_sofort_wirksam(): void
    {
        config(['glossary.metrics_token' => '']);
        $dev = $this->userWithRoles([Role::DEVELOPER], 'dev@example.org');
        $this->actingAs($dev);
        $this->get('/metrics')->assertNotFound();

        $this->post('/admin/einstellungen/geheim/metrics.token', ['aktion' => 'erzeugen'])->assertRedirect()->assertSessionHas('neues_geheimnis');
        $token = session('neues_geheimnis')['wert'];
        $this->assertStringNotContainsString($token, (string) DB::table('settings')->where('key', 'metrics.token')->value('value'), 'verschlüsselt gespeichert');
        Settings::flush();
        $this->get('/metrics', ['Authorization' => 'Bearer '.$token])->assertOk();

        $this->post('/admin/einstellungen/geheim/metrics.token', ['aktion' => 'erzeugen'])->assertRedirect();
        Settings::flush();
        $this->get('/metrics', ['Authorization' => 'Bearer '.$token])->assertStatus(401);

        $this->post('/admin/einstellungen/geheim/metrics.token', ['aktion' => 'entfernen'])->assertRedirect();
        Settings::flush();
        $this->get('/metrics')->assertNotFound();
        $log = UserAudit::query()->where('action', 'settings.changed')->get()->pluck('new_values')->all();
        $this->assertSame(['gesetzt', 'erneuert', 'entfernt'], array_column($log, 'new'));
        $this->assertStringNotContainsString($token, json_encode($log));
    }

    public function test_umgebung_hat_vorrang_und_sperrt(): void
    {
        $dev = $this->userWithRoles([Role::DEVELOPER], 'dev@example.org');
        Settings::set($dev, ['links.source_url' => 'https://github.com/eigen/glossary']);
        $this->assertStringStartsWith('https://github.com/eigen/glossary', AppVersion::sourceUrl());

        putenv('GLOSSARY_SOURCE_URL=https://example.org/quellcode');
        config(['glossary.source_url' => 'https://example.org/quellcode']);
        Settings::flush();
        $this->assertSame('GLOSSARY_SOURCE_URL', Settings::locked('links.source_url'));
        $this->assertSame('Umgebung', Settings::source('links.source_url'));
        $this->assertStringStartsWith('https://example.org/quellcode', AppVersion::sourceUrl());
        $this->assertSame([], Settings::set($dev, ['links.source_url' => 'https://github.com/anders/x']), 'gesperrt: wird übergangen');

        $this->actingAs($dev);
        $this->get('/admin/einstellungen')->assertInertia(fn (Assert $p) => $p->where(
            'einstellungen', fn ($items) => collect($items)->firstWhere('key', 'links.source_url')['gesperrt'] === 'GLOSSARY_SOURCE_URL'));
    }
}
