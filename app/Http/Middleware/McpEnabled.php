<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** MCP-Server nur, wenn in den Einstellungen eingeschaltet (#261, DEC-029 M8, O2); sonst 404. */
class McpEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Settings::bool('mcp.enabled'), 404);

        return $next($request);
    }
}
