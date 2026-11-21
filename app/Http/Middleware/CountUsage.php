<?php

namespace App\Http\Middleware;

use App\Statistics\Usage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Statistik ohne Cookies (#495) für API und MCP: zählt erfolgreiche Abrufe je Endpunkt bzw. MCP-Werkzeug. Der Index
 * wird nach Kanal getrennt: `?client=extension` (Browser-Erweiterung) bzw. `?client=embed` (Einbindung, mit Domain der
 * einbindenden Webseite aus «Origin»). Inhalte der Anfragen werden nicht gelesen ausser dem Namen des MCP-Werkzeugs.
 */
class CountUsage
{
    public function handle(Request $request, Closure $next, string $kind = 'api'): Response
    {
        $response = $next($request);
        $status = $response->getStatusCode();
        if (($status < 200 || $status >= 300) && $status !== 304) {
            return $response;
        }
        if ($kind === 'mcp') {
            $payload = json_decode((string) $request->getContent(), true);
            if (is_array($payload) && ($payload['method'] ?? null) === 'tools/call' && is_string($payload['params']['name'] ?? null)) {
                Usage::hit('mcp', 'tool', (string) preg_replace('/[^a-z0-9_]/', '', $payload['params']['name']), $request);
            }

            return $response;
        }
        $route = (string) $request->route()?->getName();
        if ($route === 'api.v1.index') {
            match ((string) $request->query('client', '')) {
                'extension' => Usage::hit('extension', 'index', '', $request),
                'embed' => Usage::hit('embed', 'index', Usage::domain($request->headers->get('Origin') ?? $request->headers->get('Referer')), $request),
                default => Usage::hit('api', 'endpoint', 'index', $request),
            };

            return $response;
        }
        if (str_starts_with($route, 'api.v1.')) {
            Usage::hit('api', 'endpoint', substr($route, 7), $request);
        }

        return $response;
    }
}
