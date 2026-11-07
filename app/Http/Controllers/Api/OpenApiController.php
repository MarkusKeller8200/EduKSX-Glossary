<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Maschinenlesbare Beschreibung der API (OpenAPI 3.1, openapi/glossary-v1.yaml). */
class OpenApiController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        return response()->file(base_path('openapi/glossary-v1.yaml'), [
            'Content-Type' => 'application/yaml; charset=utf-8',
        ]);
    }
}
