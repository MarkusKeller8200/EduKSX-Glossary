<?php

namespace Tests\Feature;

use App\Glossary\ExcelFormat;
use App\Glossary\GlossaryImporter;
use App\Glossary\GlossaryService;
use App\Models\Role;
use App\Models\Term;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Badge «KI-unterstützt recherchiert», ersetzt durch «Von Mensch geprüft» (#479). */
final class RechercheKiTest extends TestCase
{
    private function begriff(array $extra = []): Term
    {
        $glossary = app(GlossaryService::class);
        $term = $glossary->save(null, ['official_abbreviation' => 'KI', 'official_name' => 'Künstliche Intelligenz'] + $extra, null, true);

        return $glossary->publish($term, null);
    }

    public function test_bestand_ohne_badge_und_api_mit_leeren_feldern(): void
    {
        $term = $this->begriff();
        $this->assertNull($term->research_basis);

        $api = $this->getJson("/api/v1/terms/{$term->slug}")->assertOk()->json('data');
        $this->assertArrayHasKey('research_basis', $api);
        $this->assertNull($api['research_basis']);
        $this->assertNull($api['reviewed_at']);
        $this->get("/begriffe/{$term->slug}")->assertInertia(fn (Assert $p) => $p->where('begriff.recherche', null));
    }

    public function test_ki_unterstuetzt_zeigt_badge_ueberall(): void
    {
        $term = $this->begriff(['research_basis' => 'ai_assisted']);
        $this->assertSame('ai_assisted', $term->research_basis);

        $this->getJson("/api/v1/terms/{$term->slug}")->assertOk()->assertJsonPath('data.research_basis', 'ai_assisted')->assertJsonPath('data.reviewed_at', null);
        $index = $this->getJson('/api/v1/index')->assertOk()->json('terms');
        $this->assertSame('ai_assisted', $index[0]['research_basis']);
        $this->get("/begriffe/{$term->slug}")->assertInertia(fn (Assert $p) => $p->where('begriff.recherche', 'ai_assisted'));
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('begriffe.0.recherche', 'ai_assisted'));
    }

    public function test_als_geprueft_bestaetigen_ersetzt_badge_ohne_neue_revision(): void
    {
        $term = $this->begriff(['research_basis' => 'ai_assisted']);
        $revision = $term->revision;
        $moderator = $this->userWithRoles([Role::MODERATOR], 'mod@example.org');

        $this->actingAs($moderator)->post("/admin/begriffe/{$term->id}/recherche-geprueft")->assertRedirect();

        $term->refresh();
        $this->assertSame('human_reviewed', $term->research_basis);
        $this->assertNotNull($term->reviewed_at);
        $this->assertSame($moderator->id, $term->reviewed_by_id);
        $this->assertSame($revision, $term->revision);
        $api = $this->getJson("/api/v1/terms/{$term->slug}")->assertOk()->json('data');
        $this->assertSame('human_reviewed', $api['research_basis']);
        $this->assertSame($term->reviewed_at->toDateString(), $api['reviewed_at']);
        $this->assertStringNotContainsString('mod@example.org', (string) json_encode($api));
        $this->assertStringNotContainsString($moderator->id, (string) json_encode($api));

        // Eintrag im Verlauf der Redaktion
        $this->get("/admin/begriffe/{$term->id}")->assertInertia(fn (Assert $p) => $p
            ->where('begriff.recherche', 'human_reviewed')
            ->has('pruefungen', 1));
    }

    public function test_aktion_nur_ab_moderator_und_mit_zweitem_faktor(): void
    {
        $term = $this->begriff(['research_basis' => 'ai_assisted']);

        $this->actingAs($this->userWithRoles([Role::USER], 'user@example.org'))
            ->post("/admin/begriffe/{$term->id}/recherche-geprueft")->assertForbidden();
        $this->actingAs($this->userWithRoles([Role::MODERATOR], 'ohne2fa@example.org', false))
            ->post("/admin/begriffe/{$term->id}/recherche-geprueft");
        $this->assertSame('ai_assisted', $term->refresh()->research_basis);
    }

    public function test_spaetere_aenderung_mit_ki_faellt_zurueck(): void
    {
        $term = $this->begriff(['research_basis' => 'ai_assisted']);
        $glossary = app(GlossaryService::class);
        $glossary->confirmReview($term, null);
        $glossary->save($term->refresh(), ['short_description' => 'Neu, ohne KI'], null, true);
        $this->assertSame('human_reviewed', $term->refresh()->research_basis);

        $glossary->save($term, ['short_description' => 'Neu mit KI', 'research_basis' => 'ai_assisted'], null, true);
        $this->assertSame('ai_assisted', $term->refresh()->research_basis);
    }

    public function test_excel_spalte_recherche(): void
    {
        $this->assertContains('Recherche', ExcelFormat::header());
        $this->assertContains('Recherche', ExcelFormat::PUBLIC_COLUMNS);
        $importer = app(GlossaryImporter::class);

        // neue Spalte: «KI-unterstützt» setzt das Merkmal, «geprüft» setzt nichts
        $result = $importer->run([
            ['ID' => '', 'Abkürzung offiziell' => 'LLM', 'Bezeichnung offiziell' => 'Large Language Model', 'Recherche' => 'KI-unterstützt'],
            ['ID' => '', 'Abkürzung offiziell' => 'RAG', 'Bezeichnung offiziell' => 'Retrieval-Augmented Generation', 'Recherche' => 'geprüft'],
        ], false, null, true, false);
        $this->assertSame(0, $result['fehler']);
        $this->assertSame('ai_assisted', Term::query()->where('official_abbreviation', 'LLM')->value('research_basis'));
        $this->assertNull(Term::query()->where('official_abbreviation', 'RAG')->value('research_basis'));

        // bestehende Dateien ohne die Spalte funktionieren unverändert
        $result = $importer->run([['ID' => '', 'Abkürzung offiziell' => 'GPT', 'Bezeichnung offiziell' => 'Generative Pre-trained Transformer']], false, null, true, false);
        $this->assertSame(0, $result['fehler']);
        $this->assertNull(Term::query()->where('official_abbreviation', 'GPT')->value('research_basis'));

        // unbekannter Wert → Fehler in der Vorschau
        $result = $importer->run([['ID' => '', 'Abkürzung offiziell' => 'XY', 'Bezeichnung offiziell' => 'Test', 'Recherche' => 'vielleicht']], false, null, true, true);
        $this->assertSame(1, $result['fehler']);

        $row = ExcelFormat::row(Term::query()->where('official_abbreviation', 'LLM')->firstOrFail());
        $this->assertSame('KI-unterstützt', $row['Recherche']);
    }

    public function test_redaktion_liste_mit_recherche_und_formular(): void
    {
        $term = $this->begriff(['research_basis' => 'ai_assisted']);
        $this->actingAs($this->admin());
        $this->get('/admin/begriffe')->assertInertia(fn (Assert $p) => $p->where('begriffe.0.recherche', 'ai_assisted'));
        $this->get("/admin/begriffe/{$term->id}/bearbeiten")->assertInertia(fn (Assert $p) => $p->where('werte.ki_unterstuetzt', true));
    }
}
