<?php

namespace App\Http\Middleware;

use App\Support\Turnstile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Schutz-Kopfzeilen für alle Antworten. Übernommen aus sek-intranet app/Http/Middleware/SecurityHeaders.php
 * (#147 dort), ohne die Einbindungen von Fachanwendungen: Das Glossary bettet nichts ein.
 *
 * - Nur das Glossary selbst darf sich einbetten (X-Frame-Options, CSP frame-ancestors) – gegen Clickjacking.
 * - Keine Plugins/Objekte, kein fremdes base, kein MIME-Raten, gekürzter Referrer, Geräte-Rechte aus, HSTS bei HTTPS.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        // Spam-Schutz (Turnstile) bettet sich nur auf der Registrierung als iframe ein (#41)
        $frames = $request->routeIs('register') && Turnstile::enabled() ? "'self' https://challenges.cloudflare.com" : "'self'";
        $headers->set('Content-Security-Policy', implode('; ', [
            "frame-ancestors 'self'",
            "frame-src {$frames}",
            "object-src 'none'",
            "base-uri 'self'",
        ]));
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
