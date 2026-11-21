<?php

namespace App\Providers;

use App\Access\IdentEvents;
use App\Access\Permission;
use App\Database\PostgresSchema;
use App\Glossary\PublicCache;
use App\Http\Middleware\ContentSecurityPolicy;
use App\Metrics\Metrics;
use App\Models\User;
use App\Organizations\OrganizationService;
use App\Support\MailLog;
use App\Support\MailSettings;
use App\Support\Turnstile;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobFailed;
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
        // je Anfrage eine Instanz: Schlüssel des Zwischenspeichers nur einmal berechnen (#137)
        $this->app->scoped(PublicCache::class);

        // Mail-Versand aus der Administration (#219): erst übernehmen, wenn Mail gebraucht wird
        $this->app->afterResolving('mail.manager', fn () => MailSettings::apply());
    }

    public function boot(): void
    {
        $this->configureDefaults();

        // MCP-Server (#261, DEC-029 O5): 60 Anfragen pro Minute und IP-Adresse
        RateLimiter::for('mcp', fn (Request $request) => Limit::perMinute(60)->by('mcp:'.$request->ip()));

        // CSP (#397): erst nach den Kopfzeilen von eduksx/core erzwingen bzw. die interne Kopfzeile entfernen
        Event::listen(RequestHandled::class, fn (RequestHandled $e) => ContentSecurityPolicy::finalize($e->response));

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

        // PostgreSQL: Schema «glossary» anlegen, falls es fehlt (#333, DB-Standard E2) – einmal je Verbindung
        // Verbindungen, die schon vor diesem Punkt aufgebaut wurden (z. B. durch einen anderen Provider), ebenso
        Event::listen(function (ConnectionEstablished $event): void {
            PostgresSchema::ensureFor($event->connection);
        });
        foreach (DB::getConnections() as $connection) {
            PostgresSchema::ensureFor($connection);
        }

        // Anmeldung mit Microsoft (#45): Provider von SocialiteProviders bei Socialite anmelden
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('microsoft', MicrosoftProvider::class);
        });

        // Mail-Protokoll (#463): versendet bzw. nach allen Versuchen fehlgeschlagen, ohne Inhalt
        Event::listen(fn (MessageSent $event) => MailLog::sent($event));
        Event::listen(fn (JobFailed $event) => MailLog::failed($event));

        // Anmeldungen für die Metriken (#192)
        Event::listen(fn (Login $event) => Metrics::login('ok'));
        Event::listen(fn (Failed $event) => Metrics::login('failed'));

        // Offene Einladungen in eine Organisation bei der Anmeldung melden (#148); annehmen nur aktiv
        Event::listen(function (Login $event): void {
            if (! $event->user instanceof User || ! app()->bound('session')) {
                return;
            }
            // über die Domain aufgenommen (#462): einmal darauf hinweisen
            $domain = app(OrganizationService::class)->pendingDomainNotice($event->user);
            if ($domain !== null) {
                session()->flash('status', "Sie wurden über die Domain Ihrer E-Mail-Adresse in die Organisation «{$domain}» aufgenommen. Austreten unter «Mein Konto → Meine Organisation».");

                return;
            }
            // Einladung älter als das Konto, Adresse bestätigt (#461): auch nachträglich bei der Anmeldung annehmen
            $joined = app(OrganizationService::class)->joinOnVerifiedEmail($event->user, 'Anmeldung');
            if ($joined !== null) {
                session()->flash('status', "Einladung angenommen: Sie sind jetzt Mitglied von «{$joined->name}». Austreten unter «Mein Konto → Meine Organisation».");

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
