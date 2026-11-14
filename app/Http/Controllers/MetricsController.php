<?php

namespace App\Http\Controllers;

use App\Metrics\Metrics;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * GET /metrics im Textformat von Prometheus (#192). Nur mit GLOSSARY_METRICS_TOKEN (sonst 404) und
 * `Authorization: Bearer <token>` (sonst 401).
 */
class MetricsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(Metrics::enabled(), 404);
        $token = (string) $request->bearerToken();
        if ($token === '' || ! hash_equals((string) config('glossary.metrics_token'), $token)) {
            return response('Unauthorized', 401, ['WWW-Authenticate' => 'Bearer', 'Content-Type' => 'text/plain']);
        }

        return response(Metrics::render(), 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
