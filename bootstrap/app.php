<?php

use App\Http\Middleware\AuthenticateServiceToken;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectFormerHost;
use App\Http\Middleware\RequirePrivacyAcknowledgement;
use EduKSX\Core\Eloquent\StaleModel;
use EduKSX\Core\Http\Problem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance']);

        // Hinter Traefik (Reverse Proxy): HTTPS und Client-IP aus den Proxy-Headern übernehmen.
        // Der Container ist nur über Traefik erreichbar (kein veröffentlichter Port).
        $middleware->trustProxies(at: '*');

        // Schutz-Kopfzeilen und X-Request-Id für alle Antworten kommen aus eduksx/core (#334)

        // Frühere Adresse weiterleiten (#304), vor allem anderen
        $middleware->prepend(RedirectFormerHost::class);

        $middleware->web(append: [
            EnsureAccountActive::class,
            RequirePrivacyAcknowledgement::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Schreibende API mit Token eines Servicekontos (#47)
        $middleware->alias(['service.token' => AuthenticateServiceToken::class]);

        // Nicht angemeldet → Anmeldeseite
        $middleware->redirectGuestsTo('/anmelden');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Abgelaufener oder veränderter Bestätigungslink (#41): freundlich zur Anmeldung
        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            if ($request->routeIs('register.verify')) {
                return redirect('/anmelden')->withErrors(['email' => 'Der Bestätigungslink ist abgelaufen. Bitte anmelden – der Link wird dann neu gesendet.']);
            }

            return null;
        });

        // Optimistisches Sperren (#286): inzwischen geändert – nichts überschrieben; Web zurück mit Meldung,
        // API und JSON als 409 «conflict» über eduksx/core (#334)
        $exceptions->render(function (StaleModel $e, Request $request) {
            if (Problem::handles($request) || $request->expectsJson()) {
                return $request->expectsJson() && ! Problem::handles($request) ? Problem::fromThrowable($e, $request) : null;
            }

            return back()->withErrors(['form' => $e->getMessage()])->withInput();
        });

        // API: Fehler als application/problem+json mit code und request_id aus eduksx/core (API-Standard A6, #334)
        Problem::register($exceptions);
    })->create();
