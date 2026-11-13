<?php

namespace Tests\Feature;

use App\Access\Service\ServiceAccountManager;
use App\Glossary\GlossaryService;
use App\Models\Category;
use App\Models\Proposal;
use App\Models\ProposalEvent;
use App\Models\Role;
use App\Models\Term;
use App\Models\User;
use App\Proposals\ProposalService;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Vorschläge und Moderation (#113, DEC-022, DEC-024). */
class ProposalTest extends TestCase
{
    private GlossaryService $glossary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->glossary = app(GlossaryService::class);
        Category::query()->create(['key' => 'per', 'abbreviation' => 'PER', 'name' => 'Personal', 'label' => 'Personal']);
    }

    private function published(): Term
    {
        return $this->glossary->publish($this->glossary->save(null, [
            'official_abbreviation' => 'SL', 'official_name' => 'Schulleitung', 'short_description' => 'Leitet die Schule',
        ], null, true), null);
    }

    private function benutzer(string $email = 'b@example.org'): User
    {
        $user = $this->userWithRoles([Role::USER], $email, twoFactor: false);
        if ($email === 'b@example.org') {
            $user->forceFill(['community_name' => 'Anna'])->save();
        }

        return $user;
    }

    public function test_benutzer_reicht_korrektur_und_neuen_begriff_ein(): void
    {
        $term = $this->published();
        $this->actingAs($user = $this->benutzer());

        $this->get("/vorschlaege/neu?begriff={$term->slug}")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('proposals/create')->where('werte.official_name', 'Schulleitung'));

        // unveränderte Felder werden nicht übermittelt
        $this->post('/vorschlaege', [
            'begriff' => $term->slug,
            'werte' => ['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung', 'short_description' => 'Führt die Schule'],
            'begruendung' => 'Gemäss Volksschulgesetz',
        ])->assertRedirect();
        $proposal = Proposal::query()->firstOrFail();
        $this->assertSame('change', $proposal->proposal_type);
        $this->assertSame(['short_description' => 'Führt die Schule'], $proposal->changes);
        $this->assertSame(1, $proposal->base_revision);
        $this->assertSame('Anna', $proposal->author_label);

        // ohne Änderung abgewiesen
        $this->post('/vorschlaege', ['begriff' => $term->slug, 'werte' => ['official_name' => 'Schulleitung']])
            ->assertSessionHasErrors('werte');

        $this->post('/vorschlaege', ['werte' => ['official_abbreviation' => 'LP', 'official_name' => 'Lehrperson', 'categories' => ['per']]])
            ->assertRedirect();
        $this->assertSame(2, Proposal::query()->where('user_id', $user->id)->count());

        $this->get('/vorschlaege')->assertInertia(fn (Assert $p) => $p->component('proposals/index')->has('vorschlaege', 2));

        // nie öffentlich
        $this->assertStringNotContainsString('Führt die Schule', (string) $this->getJson('/api/v1/exports?format=json')->getContent());
        $this->assertStringNotContainsString('Lehrperson', (string) $this->getJson('/api/v1/terms')->getContent());
    }

    public function test_gast_und_abgemeldet_ohne_vorschlag(): void
    {
        $this->get('/vorschlaege/neu')->assertRedirect('/anmelden');
        $this->actingAs($this->userWithRoles([Role::GUEST], 'g@example.org', twoFactor: false));
        $this->get('/vorschlaege/neu')->assertForbidden();
        $this->post('/vorschlaege', ['werte' => ['official_name' => 'X']])->assertForbidden();
    }

    public function test_annehmen_ergibt_entwurf_mit_kontext(): void
    {
        $term = $this->published();
        $proposal = app(ProposalService::class)->submit($term, ['short_description' => 'Führt die Schule'], null, $this->benutzer());

        $this->actingAs($this->userWithRoles([Role::MODERATOR], 'm@example.org'));
        $this->get('/admin/vorschlaege')->assertInertia(fn (Assert $p) => $p->component('admin/proposals')->has('vorschlaege', 1));
        $this->get("/admin/vorschlaege/{$proposal->id}")->assertInertia(fn (Assert $p) => $p
            ->component('admin/proposal-show')->where('vergleich.0.konflikt', false)->where('vergleich.0.vorschlag', 'Führt die Schule'));

        $this->post("/admin/vorschlaege/{$proposal->id}/annehmen", ['felder' => ['short_description']])->assertRedirect("/admin/begriffe/{$term->id}");

        $term->refresh();
        $this->assertSame('Führt die Schule', $term->short_description);
        $this->assertSame(2, $term->revision);
        $this->assertSame(1, $term->publishedRevision?->revision);   // Entwurf, nicht veröffentlicht
        $this->assertSame(['via' => 'vorschlag', 'proposal_id' => $proposal->id, 'proposed_by' => 'Anna'], $term->revisions()->where('revision', 2)->firstOrFail()->context);
        $this->assertSame('accepted', $proposal->refresh()->status);
        $this->assertSame(2, $proposal->result_revision);
        $this->assertStringNotContainsString('Führt die Schule', (string) $this->getJson("/api/v1/terms/{$term->id}")->getContent());
    }

    public function test_konflikt_wird_angezeigt_und_nicht_still_ueberschrieben(): void
    {
        $term = $this->published();
        $proposal = app(ProposalService::class)->submit($term, ['short_description' => 'Vorschlag', 'description' => 'Neue Beschreibung'], null, $this->benutzer());
        // Redaktion ändert danach dasselbe Feld
        $this->glossary->save($term, ['short_description' => 'Redaktion'], null, true);

        $this->actingAs($this->userWithRoles([Role::MODERATOR], 'm@example.org'));
        $this->get("/admin/vorschlaege/{$proposal->id}")->assertInertia(fn (Assert $p) => $p
            ->where('vergleich.0.feld', 'short_description')->where('vergleich.0.konflikt', true)
            ->where('vergleich.0.basis', 'Leitet die Schule')->where('vergleich.0.aktuell', 'Redaktion')
            ->where('vergleich.1.konflikt', false));

        // nur das konfliktfreie Feld übernehmen
        $this->post("/admin/vorschlaege/{$proposal->id}/annehmen", ['felder' => ['description']])->assertRedirect();
        $term->refresh();
        $this->assertSame('Redaktion', $term->short_description);
        $this->assertSame('Neue Beschreibung', $term->description);
        $event = ProposalEvent::query()->where('event_type', 'accepted')->firstOrFail();
        $this->assertSame(['short_description'], $event->details['skipped'] ?? null);
    }

    public function test_rueckfrage_antwort_ablehnen_und_zurueckziehen(): void
    {
        $term = $this->published();
        $service = app(ProposalService::class);
        $user = $this->benutzer();
        $proposal = $service->submit($term, ['url' => 'https://www.vsa.zh.ch'], null, $user);

        $this->actingAs($this->userWithRoles([Role::MODERATOR], 'm@example.org'));
        $this->post("/admin/vorschlaege/{$proposal->id}/rueckfrage", ['frage' => 'Welche Seite genau?'])->assertRedirect();
        $this->post("/admin/vorschlaege/{$proposal->id}/ablehnen", ['begruendung' => ''])->assertSessionHasErrors('begruendung');
        $this->assertSame('query', $proposal->refresh()->status);

        $this->actingAs($user);
        $this->get("/vorschlaege/{$proposal->id}")->assertInertia(fn (Assert $p) => $p
            ->where('vorschlag.status', 'query')->where('vorschlag.ereignisse.1.text', 'Welche Seite genau?')
            ->where('vorschlag.ereignisse.1.von', 'Redaktion'));
        $this->post("/vorschlaege/{$proposal->id}/antwort", ['antwort' => 'Die Startseite'])->assertRedirect();
        $this->assertSame('submitted', $proposal->refresh()->status);

        $this->actingAs($this->userWithRoles([Role::MODERATOR], 'm2@example.org'));
        $this->post("/admin/vorschlaege/{$proposal->id}/ablehnen", ['begruendung' => 'Keine amtliche Quelle'])->assertRedirect('/admin/vorschlaege');
        $this->assertSame('rejected', $proposal->refresh()->status);
        $this->assertNotNull($proposal->resolved_at);

        // zurückziehen nur offen und nur eigene
        $second = $service->submit($term, ['description' => 'Text'], null, $user);
        $this->actingAs($this->benutzer('other@example.org'));
        $this->get("/vorschlaege/{$second->id}")->assertNotFound();
        $this->post("/vorschlaege/{$second->id}/zurueckziehen")->assertSessionHasErrors('vorschlag');
        $this->actingAs($user);
        $this->post("/vorschlaege/{$second->id}/zurueckziehen")->assertRedirect();
        $this->assertSame('withdrawn', $second->refresh()->status);
    }

    public function test_offizielles_feld_braucht_recht(): void
    {
        $term = $this->published();
        $proposal = app(ProposalService::class)->submit($term, ['official_name' => 'Schulleiterin'], null, $this->benutzer());

        $this->actingAs($this->userWithRoles([Role::MODERATOR], 'm@example.org'));
        $this->post("/admin/vorschlaege/{$proposal->id}/annehmen", ['felder' => ['official_name']])->assertSessionHasErrors('felder');
        $this->assertSame('Schulleitung', $term->refresh()->official_name);

        $this->actingAs($this->admin());
        $this->post("/admin/vorschlaege/{$proposal->id}/annehmen", ['felder' => ['official_name']])->assertRedirect();
        $this->assertSame('Schulleiterin', $term->refresh()->official_name);
    }

    public function test_benutzer_ohne_moderation(): void
    {
        $this->actingAs($this->benutzer());
        $this->get('/admin/vorschlaege')->assertForbidden();
    }

    public function test_vorschlag_per_servicetoken_landet_in_der_moderation(): void
    {
        $term = $this->published();
        $owner = $this->userWithRoles([Role::ORG_ADMIN], 'org@example.org');
        $manager = app(ServiceAccountManager::class);
        $account = $manager->create($owner, 'Intranet', 'Vorschläge', ['propose']);
        ['plain' => $plain] = $manager->createToken($owner, $account, 'Prod', 30);

        $response = $this->postJson('/api/v1/proposals', [
            'term' => $term->slug, 'changes' => ['short_description' => 'Aus dem Intranet'], 'reason' => 'Rückmeldung',
        ], ['Authorization' => "Bearer {$plain}"])->assertCreated()->assertJsonPath('status', 'submitted');
        $id = (int) $response->json('id');

        $proposal = Proposal::query()->findOrFail($id);
        $this->assertSame($account->id, $proposal->service_account_id);
        $this->assertSame('Servicekonto «Intranet»', $proposal->author_label);
        $this->getJson("/api/v1/proposals/{$id}", ['Authorization' => "Bearer {$plain}"])->assertOk()->assertJsonPath('id', $id);
        $this->assertSame('Leitet die Schule', $term->refresh()->short_description);   // nicht direkt geändert
        $this->assertStringNotContainsString('Aus dem Intranet', (string) $this->getJson("/api/v1/terms/{$term->id}")->getContent());

        // ohne Scope bzw. ohne Token abgewiesen
        $other = $manager->create($owner, 'Lesend', 'Nur Prüfung', []);
        ['plain' => $noScope] = $manager->createToken($owner, $other, 'Prod', 30);
        $this->postJson('/api/v1/proposals', ['changes' => ['official_name' => 'X']], ['Authorization' => "Bearer {$noScope}"])->assertUnauthorized();
        $this->postJson('/api/v1/proposals', ['changes' => ['official_name' => 'X']])->assertUnauthorized();
    }

    public function test_loeschfrist_und_kontoloeschung_ohne_personenbezug(): void
    {
        $term = $this->published();
        $service = app(ProposalService::class);
        $user = $this->benutzer();
        $old = $service->submit($term, ['description' => 'Alt'], null, $user);
        $service->withdraw($old, $user);
        $fresh = $service->submit($term, ['description' => 'Neu'], null, $user);

        $this->travel(25)->months();
        $this->artisan('glossary:loeschfristen')->assertSuccessful();
        $old->refresh();
        $this->assertNull($old->user_id);
        $this->assertSame(Proposal::ANONYMOUS, $old->author_label);
        $this->assertSame(0, ProposalEvent::query()->where('proposal_id', $old->id)->where('actor_label', 'Anna')->count());
        $this->assertSame($user->id, $fresh->refresh()->user_id);   // offen: bleibt

        $user->delete();
        $fresh->refresh();
        $this->assertNull($fresh->user_id);
        $this->assertSame(Proposal::ANONYMOUS, $fresh->author_label);
        $this->assertSame('Neu', $fresh->changes['description']);
    }

    public function test_korrektur_bei_altem_abbild_ohne_listenfelder(): void
    {
        $term = $this->published();
        // Abbild wie vor DEC-020 (#84): ohne alternative_aliases und categories (#133)
        $revision = $term->publishedRevision;
        $this->assertNotNull($revision);
        $snapshot = $revision->snapshot;
        unset($snapshot['alternative_aliases'], $snapshot['categories']);
        $revision->newQuery()->whereKey($revision->id)->toBase()->update(['snapshot' => json_encode($snapshot)]);

        $this->actingAs($this->benutzer());
        $this->get("/vorschlaege/neu?begriff={$term->slug}")->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('proposals/create')->where('werte.alternative_aliases', [])->where('werte.categories', [])
            ->where('werte.official_name', 'Schulleitung'));

        $this->post('/vorschlaege', [
            'begriff' => $term->slug,
            'werte' => ['official_name' => 'Schulleitung', 'alternative_aliases' => '', 'categories' => [], 'short_description' => 'Neu'],
        ])->assertRedirect();
        $this->assertSame(['short_description' => 'Neu'], Proposal::query()->latest('id')->firstOrFail()->changes);
    }
}
