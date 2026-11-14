<?php

namespace App\Http\Middleware;

use App\Metrics\Metrics;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Anfragen und Antwortzeiten für die Metriken zählen (#192); nur mit GLOSSARY_METRICS_TOKEN aktiv. */
class CountRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        $response = $next($request);
        if (Metrics::enabled() && ! $request->is('metrics', 'up', 'health')) {
            $group = $request->is('api/*') ? 'api' : ($request->is('admin', 'admin/*') ? 'admin' : 'web');
            Metrics::request($group, $response->getStatusCode(), microtime(true) - $start);
        }

        return $response;
    }
}
