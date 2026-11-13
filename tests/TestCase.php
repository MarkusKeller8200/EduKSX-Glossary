<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Backend-Tests unabhängig vom Frontend-Build (kein Vite-Manifest nötig).
        $this->withoutVite();

        // Kenntnisnahme der Datenschutzerklärung nur in den Tests dazu verlangen (#42, PrivacyTest)
        config(['glossary.privacy_version' => '']);

        // PostgreSQL (#287): Zähler laufen über zurückgerollte Transaktionen hinweg weiter – je Test von vorn,
        // damit Tests mit fortlaufenden Nummern (Datenstand, Cursor) gleich laufen wie unter SQLite
        if (DB::getDriverName() === 'pgsql') {
            foreach (DB::select("select sequencename from pg_sequences where schemaname = 'public'") as $sequence) {
                DB::statement("select setval('\"".((array) $sequence)['sequencename']."\"', 1, false)");
            }
        }
    }

    /** Test nur unter SQLite (Format der Datei, STRICT-Tabellen, Übergang aus 0.11). */
    protected function onlySqlite(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('nur unter SQLite');
        }
    }

    /** Geheimer Schlüssel des Test-Admins (zweiter Faktor, #11). */
    protected const TOTP_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    /** Admin mit eingerichtetem zweitem Faktor (Pflicht in der Administration). */
    protected function admin(): User
    {
        return $this->userWithRoles([Role::ADMIN], 'admin@example.org');
    }

    /**
     * Konto mit Rollen (Schlüssel) und – ausser abgeschaltet – bestätigtem zweitem Faktor (#36).
     *
     * @param  list<string>  $roles
     */
    protected function userWithRoles(array $roles, string $email, bool $twoFactor = true): User
    {
        $user = User::query()->create([
            'name' => 'Test '.implode('+', $roles),
            'email' => $email,
            'password' => 'ein-langes-Test-Passwort-1',
        ]);
        if ($twoFactor) {
            $user->forceFill(['two_factor_secret' => self::TOTP_SECRET, 'two_factor_confirmed_at' => now()])->save();
        }
        foreach ($roles as $key) {
            $user->roles()->attach(Role::byKey($key)->id);
        }

        return $user;
    }
}
