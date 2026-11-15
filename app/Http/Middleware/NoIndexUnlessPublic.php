<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Suchmaschinen (#393): Ohne GLOSSARY_SUCHMASCHINEN=true trägt jede Antwort «X-Robots-Tag: noindex, nofollow» –
 * dev, test und selbst gehostete Kopien erscheinen so nicht in Suchmaschinen neben dem öffentlichen Glossary.
 */
class NoIndexUnlessPublic
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (! config('glossary.search_engines')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
