<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vollständige Content Security Policy mit Nonce und Trusted Types (#397, Pentest 15.11.2026). Ergänzt die
 * Kopfzeilen aus eduksx/core (frame-ancestors, frame-src, object-src, base-uri), die unverändert bleiben.
 *
 * Stufe 1 (Vorgabe): `Content-Security-Policy-Report-Only` – Verstösse landen über /csp-bericht im Protokoll, nichts
 * wird blockiert. Stufe 2 mit `GLOSSARY_CSP_ENFORCE=true`: die Richtlinie wird zusätzlich erzwungen (Trusted Types
 * bleiben bis zur eigenen Freigabe Report-Only, weil Mermaid und andere Stellen innerHTML nutzen).
 */
class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $response = $next($request);
        if (! str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }
        $policy = self::policy($nonce);
        $response->headers->set('Content-Security-Policy-Report-Only', implode('; ', [...$policy, "require-trusted-types-for 'script'", 'report-uri /csp-bericht']));
        $response->headers->set(self::PENDING, implode('; ', $policy));

        return $response;
    }

    /** Interne Kopfzeile: Richtlinie zum Erzwingen, bis die Kopfzeilen aus eduksx/core gesetzt sind. */
    public const PENDING = 'X-Glossary-Csp';

    /**
     * Nach allen Middlewares (Ereignis RequestHandled): mit GLOSSARY_CSP_ENFORCE=true die Richtlinie an die von
     * eduksx/core anhängen. Die interne Kopfzeile verlässt den Server nie.
     */
    public static function finalize(Response $response): void
    {
        $pending = $response->headers->get(self::PENDING);
        $response->headers->remove(self::PENDING);
        if ($pending === null || ! (bool) config('glossary.csp_enforce', false)) {
            return;
        }
        $core = (string) $response->headers->get('Content-Security-Policy');
        $response->headers->set('Content-Security-Policy', implode('; ', array_filter([$core, $pending])));
    }

    /** @return list<string> */
    public static function policy(string $nonce): array
    {
        $turnstile = 'https://challenges.cloudflare.com';

        return [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' {$turnstile}",
            "style-src 'self' 'nonce-{$nonce}'",
            // React setzt Stile als Attribut (style="…"); das erlaubt keine Scripts
            "style-src-attr 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self' {$turnstile}",
            "worker-src 'self'",
            "manifest-src 'self'",
            "form-action 'self' https://login.microsoftonline.com",
        ];
    }
}
