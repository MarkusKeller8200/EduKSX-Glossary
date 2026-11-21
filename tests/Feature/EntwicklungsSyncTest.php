<?php

namespace Tests\Feature;

use App\Access\Service\ServiceAccountManager;
use App\Glossary\GlossaryService;
use App\Models\Role;
use App\Models\SyncPeer;
use App\Models\Term;
use App\Models\User;
use App\Support\DatabaseDump;
use App\Support\DumpArchive;
use App\Support\Settings;
use App\Sync\ContentState;
use App\Sync\DevSync;
use App\Sync\SyncRole;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/** Entwicklungs-Sync dev ↔ test ↔ prod (#478, DEC-041 4a). */
final class EntwicklungsSyncTest extends TestCase
{
    private function role(string $role): void
    {
        config(['glossary.sync_role' => $role]);
        Settings::flush();
    }

    /** @param  list<string>  $scopes */
    private function token(array $scopes): string
    {
        $developer = $this->userWithRoles([Role::DEVELOPER], 'dev'.count($scopes).'@example.org');
        $manager = app(ServiceAccountManager::class);
        $account = $manager->create($developer, 'dev', 'Entwicklungs-Sync', $scopes);

        return $manager->createToken($developer, $account, 'Sync', 30)['plain'];
    }

    private function begriff(string $abk, bool $publish = true): Term
    {
        $glossary = app(GlossaryService::class);
        $term = $glossary->save(null, ['official_abbreviation' => $abk, 'official_name' => "Begriff {$abk}"], null, true);

        return $publish ? $glossary->publish($term, null) : $term;
    }

    public function test_rollen_und_richtungen(): void
    {
        $this->assertTrue(SyncRole::canPullFrom('master', 'development'));
        $this->assertTrue(SyncRole::canPullFrom('test', 'development'));
        $this->assertTrue(SyncRole::canPullFrom('master', 'test'));
        $this->assertFalse(SyncRole::canPullFrom('development', 'master'));
        $this->assertFalse(SyncRole::canUploadTo('development', 'master'));   // prod schreibt nie auf andere Instanzen
        $this->assertTrue(SyncRole::canUploadTo('master', 'test'));
        $this->assertTrue(SyncRole::canUploadTo('test', 'development'));
        $this->assertFalse(SyncRole::canUploadTo('development', 'test'));
    }

    public function test_rolle_entwicklung_nicht_in_production(): void
    {
        $admin = $this->userWithRoles([Role::DEVELOPER], 'devrolle@example.org');
        app()->detectEnvironment(fn () => 'production');
        try {
            Settings::set($admin, ['sync.role' => 'development']);
            $this->fail('development in production erlaubt');
        } catch (InvalidArgumentException) {
            $this->assertSame(SyncRole::INSTANCE, SyncRole::current());
        }
        // test geht auch mit APP_ENV=production
        Settings::set($admin, ['sync.role' => 'test']);
        $this->assertSame(SyncRole::TEST, SyncRole::current());
        app()->detectEnvironment(fn () => 'testing');
    }

    public function test_gegenstelle_stand_und_dump_nur_mit_token_und_als_quelle(): void
    {
        $this->begriff('AA');
        $token = $this->token(['glossary:sync.read']);
        $this->getJson('/api/sync/state')->assertUnauthorized();

        $this->role('development');
        $this->getJson('/api/sync/state', ['Authorization' => "Bearer {$token}"])->assertOk()
            ->assertJsonPath('role', 'development')->assertJsonPath('scopes', ['glossary:sync.read']);
        $this->get('/api/sync/dump', ['Authorization' => "Bearer {$token}"])->assertForbidden();

        $this->role('master');
        $response = $this->get('/api/sync/dump', ['Authorization' => "Bearer {$token}"])->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'sync').'.zip';
        file_put_contents($file, $response->streamedContent());
        $dump = DumpArchive::open($file);
        $this->assertTrue($dump->anonymized());
        $this->assertStringNotContainsString('@example.org', json_encode($dump->rows('users')) ?: '');
    }

    public function test_hochladen_als_entwurf_mit_konfliktpruefung(): void
    {
        $this->role('master');
        $existing = $this->begriff('AA');
        $token = $this->token(['glossary:sync.read', 'glossary:sync.write']);
        $auth = ['Authorization' => "Bearer {$token}"];
        $state = app(ContentState::class);

        // neuer Begriff von dev mit eigener UUID und eigenem Code
        $payload = $state->term($existing);
        $new = ['id' => '0195f6a2-7c1e-7a3b-9b2d-3f4e5a6b7c8d', 'code' => 'ZZ9ZZ9'] + array_merge($payload, ['official_abbreviation' => 'NEU', 'official_name' => 'Neu von dev']);
        $new['id'] = '0195f6a2-7c1e-7a3b-9b2d-3f4e5a6b7c8d';
        $new['code'] = 'ZZ9ZZ9';
        $changed = array_merge($payload, ['short_description' => 'Auf dev geändert']);

        $result = $this->postJson('/api/sync/upload', ['terms' => [
            ['base_revision' => null, 'term' => $new],
            ['base_revision' => $existing->revision, 'term' => $changed],
        ]], $auth)->assertOk()->json('terms');
        $this->assertSame('created', $result[0]['status'], (string) $result[0]['message']);
        $this->assertSame('updated', $result[1]['status']);

        $created = Term::query()->findOrFail('0195f6a2-7c1e-7a3b-9b2d-3f4e5a6b7c8d');
        $this->assertSame('ZZ9ZZ9', $created->code);
        $this->assertNull($created->published_revision_id);   // Entwurf
        $this->assertTrue($existing->refresh()->hasUnpublishedChanges());
        $this->assertSame('Auf dev geändert', $existing->short_description);
        $this->assertSame(ContentState::hash($state->term($existing)), $result[1]['hash']);

        // veraltete Basisrevision → Konflikt, nichts überschrieben
        $result = $this->postJson('/api/sync/upload', ['terms' => [
            ['base_revision' => 1, 'term' => array_merge($payload, ['short_description' => 'Überschreiben?'])],
        ]], $auth)->assertOk()->json('terms');
        $this->assertSame('conflict', $result[0]['status']);
        $this->assertSame('Auf dev geändert', $existing->refresh()->short_description);

        // direkt veröffentlichen nur mit Scope
        $this->postJson('/api/sync/upload', ['publish' => true, 'terms' => []], $auth)->assertForbidden();
    }

    public function test_direkt_veroeffentlichen_mit_scope(): void
    {
        $this->role('test');
        $token = $this->token(['glossary:sync.write', 'glossary:sync.publish']);
        $new = app(ContentState::class)->term($this->begriff('XX', false));
        Term::query()->delete();
        $result = $this->postJson('/api/sync/upload', ['publish' => true, 'terms' => [['base_revision' => null, 'term' => $new]]], ['Authorization' => "Bearer {$token}"])
            ->assertOk()->json('terms');
        $this->assertSame('created+published', $result[0]['status'], (string) $result[0]['message']);
        $this->assertNotNull(Term::query()->findOrFail($new['id'])->published_revision_id);
    }

    public function test_hochladen_auf_entwicklung_nicht_moeglich(): void
    {
        $this->role('development');
        $token = $this->token(['glossary:sync.write']);
        $this->postJson('/api/sync/upload', ['terms' => []], ['Authorization' => "Bearer {$token}"])->assertForbidden();
    }

    public function test_holen_ersetzt_inhalte_und_behaelt_konten_und_gegenstellen(): void
    {
        $this->role('development');
        $prodTerm = $this->begriff('PROD');
        // Stand «prod» als anonymisierter Dump
        $file = tempnam(sys_get_temp_dir(), 'prod').'.zip';
        DatabaseDump::create($file, null, null, true);
        $remoteState = app(ContentState::class)->state();

        // dev weicht ab: eigener Begriff und eigenes Konto, Gegenstelle erfasst
        $devTerm = $this->begriff('DEV');
        $dev = $this->userWithRoles([Role::DEVELOPER], 'entwickler@example.org');
        $peer = SyncPeer::query()->create(['name' => 'prod', 'url' => 'https://prod.example', 'token' => 'glossary_sa_abc']);

        Http::fake([
            'prod.example/api/sync/state' => Http::response(['role' => 'master', 'scopes' => ['glossary:sync.read', 'glossary:sync.write'], ...$remoteState]),
            'prod.example/api/sync/dump' => Http::response((string) file_get_contents($file), 200, ['Content-Type' => 'application/zip']),
        ]);

        $result = app(DevSync::class)->pull($peer, $dev);
        $this->assertGreaterThan(0, $result['rows']);
        $this->assertNotNull(Term::query()->find($prodTerm->id));
        $this->assertNull(Term::query()->find($devTerm->id));
        $this->assertNotNull(User::query()->where('email', 'entwickler@example.org')->first());   // Konto bleibt
        $peer = SyncPeer::query()->findOrFail($peer->id);   // Gegenstelle bleibt
        $this->assertSame('master', $peer->remote_role);
        $this->assertSame($remoteState['terms'], $peer->base['terms'] ?? null);
        $this->assertFileExists(storage_path('app/backups/sync/'.$result['backup']));
        Http::assertSent(fn (HttpRequest $r): bool => $r->hasHeader('Authorization', 'Bearer glossary_sa_abc'));
    }

    public function test_holen_nur_in_erlaubter_richtung(): void
    {
        $this->role('master');
        $peer = SyncPeer::query()->create(['name' => 'dev', 'url' => 'https://dev.example', 'token' => 'glossary_sa_abc']);
        Http::fake(['dev.example/api/sync/state' => Http::response(['role' => 'development', 'scopes' => ['glossary:sync.read'], 'terms' => [], 'categories' => [], 'sources' => []])]);
        $this->expectException(RuntimeException::class);
        app(DevSync::class)->pull($peer, $this->admin());
    }

    public function test_vergleich_und_hochladen_mit_basis(): void
    {
        $this->role('development');
        $a = $this->begriff('AA');
        $b = $this->begriff('BB');
        $state = app(ContentState::class);
        $remote = $state->state();
        $peer = SyncPeer::query()->create(['name' => 'test', 'url' => 'https://test.example', 'token' => 'glossary_sa_abc']);
        $peer->base = ['terms' => $remote['terms'], 'categories' => $remote['categories'], 'sources' => $remote['sources']];
        $peer->save();

        // auf dev: AA geändert, CC neu
        app(GlossaryService::class)->save($a, ['short_description' => 'neu auf dev'], null, true);
        $c = $this->begriff('CC');
        $uploaded = null;
        Http::fake(function (HttpRequest $request) use ($remote, &$uploaded) {
            if (str_ends_with($request->url(), '/api/sync/state')) {
                return Http::response(['role' => 'test', 'scopes' => ['glossary:sync.write'], ...$remote]);
            }
            $uploaded = $request->data();

            return Http::response(['terms' => array_map(fn (array $t): array => ['id' => $t['term']['id'], 'status' => $t['base_revision'] === null ? 'created' : 'updated', 'revision' => 9, 'hash' => 'h-'.$t['term']['id'], 'code' => null, 'message' => null], $request->data()['terms']), 'categories' => [], 'sources' => []]);
        });

        $sync = app(DevSync::class);
        $compare = $sync->compare($peer);
        $this->assertFalse($compare['blocked']);
        $states = array_column($compare['terms'], 'state', 'id');
        $this->assertSame(['local', 'new'], [$states[$a->id], $states[$c->id]]);
        $this->assertArrayNotHasKey($b->id, $states);

        $summary = $sync->upload($peer, [$a->id, $c->id], false, $this->admin());
        $this->assertSame([1, 1], [$summary['created'], $summary['updated']]);
        $this->assertFalse($uploaded['publish']);
        $this->assertSame($remote['terms'][$a->id][0], collect($uploaded['terms'])->firstWhere('term.id', $a->id)['base_revision']);
        $this->assertSame([9, 'h-'.$c->id], $peer->refresh()->base['terms'][$c->id]);
    }

    public function test_geaendertes_ziel_verlangt_zuerst_holen(): void
    {
        $this->role('development');
        $a = $this->begriff('AA');
        $state = app(ContentState::class);
        $base = $state->state();
        $peer = SyncPeer::query()->create(['name' => 'prod', 'url' => 'https://prod.example', 'token' => 'glossary_sa_abc']);
        $peer->base = ['terms' => $base['terms'], 'categories' => $base['categories'], 'sources' => $base['sources']];
        $peer->save();
        $remote = $base;
        $remote['terms'][$a->id] = [5, 'auf-prod-geaendert'];
        Http::fake(['prod.example/api/sync/state' => Http::response(['role' => 'master', 'scopes' => ['glossary:sync.write'], ...$remote])]);
        app(GlossaryService::class)->save($a, ['short_description' => 'dev'], null, true);

        $compare = app(DevSync::class)->compare($peer);
        $this->assertTrue($compare['blocked']);
        $this->assertSame('conflict', $compare['terms'][0]['state']);
        $this->expectExceptionMessage('zuerst «Holen»');
        app(DevSync::class)->upload($peer, [$a->id], false, $this->admin());
    }

    public function test_administration_nur_mit_recht_system_und_token_nie_sichtbar(): void
    {
        $this->role('development');
        $this->actingAs($this->userWithRoles([Role::MODERATOR], 'mod@example.org'))->get('/admin/system/sync')->assertForbidden();
        $this->actingAs($this->userWithRoles([Role::DEVELOPER], 'devs@example.org'));
        Http::fake(['prod.example/*' => Http::response(['role' => 'master', 'scopes' => [], 'terms' => [], 'categories' => [], 'sources' => []])]);
        $this->post('/admin/system/sync/gegenstellen', ['name' => 'prod', 'url' => 'https://prod.example', 'token' => 'glossary_sa_geheim123'])->assertRedirect();
        $peer = SyncPeer::query()->firstOrFail();
        $this->assertNotSame('glossary_sa_geheim123', $peer->getRawOriginal('token'));
        $page = $this->get("/admin/system/sync/gegenstellen/{$peer->id}")->assertOk();
        $this->assertStringNotContainsString('glossary_sa_geheim123', (string) $page->getContent());
        $this->post('/admin/system/sync/gegenstellen', ['name' => 'x', 'url' => 'ftp://x', 'token' => 'glossary_sa_abc'])->assertSessionHasErrors('url');
    }
}
