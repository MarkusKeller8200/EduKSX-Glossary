<?php

namespace Tests\Feature;

use App\Access\IdentEvents;
use App\Models\Role;
use App\Models\User;
use App\Models\UserIdentity;
use EduKSX\Core\Spec\Schemas;
use EduKSX\Core\Testing\SendsPlatformEvents;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Plattform: Steckbrief und /health (#189), Ereignisse von Ident (#187). */
final class PlattformTest extends TestCase
{
    use SendsPlatformEvents;

    private const PLATTFORM_ID = '0193a1b2-0000-7000-8000-00000000b001';

    public function test_steckbrief_nach_schema(): void
    {
        $response = $this->get('/.well-known/eduksx-module.json');

        $response->assertOk();
        $this->assertSame([], Schemas::errors('module-manifest', json_decode((string) $response->getContent())));
        $response->assertJson([
            'module' => 'glossary',
            'name' => 'EduKSX-Glossary',
            'version' => config('glossary.version'),
            'operation_mode' => 'central',
            'events_consumed' => ['ident.user.deleted', 'ident.user.disabled'],
            'urls' => ['up' => '/up', 'health' => '/health', 'openapi' => '/api/openapi.json', 'docs' => '/api/docs'],
            'data_license' => 'CC-BY-4.0',
        ]);
    }

    public function test_health_und_up(): void
    {
        $response = $this->get('/health');

        $response->assertOk()->assertJsonPath('status', 'ok')->assertJsonPath('module', 'glossary')
            ->assertJsonPath('checks.database.status', 'ok')->assertJsonPath('checks.migrations.status', 'ok');
        $this->assertSame([], Schemas::errors('health', json_decode((string) $response->getContent())));
        $this->get('/up')->assertOk();
    }

    public function test_health_meldet_ausstehende_migration_mit_503(): void
    {
        DB::table('migrations')->where('migration', '2026_11_14_000100_ereignisse_von_ident')->delete();

        $this->get('/health')->assertStatus(503)->assertJsonPath('checks.migrations.status', 'fail');
    }

    private function identUser(string $email, string $subject): User
    {
        $user = $this->userWithRoles([Role::USER], $email);
        UserIdentity::query()->create([
            'user_id' => $user->id, 'provider' => IdentEvents::PROVIDER, 'issuer' => 'https://ident.eduksx.example', 'subject' => $subject,
        ]);

        return $user;
    }

    public function test_konto_geloescht_bei_ident_loescht_das_konto(): void
    {
        $this->admin();   // Schutzregel: es bleibt eine Verwaltung
        $user = $this->identUser('lea@schule.example', '312845729384756201');

        $this->postPlatformEvent('ident.user.deleted', ['user_id' => self::PLATTFORM_ID, 'subject' => '312845729384756201'])->assertNoContent();

        $this->assertNull(User::query()->find($user->id));
        $this->assertDatabaseMissing('user_identities', ['subject' => '312845729384756201']);
    }

    public function test_konto_gesperrt_bei_ident_sperrt_das_konto(): void
    {
        $this->admin();
        $user = $this->identUser('remo@schule.example', '4711');

        $this->postPlatformEvent('ident.user.disabled', ['user_id' => self::PLATTFORM_ID, 'subject' => '4711'])->assertNoContent();

        $this->assertSame('blocked', $user->fresh()?->status);
    }

    public function test_unbekannte_person_gilt_als_verarbeitet(): void
    {
        $this->postPlatformEvent('ident.user.deleted', ['user_id' => self::PLATTFORM_ID, 'subject' => 'unbekannt'])->assertNoContent();

        $this->assertDatabaseHas('received_events', ['type' => 'ident.user.deleted']);
    }

    public function test_unsignierte_und_veraltete_ereignisse_werden_abgewiesen(): void
    {
        $user = $this->identUser('lea@schule.example', '99');
        $data = ['user_id' => self::PLATTFORM_ID, 'subject' => '99'];

        $this->postRawPlatformEvent('{"specversion":"1.0"}', key: '')->assertStatus(401)->assertHeader('Content-Type', 'application/problem+json');
        $this->postPlatformEvent('ident.user.deleted', $data, time: time() - 301)->assertStatus(401);
        $this->postPlatformEvent('ident.user.deleted', $data, key: 'falscher-schluessel-0123456789abcdef0')->assertStatus(401);

        $this->assertNotNull(User::query()->find($user->id));
    }
}
