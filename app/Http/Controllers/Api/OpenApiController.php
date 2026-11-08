<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * Maschinenlesbare Beschreibung der API (OpenAPI 3.1, openapi/glossary-v1.json). JSON ist gültiges YAML 1.2,
 * darum liefert /api/openapi.yaml denselben Inhalt mit YAML-Typ.
 */
class OpenApiController extends Controller
{
    public function json(): Response
    {
        return $this->file('application/json; charset=utf-8');
    }

    public function yaml(): Response
    {
        return $this->file('application/yaml; charset=utf-8');
    }

    /** Postman-Sammlung zum Import (#158), erzeugt mit scripts/postman.py. */
    public function postman(): Response
    {
        return response((string) file_get_contents(base_path('openapi/glossary-v1.postman.json')), 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="eduksx-glossary.postman_collection.json"',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    private function file(string $type): Response
    {
        return response((string) file_get_contents(base_path('openapi/glossary-v1.json')), 200, [
            'Content-Type' => $type,
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
