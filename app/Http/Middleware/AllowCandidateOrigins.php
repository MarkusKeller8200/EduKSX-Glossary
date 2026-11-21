<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CORS für «Begriff melden» (#480, DEC-042 M3, M4): Browser-Erweiterungen (mit Sitzung) und die in der Administration
 * eingetragenen Webseiten mit Einbindung (ohne Sitzung). Andere Webseiten blockiert der Browser; nur JSON erzwingt die
 * Vorabprüfung. Aufrufe ohne Origin (keine Browser) begrenzen Abruflimit, Sperrliste und Killswitch.
 */
class AllowCandidateOrigins
{
    private const EXTENSION = '#^(chrome|moz|safari-web)-extension://[A-Za-z0-9-]{1,64}$#';

    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');
        $kind = self::kind($origin, $request);
        if ($origin !== null && $kind === null) {
            return response()->json(['detail' => 'Diese Webseite darf nicht melden.'], 403);
        }
        if ($request->isMethod('OPTIONS')) {
            $preflight = $this->headers(response('', 204), $origin, $kind);
            $preflight->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            $preflight->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept');
            $preflight->headers->set('Access-Control-Max-Age', '600');

            return $preflight;
        }
        if ($request->isMethod('POST') && ! $request->isJson()) {
            return response()->json(['detail' => 'Nur JSON.'], 415);
        }
        $request->attributes->set('candidate_channel', $kind === 'website' ? 'embed' : 'extension');

        return $this->headers($next($request), $origin, $kind);
    }

    /** extension (mit Sitzung), self (eigene Seite), website (eingetragen, ohne Sitzung) oder null. */
    public static function kind(?string $origin, Request $request): ?string
    {
        if ($origin === null) {
            return 'self';
        }
        if (preg_match(self::EXTENSION, $origin) === 1) {
            return 'extension';
        }
        if ($origin === $request->getSchemeAndHttpHost() || $origin === rtrim((string) config('app.url'), '/')) {
            return 'self';
        }
        foreach (explode(',', Settings::string('candidates.origins')) as $allowed) {
            if (rtrim(trim($allowed), '/') !== '' && rtrim(trim($allowed), '/') === $origin) {
                return 'website';
            }
        }

        return null;
    }

    private function headers(Response $response, ?string $origin, ?string $kind): Response
    {
        $response->headers->set('Vary', 'Origin', false);
        if ($origin !== null && $kind !== null && $kind !== 'self') {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            // Sitzung nur für Erweiterungen (Anmeldung erkennen), nie für fremde Webseiten
            if ($kind === 'extension') {
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            }
        }
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
