<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAudit;
use App\Support\Settings;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Allgemeine Einstellungen (#106). */
class SettingsTest extends TestCase
{
    private function developer(): User
    {
        return $this->userWithRoles([Role::DEVELOPER], 'dev@example.org');
    }

    public function test_seite_zeigt_und_speichert_mit_protokoll(): void
    {
        $this->actingAs($dev = $this->developer());
        $this->get('/admin/einstellungen')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('admin/settings')->has('einstellungen', count(Settings::DEFINITIONS))
            ->where('einstellungen.0.key', 'retention.inactive_months')->where('einstellungen.0.wert', 24));

        $this->put('/admin/einstellungen', ['werte' => ['source_check.months' => 6, 'privacy.draft_notice' => false, 'links.github_repo' => '']])
            ->assertRedirect();
        $this->assertSame(6, Settings::int('source_check.months'));
        $this->assertFalse(Settings::bool('privacy.draft_notice'));
        $this->assertSame(3, UserAudit::query()->where('action', 'settings.changed')->where('actor_id', $dev->id)->count());

        // wirkt: Prüfung, Datenschutz, Links
        $this->get('/admin/pruefung')->assertInertia(fn (Assert $p) => $p->where('monate', 6));
        $this->get('/datenschutz')->assertInertia(fn (Assert $p) => $p->where('entwurf', false));
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('links.githubRepo', null));

        // zurück auf die Vorgabe: kein gespeicherter Eintrag mehr
        $this->put('/admin/einstellungen', ['werte' => ['source_check.months' => 12]])->assertRedirect();
        $this->assertDatabaseMissing('settings', ['key' => 'source_check.months']);
    }

    public function test_ungueltige_werte_abgewiesen(): void
    {
        $this->actingAs($this->developer());
        $this->put('/admin/einstellungen', ['werte' => ['retention.audit_years' => 0]])->assertSessionHasErrors('werte');
        $this->put('/admin/einstellungen', ['werte' => ['links.pages_url' => 'http://unsicher.example']])->assertSessionHasErrors('werte');
        $this->put('/admin/einstellungen', ['werte' => ['links.github_repo' => 'kein repo']])->assertSessionHasErrors('werte');
        $this->put('/admin/einstellungen', ['werte' => ['unbekannt' => 1]])->assertSessionHasErrors('werte');
        $this->assertSame(5, Settings::int('retention.audit_years'));
    }

    public function test_ohne_recht_nicht_erreichbar(): void
    {
        $this->actingAs($this->admin());
        $this->get('/admin/einstellungen')->assertForbidden();
        $this->put('/admin/einstellungen', ['werte' => ['publish.four_eyes' => true]])->assertForbidden();
    }

    public function test_vier_augen_prinzip(): void
    {
        $glossary = app(GlossaryService::class);
        Settings::set($this->developer(), ['publish.four_eyes' => true]);
        $author = $this->admin();
        $other = $this->userWithRoles([Role::ADMIN], 'admin2@example.org');
        $term = $glossary->save(null, ['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung'], $author, true);

        $this->actingAs($author);
        $this->post("/admin/begriffe/{$term->id}/veroeffentlichen")->assertSessionHasErrors('veroeffentlichen');
        $this->assertNull($term->refresh()->published_revision_id);
        $this->post('/admin/begriffe/veroeffentlichen', ['ids' => [$term->id]])->assertSessionHas('status', fn (string $s): bool => str_contains($s, 'Vier-Augen'));
        $this->assertNull($term->refresh()->published_revision_id);

        $this->actingAs($other);
        $this->post("/admin/begriffe/{$term->id}/veroeffentlichen")->assertRedirect();
        $this->assertNotNull($term->refresh()->published_revision_id);
    }
}
