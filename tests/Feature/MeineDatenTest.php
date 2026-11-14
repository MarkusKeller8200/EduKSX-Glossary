<?php

namespace Tests\Feature;

use App\Access\AccessDenied;
use App\Access\AccountManager;
use App\Access\Audit;
use App\Access\Service\ServiceAccountManager;
use App\Data\PersonalData;
use App\Glossary\GlossaryService;
use App\Models\Role;
use App\Models\TermUserOverride;
use App\Models\User;
use App\Organizations\OrganizationService;
use App\Proposals\ProposalService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Auskunft und Datenherausgabe (#312, DB-Standard B8) und Einschränkung der Bearbeitung (B13). */
final class MeineDatenTest extends TestCase
{
    /** Daten von Lea (A) und Remo (B) in möglichst vielen Tabellen; Lea handelt auch an Remo. */
    private function daten(): array
    {
        $lea = $this->userWithRoles([Role::ORG_ADMIN, Role::MODERATOR], 'lea@schule.example');
        $remo = $this->userWithRoles([Role::USER], 'remo@schule.example');
        $lea->identities()->create(['provider' => 'microsoft', 'issuer' => 'https://login.example/v2.0', 'subject' => 'sub-lea', 'entra_tenant_id' => 't1']);
        $remo->identities()->create(['provider' => 'microsoft', 'issuer' => 'https://login.example/v2.0', 'subject' => 'sub-remo']);

        $glossary = app(GlossaryService::class);
        $term = $glossary->save(null, ['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung'], $lea, true);
        $term = $glossary->publish($term, $lea);
        TermUserOverride::query()->forceCreate(['term_id' => $term->id, 'user_id' => $lea->id, 'name_override' => 'Leitung (Lea)']);
        TermUserOverride::query()->forceCreate(['term_id' => $term->id, 'user_id' => $remo->id, 'name_override' => 'Leitung (Remo)']);
        app(ProposalService::class)->submit($term, ['short_description' => 'von Lea'], 'Grund Lea', $lea);
        app(ProposalService::class)->submit($term, ['short_description' => 'von Remo'], 'Grund Remo', $remo);

        $organizations = app(OrganizationService::class);
        $organization = $organizations->create($lea, 'Schule Muster');
        $organizations->invite($lea, $organization, 'neu@schule.example');
        $accounts = app(ServiceAccountManager::class);
        $accounts->createToken($lea, $accounts->create($lea, 'Intranet', 'Begriffe', ['glossary:terms.read']), 'Prod', 30);
        Audit::log($lea, $remo, 'account.blocked');
        Audit::log($remo, $lea, 'account.blocked');
        DB::table('import_batches')->insert(['imported_by_id' => $lea->id, 'user_label' => 'Lea', 'file_name' => 'a.xlsx', 'format_version' => 1, 'rows' => 1, 'created_count' => 1, 'updated_count' => 0, 'error_count' => 0]);

        return [$lea, $remo];
    }

    public function test_export_enthaelt_alles_und_nichts_von_anderen(): void
    {
        [$lea, $remo] = $this->daten();

        $response = $this->actingAs($lea)->get('/konto/meine-daten')->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $export = $response->json();
        $this->assertSame(PersonalData::FORMAT, $export['format']);

        // je Tabelle mit Personenbezug: alle Zeilen von Lea
        $covered = 0;
        foreach (PersonalData::references() as $table => $columns) {
            $count = DB::table($table)->where(function ($q) use ($columns, $lea): void {
                foreach ($columns as $column) {
                    $q->orWhere($column, $lea->id);
                }
            })->count();
            $this->assertSame($count, count($export['tables'][$table]['rows'] ?? []), $table);
            $covered += $count > 0 ? 1 : 0;
        }
        $this->assertGreaterThanOrEqual(10, $covered, 'Daten in vielen Tabellen');
        $this->assertSame('lea@schule.example', $export['tables']['users']['rows'][0]['email']);
        $this->assertSame('neu@schule.example', $export['tables']['organization_invitations']['rows'][0]['email'] ?? null);

        // nichts von Remo und keine Geheimnisse
        $json = json_encode($export, JSON_UNESCAPED_UNICODE);
        foreach ([$remo->id, 'remo@schule.example', 'sub-remo', 'Leitung (Remo)', 'Grund Remo'] as $other) {
            $this->assertStringNotContainsString($other, (string) $json);
        }
        $this->assertArrayNotHasKey('password', $export['tables']['users']['rows'][0]);
        $this->assertArrayNotHasKey('token_hash', $export['tables']['service_account_tokens']['rows'][0] ?? []);
        $this->assertStringContainsString(PersonalData::OTHER, (string) $json);

        // Aufruf im Sicherheitsprotokoll
        $this->assertDatabaseHas('user_audit', ['action' => 'account.data_exported', 'actor_id' => $lea->id, 'target_user_id' => $lea->id]);
    }

    public function test_verwaltung_exportiert_fuer_eine_anfrage_per_brief(): void
    {
        [$lea] = $this->daten();
        $admin = $this->admin();

        $this->actingAs($admin)->get("/admin/benutzer/{$lea->id}/daten")->assertOk()->assertJsonPath('subject.email', 'lea@schule.example');
        $this->assertDatabaseHas('user_audit', ['action' => 'account.data_exported', 'actor_id' => $admin->id, 'target_user_id' => $lea->id]);
        $this->actingAs($lea)->get("/admin/benutzer/{$lea->id}/daten")->assertForbidden();
    }

    public function test_einschraenkung_der_bearbeitung(): void
    {
        $admin = $this->admin();
        $lea = $this->userWithRoles([Role::USER], 'lea@schule.example');

        $this->artisan('glossary:einschraenken', ['konto' => 'lea@schule.example'])->assertSuccessful();
        $this->assertTrue($lea->fresh()?->is_processing_restricted);
        $this->assertDatabaseHas('user_audit', ['action' => 'account.processing_restricted', 'target_user_id' => $lea->id]);

        // nicht angezeigt
        $this->actingAs($admin)->get('/admin/benutzer')->assertInertia(fn (Assert $p) => $p->where('benutzer', fn ($list) => collect($list)->contains(fn ($u) => $u['id'] === $lea->id && $u['email'] === '' && $u['name'] === 'Bearbeitung eingeschränkt')));
        $this->actingAs($admin)->get("/admin/benutzer/{$lea->id}")->assertRedirect('/admin/benutzer');
        // nicht geändert – weder durch die Verwaltung noch durch die Person
        try {
            app(AccountManager::class)->block($admin, $lea->fresh() ?? $lea);
            $this->fail('Sperren hätte abgewiesen werden müssen.');
        } catch (AccessDenied $e) {
            $this->assertStringContainsString('eingeschränkt', $e->getMessage());
        }
        $this->actingAs($lea->fresh() ?? $lea)->put('/konto', ['first_name' => 'X', 'last_name' => 'Y'])->assertStatus(423);
        // Auskunft bleibt möglich
        $this->actingAs($admin)->get("/admin/benutzer/{$lea->id}/daten")->assertOk();

        $this->artisan('glossary:einschraenken', ['konto' => $lea->id, '--aufheben' => true])->assertSuccessful();
        $this->assertFalse($lea->fresh()?->is_processing_restricted);
        $this->assertInstanceOf(User::class, $lea->fresh());
    }
}
