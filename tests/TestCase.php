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

    protected function admin(): User
    {
        return User::query()->create([
            'name' => 'Admin Test',
            'email' => 'admin@example.org',
            'password' => 'ein-langes-Test-Passwort-1',
            'is_admin' => true,
        ]);
    }
}
