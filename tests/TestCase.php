<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Backend-Tests unabhängig vom Frontend-Build (kein Vite-Manifest nötig).
        $this->withoutVite();
    }

    /** Geheimer Schlüssel des Test-Admins (zweiter Faktor, #11). */
    protected const TOTP_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    /** Admin mit eingerichtetem zweitem Faktor (Pflicht in der Administration). */
    protected function admin(): User
    {
        $user = User::query()->create([
            'name' => 'Admin Test',
            'email' => 'admin@example.org',
            'password' => 'ein-langes-Test-Passwort-1',
            'is_admin' => true,
        ]);
        $user->forceFill(['two_factor_secret' => self::TOTP_SECRET, 'two_factor_confirmed_at' => now()])->save();

        return $user;
    }
}
