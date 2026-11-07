<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use App\Models\Category;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Farbkonzept (#77): Farbe je Kategorie/Herkunft wählen, Anzeige über die geteilte Angabe «farben». */
class ColorsTest extends TestCase
{
    public function test_farbe_in_listen_waehlen_und_anzeigen(): void
    {
        $this->actingAs($this->admin());
        $category = Category::query()->create(['key' => 'per', 'abbreviation' => 'PER', 'name' => 'Personal', 'label' => 'Personal']);

        $this->put("/admin/listen/kategorien/{$category->id}", ['name' => 'Personal', 'label' => 'Personal', 'color' => 'teal'])
            ->assertRedirect();
        $this->assertSame('teal', $category->refresh()->color);

        $this->put("/admin/listen/kategorien/{$category->id}", ['name' => 'Personal', 'color' => 'pink'])
            ->assertSessionHasErrors('color');
        $this->assertSame('teal', $category->refresh()->color);

        $glossary = app(GlossaryService::class);
        $glossary->publish($glossary->save(null, ['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung', 'categories' => ['per']], null, true), null);

        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('farben.kategorien.Personal', 'teal'));
        $this->get('/admin/listen')->assertInertia(fn (Assert $p) => $p->where('listen.kategorien.0.color', 'teal')->has('farbwahl', 11));
    }
}
