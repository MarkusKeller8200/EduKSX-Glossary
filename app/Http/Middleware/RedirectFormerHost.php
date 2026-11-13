<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Frühere Adresse dauerhaft weiterleiten (#304): Anfragen an GLOSSARY_REDIRECT_HOST gehen mit 301 auf dieselbe
 * Seite unter APP_URL, z. B. glossary.markussx.ch → glossary-dev.markussx.ch. Ohne Einstellung wirkungslos.
 */
class RedirectFormerHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $former = mb_strtolower(trim((string) config('glossary.redirect_host')));
        if ($former !== '' && mb_strtolower($request->getHost()) === $former) {
            return redirect()->away(rtrim((string) config('app.url'), '/').$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
