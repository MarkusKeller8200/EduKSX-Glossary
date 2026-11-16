<?php

namespace Tests\Feature;

use App\Mail\RoleRequestDecided;
use App\Models\Role;
use App\Models\RoleRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Als Moderator/in bewerben (#460). */
final class ModeratorBewerbungTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'smtp', 'mail.from.address' => 'noreply@eduksx.ch']);
        Mail::fake();
    }

    private function bewerber(bool $zweiFaktor = true, bool $bestaetigt = true): User
    {
        $u = $this->userWithRoles([Role::USER], 'lea@schule.ch', $zweiFaktor);
        $u->forceFill(['email_verified_at' => $bestaetigt ? now() : null])->save();

        return $u->refresh();
    }

    public function test_hinweis_genau_einmal_nicht_fuer_moderation(): void
    {
        $this->actingAs($u = $this->bewerber());
        $this->get('/konto')->assertInertia(fn (Assert $p) => $p->where('moderatorHinweis.fehlt', []));
        $this->get('/konto')->assertInertia(fn (Assert $p) => $p->where('moderatorHinweis', null));
        $this->assertNotNull($u->refresh()->moderator_notice_seen_at);

        $this->actingAs($this->userWithRoles([Role::MODERATOR], 'mod@schule.ch', true));
        $this->get('/konto')->assertInertia(fn (Assert $p) => $p->where('moderatorHinweis', null));
    }

    public function test_voraussetzungen_zweiter_faktor_und_bestaetigte_adresse(): void
    {
        $this->actingAs($this->bewerber(false, false));
        $this->get('/konto')->assertInertia(fn (Assert $p) => $p
            ->where('moderation.fehlt', ['zweiter Faktor', 'bestätigte E-Mail-Adresse']));
        $this->post('/konto/moderation', ['begruendung' => 'Ich kenne die Schulbegriffe gut.'])->assertSessionHasErrors('begruendung');
        $this->assertSame(0, RoleRequest::query()->count());
    }

    public function test_bewerben_annehmen_durch_admin(): void
    {
        $this->actingAs($u = $this->bewerber());
        $this->post('/konto/moderation', ['begruendung' => 'Ich kenne die Schulbegriffe gut.'])->assertSessionHasNoErrors();
        $this->post('/konto/moderation', ['begruendung' => 'Nochmals, bitte.'])->assertSessionHasErrors('begruendung');   // eine offene
        $anfrage = RoleRequest::query()->firstOrFail();

        // Moderator (ohne «manage-users») darf nicht entscheiden
        $this->actingAs($this->userWithRoles([Role::MODERATOR], 'mod@schule.ch', true));
        $this->post("/admin/anfragen/{$anfrage->id}/annehmen")->assertForbidden();

        $this->actingAs($admin = $this->userWithRoles([Role::ADMIN], 'chef@schule.ch', true));
        $this->get('/admin')->assertInertia(fn (Assert $p) => $p->where('anfragen', 1));
        $this->get('/admin/anfragen')->assertOk()->assertInertia(fn (Assert $p) => $p->component('admin/role-requests')->has('anfragen', 1));
        $this->post("/admin/anfragen/{$anfrage->id}/annehmen")->assertSessionHasNoErrors();

        $this->assertContains(Role::MODERATOR, $u->roleKeys());
        $this->assertSame(RoleRequest::ACCEPTED, $anfrage->refresh()->status);
        $this->assertSame($admin->id, $anfrage->decided_by_id);
        Mail::assertQueued(RoleRequestDecided::class, fn (RoleRequestDecided $m): bool => $m->hasTo('lea@schule.ch') && $m->accepted);
    }

    public function test_ablehnen_nur_mit_begruendung(): void
    {
        $this->actingAs($u = $this->bewerber());
        $this->post('/konto/moderation', ['begruendung' => 'Ich kenne die Schulbegriffe gut.']);
        $anfrage = RoleRequest::query()->firstOrFail();

        $this->actingAs($this->userWithRoles([Role::ADMIN], 'chef@schule.ch', true));
        $this->post("/admin/anfragen/{$anfrage->id}/ablehnen")->assertSessionHasErrors('hinweis');
        $this->post("/admin/anfragen/{$anfrage->id}/ablehnen", ['hinweis' => 'Zurzeit genug Personen.'])->assertSessionHasNoErrors();
        $this->assertNotContains(Role::MODERATOR, $u->roleKeys());
        Mail::assertQueued(RoleRequestDecided::class, fn (RoleRequestDecided $m): bool => ! $m->accepted && $m->note === 'Zurzeit genug Personen.');

        // danach erneut bewerben möglich, letzte Ablehnung sichtbar
        $this->actingAs($u)->get('/konto')->assertInertia(fn (Assert $p) => $p->where('moderation.abgelehnt.hinweis', 'Zurzeit genug Personen.'));
    }

    public function test_zurueckziehen(): void
    {
        $this->actingAs($this->bewerber());
        $this->post('/konto/moderation', ['begruendung' => 'Ich kenne die Schulbegriffe gut.']);
        $anfrage = RoleRequest::query()->firstOrFail();
        $this->delete("/konto/moderation/{$anfrage->id}")->assertSessionHasNoErrors();
        $this->assertSame(0, RoleRequest::query()->count());
    }
}
