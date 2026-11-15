<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Organizations\OrganizationService;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Verlauf für Konten und Organisationen (#417): eigene Änderungen und solche durch Admin bzw. Org-Admin. */
final class VerlaufTest extends TestCase
{
    /** @param  list<array{am: string, wer: string, was: string, details: string}>  $verlauf */
    private function enthaelt(array $verlauf, string $was, string $details = ''): bool
    {
        return collect($verlauf)->contains(fn (array $e): bool => str_contains($e['was'], $was) && str_contains($e['details'], $details));
    }

    public function test_konto_zeigt_eigene_und_fremde_aenderungen_ohne_personenwerte(): void
    {
        $user = $this->userWithRoles([Role::USER], 'lea@schule.example');
        $this->actingAs($user);
        $this->put('/konto', ['first_name' => 'Lea', 'last_name' => 'Muster', 'function' => 'Lehrerin', 'institution_kind' => 'primary_school'])
            ->assertRedirect();

        // Admin vergibt eine Rolle
        $this->actingAs($admin = $this->admin());
        $this->put("/admin/benutzer/{$user->id}/rollen", ['rollen' => [Role::USER, Role::MODERATOR]])->assertRedirect();

        $this->get("/admin/benutzer/{$user->id}")->assertInertia(fn (Assert $p) => $p
            ->where('verlauf', fn ($v): bool => $this->enthaelt($v->all(), 'Rolle vergeben') && $this->enthaelt($v->all(), 'Konto geändert', 'Funktion geändert')));

        $this->actingAs(User::query()->findOrFail($user->id));
        $response = $this->get('/konto')->assertInertia(fn (Assert $p) => $p
            ->where('verlauf', fn ($v): bool => $this->enthaelt($v->all(), 'Konto geändert', 'Art der Einrichtung')
                && $this->enthaelt($v->all(), 'Rolle vergeben')));
        // personenbezogene Werte erscheinen nur als «geändert»
        $this->assertStringNotContainsString('Lehrerin', json_encode($response->viewData('page')['props']['verlauf']) ?: '');
    }

    public function test_organisation_zeigt_verlauf_nur_admins(): void
    {
        $leitung = $this->userWithRoles([Role::USER], 'leitung@schule.example');
        $org = app(OrganizationService::class)->create($leitung, 'Schule Muster');
        app(OrganizationService::class)->rename($leitung->fresh() ?? $leitung, $org, 'Schule Musterdorf');

        $this->actingAs(User::query()->findOrFail($leitung->id));
        $this->get('/organisation')->assertInertia(fn (Assert $p) => $p
            ->where('verlauf', fn ($v): bool => $this->enthaelt($v->all(), 'Organisation geändert', 'Schule Muster → Schule Musterdorf')
                && $this->enthaelt($v->all(), 'Mitgliedschaft angelegt')));

        $this->actingAs($this->admin());
        $this->get("/admin/organisationen/{$org->id}")->assertInertia(fn (Assert $p) => $p
            ->where('verlauf', fn ($v): bool => $this->enthaelt($v->all(), 'Organisation angelegt')));
    }
}
