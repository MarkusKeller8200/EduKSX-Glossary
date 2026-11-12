<?php

namespace Tests\Feature;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Rollen-Schlüssel englisch (#288): Umbenennung behält Zuordnungen und Rechte. */
class RollenEnglischTest extends TestCase
{
    use RefreshDatabase;

    public function test_alte_schluessel_werden_umbenannt_zuordnung_bleibt(): void
    {
        $user = $this->userWithRoles([Role::USER], 'anna@example.org');
        $role = Role::byKey(Role::USER);
        $rechte = DB::table('role_permissions')->where('role_id', $role->id)->count();
        DB::table('roles')->where('key', 'user')->update(['key' => 'benutzer']);
        DB::table('roles')->where('key', 'guest')->update(['key' => 'gast']);

        (require database_path('migrations/2026_11_13_000000_rollen_englisch.php'))->up();

        $this->assertSame(['guest', 'user'], DB::table('roles')->whereIn('key', ['guest', 'user', 'gast', 'benutzer'])->orderBy('key')->pluck('key')->all());
        $this->assertTrue($user->fresh()?->roles()->where('key', Role::USER)->exists());
        $this->assertSame($rechte, DB::table('role_permissions')->where('role_id', $role->id)->count());
        $this->assertSame('Benutzer', DB::table('roles')->where('key', 'user')->value('name'));
    }
}
