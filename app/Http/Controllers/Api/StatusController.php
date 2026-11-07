<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\AppVersion;
use Illuminate\Http\JsonResponse;

/**
 * Einstieg der öffentlichen API (docs/KONZEPT-API.md). Die Endpunkte für Begriffe, Kategorien, Datenstand und
 * Änderungen kommen mit dem Datenmodell (0.2/0.3); bis dahin nur Version und Verweise.
 */
class StatusController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $version = AppVersion::info();

        return response()->json([
            'name' => 'EduKSX-Glossary API',
            'api_version' => 'v1',
            'app_version' => $version['version'],
            'developer_release' => $version['dev'],
            'openapi' => url('/api/openapi.yaml'),
            'endpoints' => [],
        ]);
    }
}
