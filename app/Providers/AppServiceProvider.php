<?php

namespace App\Providers;

use App\Access\IdentEvents;
use App\Access\Permission;
use App\Glossary\PublicCache;
use App\Models\User;
use App\Organizations\OrganizationService;
use App\Support\MailSettings;
use App\Support\Turnstile;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\Provider as MicrosoftProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // je Anfrage eine Instanz: Schlüssel des Zwischenspeichers nur einmal berechnen (#137)
        $this->app->scoped(PublicCache::class);

        // Mail-Versand aus der Administration (#219): erst übernehmen, wenn Mail gebraucht wird
        $this->app->afterResolving('mail.manager', fn () => MailSettings::apply());
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

        // Ereignisse von EduKSX-Ident (#187): Empfang und Signatur über eduksx/core, Wirkung hier
        IdentEvents::register();

        // Anmeldung mit Microsoft (#45): Provider von SocialiteProviders bei Socialite anmelden
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('microsoft', MicrosoftProvider::class);
        });

        // Offene Einladungen in eine Organisation bei der Anmeldung melden (#148); annehmen nur aktiv
        Event::listen(function (Login $event): void {
            if (! $event->user instanceof User || ! app()->bound('session')) {
                return;
            }
            $names = app(OrganizationService::class)->pendingInvitationNames($event->user);
            if ($names !== []) {
                session()->flash('status', 'Einladung in die Organisation «'.implode('», «', $names).'» – unter «Mein Konto → Meine Organisation» annehmen oder ablehnen.');
            }
        });

        // Abruflimit «api» (120/Min. je Token bzw. Adresse) kommt aus eduksx/core (#334).
        // Spam-Schutz (Turnstile, #41) bettet sich als iframe ein: frame-src für die Sicherheitskopfzeilen von Core
        if (Turnstile::enabled()) {
            config(['eduksx.security_headers.frame_src' => ["'self'", 'https://challenges.cloudflare.com']]);
        }
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
