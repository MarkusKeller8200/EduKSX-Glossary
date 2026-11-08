<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use App\Models\Category;
use App\Models\Role;
use App\Models\Term;
use App\Models\TermPublicationEvent;
use Tests\TestCase;

/** Freigabe und Rücknahme von Revisionen (DEC-022, #95). */
class RestoreTest extends TestCase
{
    private GlossaryService $glossary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->glossary = app(GlossaryService::class);
        Category::query()->create(['key' => 'per', 'abbreviation' => 'PER', 'name' => 'Personal', 'label' => 'Personal']);
    }

    /** Revision 1 veröffentlicht («Leitet die Schule.»), Revision 2 fehlerhaft und veröffentlicht. */
    private function term(): Term
    {
        $term = $this->glossary->save(null, ['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung', 'short_description' => 'Leitet die Schule.', 'categories' => ['per']], null, true);
        $term = $this->glossary->publish($term, null);
        $term = $this->glossary->save($term, ['short_description' => 'FALSCH'], null, true);

        return $this->glossary->publish($term, null);
    }

    public function test_wiederherstellen_als_entwurf_und_veroeffentlichen(): void
    {
        $this->actingAs($this->admin());
        $term = $this->term();

        $this->post("/admin/begriffe/{$term->id}/wiederherstellen", ['revision' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $term->refresh();
        $this->assertSame(3, $term->revision);
        $this->assertSame('Leitet die Schule.', $term->short_description);
        $this->assertSame('restored', $term->revisions()->get()->last()?->change_type);
        $this->assertSame(['restored_from' => 1], $term->revisions()->get()->last()?->context);
        $this->assertTrue($term->hasUnpublishedChanges());
        $this->getJson("/api/v1/terms/{$term->id}")->assertJsonPath('data.short_description', 'FALSCH');
        // alte Revision unverändert
        $this->assertSame('FALSCH', $term->revisions()->where('revision', 2)->firstOrFail()->snapshot['short_description']);

        $this->post("/admin/begriffe/{$term->id}/wiederherstellen", ['revision' => 1, 'veroeffentlichen' => true])->assertRedirect();
        // gleicher Inhalt wie Revision 3 → keine neue Revision, aber veröffentlicht
        $events = TermPublicationEvent::query()->count();
        $this->glossary->publish($term->refresh(), null);
        $this->assertSame($events, TermPublicationEvent::query()->count());
        $this->getJson("/api/v1/terms/{$term->id}")->assertJsonPath('data.short_description', 'Leitet die Schule.')->assertJsonPath('data.revision', 3);
    }

    public function test_entwurf_verwerfen(): void
    {
        $this->actingAs($this->admin());
        $term = $this->glossary->save($this->term(), ['short_description' => 'Noch ein Entwurf.'], null, true);
        $this->assertTrue($term->hasUnpublishedChanges());

        $this->post("/admin/begriffe/{$term->id}/wiederherstellen", ['revision' => 2])->assertRedirect();
        $term->refresh();
        $this->assertSame(4, $term->revision);
        $this->assertFalse($term->hasUnpublishedChanges());

        // Freigeben ändert nichts (kein Ereignis)
        $events = TermPublicationEvent::query()->count();
        $this->glossary->publish($term, null);
        $this->assertSame($events, TermPublicationEvent::query()->count());
        $this->assertSame(2, $term->refresh()->publishedRevision?->revision);
    }

    public function test_rechte_und_fehlende_listen(): void
    {
        $term = $this->term();
        $this->actingAs($this->userWithRoles([Role::MODERATOR], 'mod@example.org'));
        $this->post("/admin/begriffe/{$term->id}/wiederherstellen", ['revision' => 1, 'veroeffentlichen' => true])->assertSessionHasErrors('form');
        $this->assertSame(2, $term->refresh()->revision);

        // offizielle Felder geändert → ohne edit-official nicht wiederherstellbar
        $this->glossary->save($term, ['official_name' => 'Schulleitung Primar'], null, true);
        $this->post("/admin/begriffe/{$term->id}/wiederherstellen", ['revision' => 1])->assertSessionHasErrors('form');

        // gelöschte Kategorie
        $this->actingAs($this->admin());
        $term->categories()->detach();
        Category::query()->where('key', 'per')->delete();
        $this->post("/admin/begriffe/{$term->id}/wiederherstellen", ['revision' => 1])->assertSessionHasErrors('form');
        $this->post("/admin/begriffe/{$term->id}/wiederherstellen", ['revision' => 99])->assertSessionHasErrors('form');
    }
}
