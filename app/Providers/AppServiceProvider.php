<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureDefaults();

        // Rechte: Daten ändern nur mit Anmeldung (DEC-014). Feinere Rollen (Redaktion) folgen mit der Redaktion.
        Gate::define('administer', fn (User $user): bool => $user->is_admin === true);
        // Offizielle Abkürzung, Bezeichnung und Aliase ändern (wie im Intranet: Vorgabe Administration)
        Gate::define('edit-official', fn (User $user): bool => $user->is_admin === true);

        // Öffentliche Lese-API: 120 Abrufe pro Minute und Adresse
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by((string) $request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Passwörter der Admin-Konten: mindestens 12 Zeichen (KONZEPT-STACK)
        Password::defaults(fn (): Password => app()->isProduction()
            ? Password::min(12)->letters()->mixedCase()->numbers()->symbols()->uncompromised()
            : Password::min(12),
        );
    }
}
