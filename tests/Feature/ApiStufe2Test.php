<?php

namespace Tests\Feature;

use App\Access\Service\ServiceAccountManager;
use App\Data\Purposes;
use App\Models\Proposal;
use App\Models\Role;
use App\Models\ServiceAccount;
use EduKSX\Core\Data\DataClass;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** API-Standard Stufe 2: If-Match (#347), Idempotenz (#348), Datenklassen und Zweck je Operation (#349). */
final class ApiStufe2Test extends TestCase
{
    /** @return array{0: ServiceAccount, 1: array<string, string>} */
    private function konto(): array
    {
        $manager = app(ServiceAccountManager::class);
        $owner = $this->userWithRoles([Role::ORG_ADMIN], 'org@example.org');
        $account = $manager->create($owner, 'Intranet', 'Vorschläge übermitteln', ['glossary:proposals.create', 'glossary:proposals.read']);
        ['plain' => $plain] = $manager->createToken($owner, $account, 'Produktion', 30);

        return [$account, ['Authorization' => 'Bearer '.$plain, 'Accept' => 'application/json']];
    }

    /** @return array{0: string, 1: string} UUID und ETag */
    private function einreichen(array $headers): array
    {
        $response = $this->postJson('/api/v1/proposals', ['changes' => ['official_name' => 'Hauswart']], $headers)->assertCreated();

        return [(string) $response->json('id'), (string) $response->headers->get('ETag')];
    }

    public function test_zurueckziehen_verlangt_if_match(): void
    {
        [, $headers] = $this->konto();
        [$id, $etag] = $this->einreichen($headers);
        $this->assertSame('"'.$id.'.0"', $etag);
        $this->getJson("/api/v1/proposals/{$id}", $headers)->assertOk()->assertHeader('ETag', $etag);

        $this->deleteJson("/api/v1/proposals/{$id}", [], $headers)
            ->assertStatus(428)->assertHeader('ETag', $etag)->assertJsonPath('code', 'precondition_required');
        $this->deleteJson("/api/v1/proposals/{$id}", [], $headers + ['If-Match' => '"'.$id.'.7"'])
            ->assertStatus(412)->assertHeader('ETag', $etag)->assertJsonPath('code', 'precondition_failed');

        $response = $this->deleteJson("/api/v1/proposals/{$id}", [], $headers + ['If-Match' => $etag])
            ->assertOk()->assertJsonPath('status', 'withdrawn');
        $neu = (string) $response->headers->get('ETag');
        $this->assertSame('"'.$id.'.1"', $neu);
        $this->assertSame('withdrawn', Proposal::query()->where('id', $id)->value('status'));

        // abgeschlossen: 409, auch mit passendem ETag
        $this->deleteJson("/api/v1/proposals/{$id}", [], $headers + ['If-Match' => $neu])->assertStatus(409);
    }

    public function test_nur_eigene_vorschlaege_zurueckziehen(): void
    {
        [, $headers] = $this->konto();
        [$id, $etag] = $this->einreichen($headers);
        $manager = app(ServiceAccountManager::class);
        $owner = $this->userWithRoles([Role::ORG_ADMIN], 'andere@example.org');
        $fremd = $manager->create($owner, 'Andere', 'Test', ['glossary:proposals.create']);
        ['plain' => $plain] = $manager->createToken($owner, $fremd, 'Test', 30);

        $this->deleteJson("/api/v1/proposals/{$id}", [], ['Authorization' => 'Bearer '.$plain, 'If-Match' => $etag])->assertNotFound();
    }

    public function test_idempotenz_bei_post(): void
    {
        [, $headers] = $this->konto();
        $headers['Idempotency-Key'] = 'intranet-0001';

        $erste = $this->postJson('/api/v1/proposals', ['changes' => ['official_name' => 'Hauswart']], $headers)->assertCreated();
        $zweite = $this->postJson('/api/v1/proposals', ['changes' => ['official_name' => 'Hauswart']], $headers)
            ->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame($erste->json('id'), $zweite->json('id'));
        $this->assertSame(1, Proposal::query()->count());

        $this->postJson('/api/v1/proposals', ['changes' => ['official_name' => 'Hauswartin']], $headers)
            ->assertStatus(422)->assertJsonPath('code', 'idempotency_key_reused');
        $this->postJson('/api/v1/proposals', ['changes' => ['official_name' => 'Hauswart']], ['Idempotency-Key' => str_repeat('x', 201)] + $headers)
            ->assertStatus(422);

        // anderer Schlüssel: neuer Vorschlag; nach 24 Stunden gelöscht
        $this->postJson('/api/v1/proposals', ['changes' => ['official_name' => 'Hauswart']], ['Idempotency-Key' => 'intranet-0002'] + $headers)->assertCreated();
        $this->assertSame(2, Proposal::query()->count());
        DB::table('api_idempotency_keys')->update(['created_at' => now()->subHours(25)]);
        $this->artisan('glossary:loeschfristen')->assertSuccessful();
        $this->assertSame(0, DB::table('api_idempotency_keys')->count());
    }

    public function test_jede_operation_nennt_zweck_und_datenklassen(): void
    {
        $spec = json_decode((string) file_get_contents(base_path('openapi/glossary-v1.json')), true);
        $classes = array_map(fn (DataClass $c): string => $c->value, DataClass::cases());
        $operations = [];
        foreach ($spec['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                if (! is_array($operation) || ! isset($operation['operationId'])) {
                    continue;
                }
                $id = $operation['operationId'];
                $operations[] = $id;
                $purpose = $operation['x-eduksx-purpose'] ?? null;
                $this->assertNotNull($purpose, "{$id}: x-eduksx-purpose fehlt");
                $this->assertContains($id, Purposes::MAP[$purpose]['operations'] ?? [], "{$id}: Zweck {$purpose} nennt die Operation nicht");
                $data = $operation['x-eduksx-data-classes'] ?? [];
                $this->assertNotSame([], $data, "{$id}: x-eduksx-data-classes fehlt");
                $this->assertSame([], array_diff($data, $classes), "{$id}: unbekannte Datenklasse");
                if (($operation['security'] ?? []) === []) {
                    $this->assertSame(['public'], $data, "{$id}: ohne Anmeldung nur öffentliche Daten (Positivliste)");
                }
            }
        }
        // jede Operation im Verzeichnis gibt es in der OpenAPI
        $listed = array_merge(...array_values(array_map(fn (array $p): array => $p['operations'], Purposes::MAP)));
        $this->assertSame([], array_values(array_diff($listed, $operations)));
    }
}
