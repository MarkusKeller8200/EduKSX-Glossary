<?php

namespace Tests\Feature;

use App\Access\Audit;
use App\Models\Organization;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Plattform-IDs für Organisationen und Konten (#311, DB-Standard E3). */
final class PlattformIdTest extends TestCase
{
    private const ID = '0193a1b2-0000-7000-8000-00000000a001';

    public function test_spalten_eindeutig_und_zuordnung_per_befehl(): void
    {
        $this->assertTrue(Schema::hasColumn('organizations', 'platform_id'));
        $this->assertTrue(Schema::hasColumn('users', 'platform_id'));

        $user = $this->userWithRoles([Role::USER], 'lea@schule.example');
        $before = DB::table('users')->count();
        $this->artisan('glossary:plattform-id', ['art' => 'konto', 'glossary_id' => $user->id, 'platform_id' => self::ID])->assertSuccessful();
        $this->assertSame(self::ID, $user->fresh()?->platform_id);
        $this->assertSame($before, DB::table('users')->count());

        // zweimal dieselbe ID geht nicht
        $other = $this->userWithRoles([Role::USER], 'remo@schule.example');
        $this->artisan('glossary:plattform-id', ['art' => 'konto', 'glossary_id' => $other->id, 'platform_id' => self::ID])->assertFailed();
        $this->artisan('glossary:plattform-id', ['art' => 'konto', 'glossary_id' => $other->id, 'platform_id' => 'keine-uuid'])->assertExitCode(2);

        $organization = Organization::query()->create(['name' => 'Schule Muster', 'created_by_id' => $user->id]);
        $this->artisan('glossary:plattform-id', ['art' => 'organisation', 'glossary_id' => $organization->id, 'platform_id' => self::ID])->assertSuccessful();
        $this->artisan('glossary:plattform-id', ['art' => 'organisation', 'glossary_id' => $organization->id, '--entfernen' => true])->assertSuccessful();
        $this->assertNull($organization->fresh()?->platform_id);
    }

    /** Ereignis-IDs (#332): neue Einträge erhalten eine UUID v7, die fortlaufende Zahl bleibt. */
    public function test_ereignis_id_in_protokoll_und_feed(): void
    {
        $user = $this->userWithRoles([Role::USER], 'lea@schule.example');
        Audit::log(null, $user, 'account.blocked');

        $row = DB::table('user_audit')->orderByDesc('id')->first();
        $this->assertTrue(Str::isUuid((string) $row?->event_id));
        foreach (['term_publication_events', 'proposal_events', 'user_audit', 'service_account_audit'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'event_id'), $table);
        }
    }
}
