<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Verlauf auf der öffentlichen Detailseite (#68): nur veröffentlichte Revisionen mit Datum, ohne Personen. */
class PublicHistoryTest extends TestCase
{
    public function test_nur_veroeffentlichte_revisionen_ohne_personen(): void
    {
        $glossary = app(GlossaryService::class);
        $author = $this->userWithRoles(['admin'], 'autor@example.org');
        $author->forceFill(['community_name' => 'GEHEIM-AUTOR'])->save();

        $term = $glossary->save(null, ['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung'], $author, true);
        $term = $glossary->publish($term, $author);
        $term = $glossary->save($term, ['short_description' => 'Leitet die Schule.'], $author, true);
        $term = $glossary->publish($term, $author);
        $glossary->save($term, ['short_description' => 'GEHEIM-ENTWURF'], $author, true);

        $response = $this->get("/begriffe/{$term->slug}")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('begriff.verlauf', 2)
                ->where('begriff.verlauf.0.revision', 2)->where('begriff.verlauf.1.revision', 1));
        $content = (string) $response->getContent();
        $this->assertStringNotContainsString('GEHEIM-AUTOR', $content);
        $this->assertStringNotContainsString('GEHEIM-ENTWURF', $content);
    }
}
