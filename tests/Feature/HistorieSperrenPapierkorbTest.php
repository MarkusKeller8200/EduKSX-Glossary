<?php

namespace Tests\Feature;

use App\Glossary\GlossaryService;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Role;
use App\Organizations\OrganizationService;
use EduKSX\Core\Database\StrictSchema;
use EduKSX\Core\Eloquent\StaleModel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

/** Historie (data_changes), optimistisches Sperren und Papierkorb (#286). */
class HistorieSperrenPapierkorbTest extends TestCase
{
    public function test_historie_mit_akteur_und_ohne_personenwerte(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $category = Category::query()->create(['key' => 'per', 'name' => 'Personal']);
        $category->update(['name' => 'Personal und Anstellung']);
        $admin->update(['email' => 'neu@example.org']);

        $rows = DB::table('data_changes')->orderBy('id')->get()->map(fn ($r) => (array) $r);
        $cat = $rows->where('subject_type', 'categories')->values();
        $this->assertSame(['created', 'updated'], $cat->pluck('action')->all());
        $this->assertSame(['name' => ['old' => 'Personal', 'new' => 'Personal und Anstellung']], json_decode((string) $cat[1]['changes'], true));
        $this->assertSame($admin->id, $cat[1]['actor_id']);
        $this->assertSame('web', $cat[1]['source']);

        $user = $rows->where('subject_type', 'users')->where('action', 'updated')->last();
        $this->assertSame(['email' => 'changed'], json_decode((string) $user['changes'], true));
        $this->assertStringNotContainsString('neu@example.org', (string) json_encode(DB::table('data_changes')->get()));
    }

    public function test_optimistisches_sperren(): void
    {
        $category = Category::query()->create(['key' => 'per', 'name' => 'Personal']);
        $copy = Category::query()->findOrFail($category->id);
        $category->update(['name' => 'A']);
        $this->assertSame(1, $category->lock_version);

        $this->expectException(StaleModel::class);
        try {
            $copy->update(['name' => 'B']);
        } finally {
            $this->assertSame('A', Category::query()->findOrFail($category->id)->name);
        }
    }

    public function test_formular_mit_veraltetem_stand_wird_abgewiesen(): void
    {
        $this->actingAs($this->admin());
        $glossary = app(GlossaryService::class);
        $term = $glossary->save(null, ['official_abbreviation' => 'SL', 'official_name' => 'Schulleitung'], null, true);
        $stand = $term->lock_version;
        $glossary->save($term, ['short_description' => 'Inzwischen geändert'], null, true);

        $this->put("/admin/begriffe/{$term->id}", [
            'official_abbreviation' => 'SL', 'official_name' => 'Schulleitung', 'short_description' => 'Mein Stand',
            'status' => 'current', 'is_active' => true, 'lock_version' => $stand,
        ])->assertSessionHasErrors('form');
        $this->assertSame('Inzwischen geändert', $term->fresh()?->short_description);

        $category = Category::query()->create(['key' => 'per', 'name' => 'Personal']);
        $this->put("/admin/listen/kategorien/{$category->id}", ['name' => 'Neu', 'lock_version' => 5])->assertSessionHasErrors('form');
        $this->put("/admin/listen/kategorien/{$category->id}", ['name' => 'Neu', 'lock_version' => 0])->assertSessionHasNoErrors();
    }

    public function test_papierkorb_listen_wiederherstellen_und_frist(): void
    {
        $developer = $this->userWithRoles([Role::DEVELOPER], 'dev@example.org');
        $this->actingAs($this->admin());
        $category = Category::query()->create(['key' => 'per', 'name' => 'Personal']);
        $this->delete("/admin/listen/kategorien/{$category->id}")->assertRedirect();
        $this->assertSoftDeleted('categories', ['id' => $category->id]);
        $this->getJson('/api/v1/categories')->assertJsonCount(0, 'data');
        $this->get('/admin/papierkorb')->assertForbidden();

        $this->actingAs($developer)->get('/admin/papierkorb')->assertInertia(fn (Assert $p) => $p->has('eintraege', 1)->where('eintraege.0.name', 'Personal'));
        $this->post("/admin/papierkorb/kategorien/{$category->id}/wiederherstellen")->assertRedirect();
        $this->assertNotSoftDeleted('categories', ['id' => $category->id]);
        $this->assertSame(['created', 'deleted', 'restored'], DB::table('data_changes')->where('subject_id', $category->id)->orderBy('id')->pluck('action')->all());

        $category->delete();
        DB::table('categories')->update(['deleted_at' => now()->subDays(31)]);
        Artisan::call('glossary:loeschfristen');
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_organisation_im_papierkorb(): void
    {
        $organizations = app(OrganizationService::class);
        $admin = $this->userWithRoles([Role::USER], 'admin@schule.ch');
        $organization = $organizations->create($admin, 'Schule Muster');
        $this->actingAs($admin);
        $organizations->delete($admin->fresh() ?? $admin, $organization);

        $this->assertSoftDeleted('organizations', ['id' => $organization->id]);
        $this->assertNull($organizations->membershipOf($admin));
        $this->assertFalse(in_array(Role::ORG_ADMIN, $admin->fresh()?->roleKeys() ?? [], true));
        // Name bleibt reserviert, solange die Organisation wiederherstellbar ist
        $other = $this->userWithRoles([Role::USER], 'b@schule.ch');
        $this->expectExceptionMessage('auch im Papierkorb');
        try {
            $organizations->create($other, 'Schule Muster');
        } finally {
            $developer = $this->userWithRoles([Role::DEVELOPER], 'dev@example.org');
            $organizations->restore($developer, Organization::onlyTrashed()->findOrFail($organization->id));
            $this->assertNotSoftDeleted('organizations', ['id' => $organization->id]);
            $this->assertSame($organization->id, $organizations->membershipOf($admin)?->organization_id);
        }
    }

    public function test_spalten_hinzufuegen_baut_die_tabelle_nie_neu_auf(): void
    {
        DB::table('categories')->insert(['id' => '01a1b335-0000-7000-8000-0000000000f1', 'key' => 'x', 'name' => 'X']);
        StrictSchema::addColumns('categories', fn (Blueprint $t) => $t->string('extra', 10)->nullable());
        $this->assertTrue(Schema::hasColumn('categories', 'extra'));
        $this->assertSame(1, DB::table('categories')->count());

        // SQLite: ->constrained() würde die Tabelle neu aufbauen und wird abgewiesen
        $this->onlySqlite();
        $this->expectException(RuntimeException::class);
        StrictSchema::addColumns('categories', fn (Blueprint $t) => $t->foreignUuid('other_id')->nullable()->constrained('users'));
    }
}
