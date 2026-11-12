<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Source;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Beschreibung und Link der Labels beim Antippen (#258). */
class LabelInfoTest extends TestCase
{
    public function test_labels_mit_beschreibung_und_link_ohne_notiz(): void
    {
        Category::query()->create(['key' => 'per', 'abbreviation' => 'PER', 'name' => 'Personal', 'label' => 'Personal',
            'description' => 'Anstellung und Personalführung', 'note' => 'intern']);
        Category::query()->create(['key' => 'leer', 'name' => 'Leer', 'label' => 'Leer']);
        Source::query()->create(['key' => 'vsa', 'abbreviation' => 'VSA', 'name' => 'Volksschulamt', 'label' => 'VSA',
            'url' => 'https://www.zh.ch/vsa']);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('labelInfo.kategorien.Personal.beschreibung', 'Anstellung und Personalführung')
            ->where('labelInfo.kategorien.Personal.name', '')
            ->missing('labelInfo.kategorien.Leer')
            ->missing('labelInfo.kategorien.Personal.note')
            ->where('labelInfo.herkunft.VSA.name', 'Volksschulamt')
            ->where('labelInfo.herkunft.VSA.url', 'https://www.zh.ch/vsa'));
    }
}
