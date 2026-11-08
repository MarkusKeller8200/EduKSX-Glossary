<?php

namespace App\Providers;

use App\Access\Permission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\Provider as MicrosoftProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureDefaults();

        // Rechte aus Rollen (#36, DEC-017): je Recht ein Gate; Prüfung immer serverseitig. Privilegierte Rechte nur
        // mit bestätigtem zweitem Faktor und aktivem Konto (#37, KONZEPT-BENUTZER 5.3); Grundrechte ohne (#110)
        foreach (Permission::keys() as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->isActive()
                && $user->hasPermission($permission)
                && (Permission::isBasic($permission) || $user->hasTwoFactor()));
        }
        // Zugang zur Administration: irgendein Recht ausser den Grundrechten (Einrichtung des zweiten Faktors
        // ist dort möglich; die einzelnen Aktionen verlangen ihn)
        Gate::define('administer', fn (User $user): bool => $user->isActive() && $user->needsTwoFactor());

        // Anmeldung mit Microsoft (#45): Provider von SocialiteProviders bei Socialite anmelden
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('microsoft', MicrosoftProvider::class);
        });

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
