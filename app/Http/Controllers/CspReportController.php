<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Verstösse gegen die Content Security Policy (#397): nur Richtlinie, blockierte Quelle (ohne Abfrage) und Dokument
 * (ohne Abfrage) ins Protokoll – keine Personendaten, keine Inhalte. Abruflimit über die Route.
 */
class CspReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $body = json_decode((string) $request->getContent(), true);
        $report = is_array($body) ? (array) ($body['csp-report'] ?? $body[0]['body'] ?? $body) : [];
        $strip = fn (mixed $v): string => mb_substr((string) preg_replace('/[?#].*$/', '', is_scalar($v) ? (string) $v : ''), 0, 200);
        Log::channel(config('logging.default'))->warning('CSP-Verstoss', [
            'directive' => $strip($report['violated-directive'] ?? $report['effectiveDirective'] ?? ''),
            'blocked' => $strip($report['blocked-uri'] ?? $report['blockedURL'] ?? ''),
            'document' => $strip($report['document-uri'] ?? $report['documentURL'] ?? ''),
            'sample' => mb_substr(is_scalar($report['script-sample'] ?? $report['sample'] ?? null) ? (string) ($report['script-sample'] ?? $report['sample']) : '', 0, 80),
        ]);

        return response('', 204);
    }
}
