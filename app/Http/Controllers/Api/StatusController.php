<?php

namespace App\Http\Controllers\Api;

use App\Glossary\ApiPresenter;
use App\Http\Controllers\Controller;
use App\Support\AppVersion;
use Illuminate\Http\JsonResponse;

/** Einstieg der öffentlichen API (docs/KONZEPT-API.md): Version, Datenstand und Verweise auf alle Endpunkte. */
class StatusController extends Controller
{
    public function __invoke(ApiPresenter $presenter): JsonResponse
    {
        $version = AppVersion::info();

        return response()->json([
            'name' => 'EduKSX-Glossary API',
            'api_version' => 'v1',
            'app_version' => $version['version'],
            'developer_release' => $version['dev'],
            'release' => $presenter->release(),
            'openapi' => url('/api/openapi.json'),
            'docs' => url('/api/docs'),
            'endpoints' => [
                url('/api/v1/terms'),
                url('/api/v1/terms/{idOrSlug}'),
                url('/api/v1/categories'),
                url('/api/v1/sources'),
                url('/api/v1/releases/latest'),
                url('/api/v1/changes?since={cursor}'),
                url('/api/v1/exports?format=json'),
            ],
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
