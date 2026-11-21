<?php

use App\Http\Middleware\McpEnabled;
use App\Mcp\GlossaryServer;
use Laravel\Mcp\Facades\Mcp;

// MCP-Server (#261, DEC-029): Streamable HTTP unter /mcp, Phase 1 öffentlich lesen. Begrenzer 60 Anfragen pro Minute
// und IP-Adresse (O5); abschaltbar in den Einstellungen (O2). Keine Sitzung, kein Cookie, keine Protokollierung der
// Eingaben (M9).
Mcp::web('/mcp', GlossaryServer::class)->middleware([McpEnabled::class, 'throttle:mcp']);
