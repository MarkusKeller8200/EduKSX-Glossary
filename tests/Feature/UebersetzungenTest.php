<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use App\Models\Category;
use App\Models\Term;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Übersetzungen (X1, #282): Teil der Revision, öffentlich erst mit der Veröffentlichung; API mit ?lang= und
 * deutschem Ersatz für fehlende Felder.
 */
class UebersetzungenTest extends TestCase
{
    private GlossaryService $glossary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->glossary = app(GlossaryService::class);
        $category = Category::query()->create(['key' => 'per', 'abbreviation' => 'PER', 'name' => 'Personal', 'label' => 'Personal']);
        $category->translations()->create(['locale' => 'fr', 'name' => 'Personnel', 'label' => 'Personnel']);
    }

    private function term(): Term
    {
        $term = $this->glossary->save(null, [
            'official_abbreviation' => 'KLP', 'official_name' => 'Klassenlehrperson', 'short_description' => 'Führt die Klasse.',
            'categories' => ['per'],
            'translations' => ['fr' => ['abbreviation' => 'MCL', 'name' => 'Maître de classe'], 'it' => ['name' => '  ']],
        ], null, true);

        return $this->glossary->publish($term, null);
    }

    public function test_uebersetzung_in_revision_und_api(): void
    {
        $term = $this->term();
        $this->assertSame(['fr'], array_keys((array) $term->publishedRevision?->snapshot['translations']));   // leeres it entfällt

        $this->getJson("/api/v1/terms/{$term->slug}")->assertOk()
            ->assertJsonPath('data.name', 'Klassenlehrperson')
            ->assertJsonPath('data.translations.fr.name', 'Maître de classe')
            ->assertJsonMissingPath('data.language');

        $this->getJson("/api/v1/terms/{$term->slug}?lang=fr")->assertOk()
            ->assertJsonPath('data.abbreviation', 'MCL')
            ->assertJsonPath('data.name', 'Maître de classe')
            ->assertJsonPath('data.display_label', 'MCL')
            ->assertJsonPath('data.official_name', 'Klassenlehrperson')
            ->assertJsonPath('data.short_description', 'Führt die Klasse.')
            ->assertJsonPath('data.language.requested', 'fr')
            ->assertJsonPath('data.language.fields.name', 'fr')
            ->assertJsonPath('data.language.fields.short_description', 'de')
            ->assertJsonPath('data.categories.0.name', 'Personnel');

        $this->getJson('/api/v1/terms?lang=fr&q=maître')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/terms?lang=it')->assertOk()->assertJsonPath('data.0.name', 'Klassenlehrperson')
            ->assertJsonPath('data.0.language.fields.name', 'de');
        $this->getJson('/api/v1/categories?lang=fr')->assertOk()->assertJsonPath('data.0.label', 'Personnel');
        $this->getJson('/api/v1/terms?lang=xx')->assertStatus(400);
    }

    public function test_entwurf_erst_nach_veroeffentlichung_sichtbar(): void
    {
        $term = $this->term();
        $term = $this->glossary->save($term, ['translations' => ['fr' => ['name' => 'Maîtresse de classe']]], null, true);

        $this->getJson("/api/v1/terms/{$term->slug}?lang=fr")->assertJsonPath('data.name', 'Maître de classe');
        $this->assertSame(['translations' => true], array_map(fn (): bool => true, array_intersect_key((array) $term->latestRevision?->change_set, ['translations' => 1])));

        $this->glossary->publish($term, null);
        $this->getJson("/api/v1/terms/{$term->slug}?lang=fr")->assertJsonPath('data.name', 'Maîtresse de classe')
            ->assertJsonPath('data.abbreviation', 'KLP');   // abbreviation entfernt → deutsch
    }

    public function test_wiederherstellen_bringt_uebersetzung_zurueck(): void
    {
        $term = $this->term();
        $term = $this->glossary->save($term, ['translations' => []], null, true);
        $this->assertSame([], $term->translations()->get()->all());

        $term = $this->glossary->restore($term, 1, null, true);
        $this->assertSame('Maître de classe', $term->translations()->where('locale', 'fr')->value('name'));
    }

    public function test_nur_uebersetzungssprachen(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->glossary->save(null, ['official_name' => 'X', 'translations' => ['de' => ['name' => 'X']]], null, true);
    }

    public function test_redaktion_erfasst_uebersetzung_mit_journal(): void
    {
        $this->actingAs($this->admin());
        $term = $this->term();
        $form = $this->get("/admin/begriffe/{$term->id}/bearbeiten")->assertOk();
        $form->assertInertia(fn (Assert $p) => $p->where('werte.translations.fr.name', 'Maître de classe')->where('werte.translations.en.name', ''));

        $this->put("/admin/begriffe/{$term->id}", [
            'official_abbreviation' => 'KLP', 'official_name' => 'Klassenlehrperson', 'short_description' => 'Führt die Klasse.',
            'categories' => ['per'], 'lifecycle_status' => 'current', 'is_active' => true,
            'translations' => ['fr' => ['abbreviation' => 'MCL', 'name' => 'Maître de classe'], 'en' => ['name' => 'Class teacher']],
        ])->assertRedirect("/admin/begriffe/{$term->id}");

        $this->get("/admin/begriffe/{$term->id}")->assertInertia(fn (Assert $p) => $p
            ->where('begriff.translations.en.name', 'Class teacher')
            ->where('verlauf.0.aenderungen', fn ($rows): bool => collect($rows)->contains(fn ($r): bool => $r['feld'] === 'Bezeichnung (EN)' && $r['neu'] === 'Class teacher')));
    }

    public function test_listen_uebersetzen(): void
    {
        $this->actingAs($this->admin());
        $id = (string) Category::query()->value('id');
        $this->put("/admin/listen/kategorien/{$id}", [
            'name' => 'Personal', 'label' => 'Personal',
            'translations' => ['fr' => ['name' => '', 'label' => '', 'description' => ''], 'it' => ['name' => 'Personale', 'label' => 'Personale']],
        ])->assertRedirect();

        $this->assertSame(['it'], Category::query()->firstOrFail()->translations()->pluck('locale')->all());   // fr geleert → entfernt
        $this->getJson('/api/v1/categories?lang=it')->assertJsonPath('data.0.name', 'Personale');
        $this->getJson('/api/v1/categories?lang=fr')->assertJsonPath('data.0.name', 'Personal');
        $this->get('/admin/listen')->assertInertia(fn (Assert $p) => $p->where('listen.kategorien.0.translations.it.label', 'Personale'));
    }
}
