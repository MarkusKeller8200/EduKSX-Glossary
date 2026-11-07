<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use App\Glossary\OfficialFieldLocked;
use App\Models\Category;
use App\Models\PublicationRelease;
use App\Models\Source;
use App\Models\Term;
use App\Models\TermAlias;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

/** Datenmodell (#12): Revisionen, Veröffentlichung, offizielle Felder, Mehrdeutigkeit. */
class GlossaryModelTest extends TestCase
{
    private GlossaryService $glossary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->glossary = app(GlossaryService::class);
        Category::query()->create(['key' => 'per', 'abbreviation' => 'PER', 'name' => 'Personal', 'label' => 'Personal']);
        Category::query()->create(['key' => 'sop', 'abbreviation' => 'SOP', 'name' => 'Sonderpädagogik', 'label' => 'Sonderpädagogik']);
        Source::query()->create(['key' => 'vsa', 'abbreviation' => 'VSA', 'name' => 'Volksschulamt des Kantons Zürich', 'label' => 'VSA']);
    }

    /** @param  array<string, mixed>  $extra */
    private function draft(array $extra = []): Term
    {
        return $this->glossary->save(null, [
            'official_abbreviation' => 'SHP',
            'official_name' => 'Schulische Heilpädagogin / Schulischer Heilpädagoge',
            'short_description' => 'Lehrperson für integrative Förderung.',
            'categories' => 'Personal; SOP',
            'sources' => ['vsa'],
            'official_aliases' => ['ShP'],
            ...$extra,
        ], null, true, 'Test');
    }

    public function test_neuer_begriff_ist_entwurf_mit_erster_revision(): void
    {
        $term = $this->draft();

        $this->assertSame(1, $term->revision);
        $this->assertSame('created', $term->revisions()->firstOrFail()->change_type);
        $this->assertNull($term->published_revision_id);
        $this->assertSame(0, Term::query()->published()->count());
        $this->assertSame('schulische-heilpaedagogin-schulischer-heilpaedagoge', $term->slug);
        $this->assertSame(['per', 'sop'], $term->revisions()->firstOrFail()->snapshot['categories']);
    }

    public function test_veroeffentlichen_erzeugt_datenstand(): void
    {
        $term = $this->glossary->publish($this->draft(), $this->admin());

        $this->assertSame(1, Term::query()->published()->count());
        $this->assertNotNull($term->publishedRevision?->published_at);
        $release = PublicationRelease::query()->latest('id')->firstOrFail();
        $this->assertSame('published', $release->action);
        $this->assertSame(1, $release->term_count);
        $this->assertSame(64, strlen($release->checksum));
    }

    public function test_aenderung_nach_freigabe_bleibt_entwurf(): void
    {
        $term = $this->glossary->publish($this->draft(), $this->admin());
        $term = $this->glossary->save($term, ['short_description' => 'Neu formuliert.'], null, true);

        $this->assertSame(2, $term->revision);
        $this->assertTrue($term->hasUnpublishedChanges());
        $this->assertSame('Lehrperson für integrative Förderung.', $term->publishedRevision?->snapshot['short_description']);

        $term = $this->glossary->publish($term, null);
        $this->assertSame('Neu formuliert.', $term->publishedRevision?->snapshot['short_description']);
        $this->assertSame(2, PublicationRelease::query()->count());
    }

    public function test_ohne_aenderung_keine_neue_revision(): void
    {
        $term = $this->draft();
        $term = $this->glossary->save($term, ['short_description' => 'Lehrperson für integrative Förderung.'], null, true);

        $this->assertSame(1, $term->revision);
    }

    public function test_offizielle_felder_ohne_recht_gesperrt(): void
    {
        $term = $this->draft();

        $this->expectException(OfficialFieldLocked::class);
        $this->glossary->save($term, ['official_name' => 'Etwas anderes'], null, false);
    }

    public function test_leeres_offizielles_feld_darf_ohne_recht_gesetzt_werden(): void
    {
        $term = $this->glossary->save(null, ['official_abbreviation' => 'SL'], null, true);
        $term = $this->glossary->save($term, ['official_name' => 'Schulleitung'], null, false);

        $this->assertSame('Schulleitung', $term->official_name);
    }

    public function test_mehrdeutige_abkuerzungen_erlaubt(): void
    {
        $this->draft();
        $this->draft(['official_name' => 'Sonderpädagogische Heilpädagogik (Beispiel)']);

        $this->assertSame(2, Term::query()->where('official_abbreviation', 'SHP')->count());
        $this->assertSame(2, TermAlias::query()->where('alias', 'ShP')->count());
    }

    public function test_id_aus_dem_intranet_bleibt_erhalten(): void
    {
        $id = '0195f6a2-7c1e-7a3b-9b2d-3f4e5a6b7c8d';
        $term = $this->draft(['id' => $id]);

        $this->assertSame($id, $term->id);
    }

    public function test_unbekannte_kategorie_ist_ein_fehler(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->draft(['categories' => ['gibt-es-nicht']]);
    }

    public function test_status_als_text_wie_im_intranet(): void
    {
        $term = $this->draft(['lifecycle_status' => 'zu prüfen']);

        $this->assertSame('zu_pruefen', $term->lifecycle_status);
    }

    public function test_zurueckziehen(): void
    {
        $term = $this->glossary->publish($this->draft(), null);
        $this->glossary->unpublish($term, null);

        $this->assertSame(0, Term::query()->published()->count());
        $this->assertSame('unpublished', PublicationRelease::query()->latest('id')->firstOrFail()->action);
    }

    public function test_revisionen_sind_unveraenderlich(): void
    {
        $revision = $this->draft()->revisions()->firstOrFail();

        $this->expectException(LogicException::class);
        $revision->update(['change_type' => 'manipuliert']);
    }
}
