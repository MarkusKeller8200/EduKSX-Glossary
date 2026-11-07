<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use App\Glossary\SyncClient;
use App\Models\Category;
use App\Models\Term;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sync mit einem Abnehmer automatisch getestet (#29, Abnahme Etappe 0.3): Der Referenzclient arbeitet nur über die
 * API v1; nach jedem Schritt muss sein Stand genau dem Vollstand des Servers entsprechen.
 */
class SyncTest extends TestCase
{
    private GlossaryService $glossary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->glossary = app(GlossaryService::class);
        Category::query()->create(['key' => 'per', 'abbreviation' => 'PER', 'name' => 'Personal', 'label' => 'Personal']);
    }

    /** @param  array<string, mixed>  $data */
    private function term(array $data, bool $publish = true): Term
    {
        $term = $this->glossary->save(null, $data, null, true);

        return $publish ? $this->glossary->publish($term, null) : $term;
    }

    /** @param  array<mixed>|null  $state */
    private function client(?array $state = null): SyncClient
    {
        return new SyncClient(function (string $path): array {
            $json = $this->getJson($path)->assertOk()->json();

            return is_array($json) ? $json : [];
        }, $state);
    }

    private function assertIdentisch(SyncClient $client): void
    {
        $this->assertSame([], $client->compare());
    }

    public function test_sync_ablauf_bleibt_identisch(): void
    {
        $sl = $this->term(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung', 'categories' => ['per']]);
        $lp = $this->term(['official_abbreviation' => 'LP', 'official_name' => 'Lehrperson']);

        // 1. Vollstand
        $client = $this->client();
        $this->assertSame('vollstand', $client->sync()['art']);
        $this->assertCount(2, $client->state()['terms']);
        $this->assertIdentisch($client);

        // 2. Ändern, Zurückziehen, Entwurf (bleibt unsichtbar)
        $this->glossary->publish($this->glossary->save($sl, ['short_description' => 'Leitet die Schule.'], null, true), null);
        $this->glossary->unpublish($lp, null);
        $this->term(['official_abbreviation' => 'DaZ', 'official_name' => 'Deutsch als Zweitsprache'], publish: false);

        $client = $this->client($client->state());   // wie ein neuer Lauf mit gespeichertem Stand
        $result = $client->sync();
        $this->assertSame(['art' => 'aenderungen', 'published' => 1, 'unpublished' => 1, 'merged' => 0], array_slice($result, 0, 4));
        $this->assertSame('Leitet die Schule.', $client->state()['terms'][$sl->id]['short_description']);
        $this->assertArrayNotHasKey($lp->id, $client->state()['terms']);
        $this->assertIdentisch($client);

        // 3. Wiederaufnahme
        $this->glossary->publish($lp->refresh(), null);
        $client->sync();
        $this->assertArrayHasKey($lp->id, $client->state()['terms']);
        $this->assertIdentisch($client);

        // 4. Zusammenführen: alte ID verschwindet, Weiterleitung auf den verbleibenden Begriff
        $doppelt = $this->term(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung', 'categories' => []]);
        $client->sync();
        $this->glossary->merge($sl->refresh(), [$doppelt], [], null, true);
        $result = $client->sync();
        $this->assertSame(1, $result['merged']);
        $this->assertArrayNotHasKey($doppelt->id, $client->state()['terms']);
        $this->assertSame($sl->id, $client->resolve($doppelt->id));
        $this->assertIdentisch($client);

        // 5. Im veröffentlichten Stand inaktiv → beim Abnehmer entfernt
        $this->glossary->publish($this->glossary->save($lp->refresh(), ['is_active' => false], null, true), null);
        $client->sync();
        $this->assertArrayNotHasKey($lp->id, $client->state()['terms']);
        $this->assertIdentisch($client);

        // 6. Bezeichnung einer Kategorie ändern: Begriffe bleiben gleich (Schlüssel), Listen separat
        Category::query()->where('key', 'per')->update(['label' => 'Personal und Anstellung']);
        $this->assertIdentisch($client);

        // 7. Wiederholter Abruf ohne Änderungen: nichts passiert (idempotent)
        $before = $client->state();
        $result = $client->sync();
        $this->assertSame(0, $result['published'] + $result['unpublished'] + $result['merged']);
        $this->assertSame($before, $client->state());
    }

    public function test_vollstand_mit_cursor_holt_spaetere_ereignisse_nach(): void
    {
        $this->term(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung']);
        $this->getJson('/api/v1/exports?format=json')->assertJsonPath('cursor', 1);
        $this->getJson('/api/v1/releases/latest')->assertJsonPath('data.cursor', 1);

        // Abnehmer mit altem Cursor 0: wiederholtes Anwenden derselben Ereignisse schadet nicht
        $client = $this->client(['cursor' => 0, 'release' => 0, 'terms' => [], 'redirects' => []]);
        $client->sync();
        $client->sync();
        $this->assertCount(1, $client->state()['terms']);
        $this->assertIdentisch($client);
    }

    public function test_befehl_sync_check(): void
    {
        $this->term(['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung']);
        Http::fake(function (ClientRequest $request) {
            $parts = parse_url($request->url());
            $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

            return Http::response($this->getJson($path)->json());
        });
        $state = 'storage/framework/testing/sync-check.json';
        @mkdir(dirname(base_path($state)), 0777, true);

        $this->artisan('glossary:sync-check', ['url' => 'http://glossary.test', '--state' => $state, '--neu' => true])
            ->expectsOutputToContain('Stand identisch')
            ->assertSuccessful();

        $this->term(['official_abbreviation' => 'LP', 'official_name' => 'Lehrperson']);
        $this->artisan('glossary:sync-check', ['url' => 'http://glossary.test', '--state' => $state])
            ->expectsOutputToContain('veröffentlicht 1')
            ->assertSuccessful();

        @unlink(base_path($state));
    }
}
