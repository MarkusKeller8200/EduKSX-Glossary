<?php

use App\Data\StaleModel;
use App\Http\Middleware\AuthenticateServiceToken;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectFormerHost;
use App\Http\Middleware\RequirePrivacyAcknowledgement;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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

        // Schutz-Kopfzeilen für alle Antworten (wie im Intranet, #147 dort)
        $middleware->append(SecurityHeaders::class);

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

        // Optimistisches Sperren (#286): inzwischen geändert – nichts überschrieben; Web zurück mit Meldung, sonst 409
        $exceptions->render(function (StaleModel $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['title' => 'Konflikt', 'status' => 409, 'detail' => $e->getMessage()], 409);
            }

            return back()->withErrors(['form' => $e->getMessage()])->withInput();
        });

        // API: Fehler als application/problem+json (RFC 9457, docs/KONZEPT-API.md)
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            $errors = null;
            if ($e instanceof ValidationException) {
                $status = 422;
                $detail = 'Die Anfrage enthält ungültige Werte.';
                $errors = $e->errors();
            } elseif ($e instanceof ModelNotFoundException) {
                $status = 404;
                $detail = 'Nicht gefunden.';
            } elseif ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                $detail = $e->getMessage() !== '' ? $e->getMessage() : ($status === 404 ? 'Unbekannter Endpunkt.' : null);
            } else {
                $status = 500;
                $detail = config('app.debug') ? $e->getMessage() : null;
            }
            $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

            return response()->json(array_filter([
                'type' => 'about:blank',
                'title' => SymfonyResponse::$statusTexts[$status] ?? 'Error',
                'status' => $status,
                'detail' => $detail,
                'instance' => $request->getRequestUri(),
                'errors' => $errors,
            ], fn ($v) => $v !== null), $status, ['Content-Type' => 'application/problem+json'] + $headers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        });
    })->create();
