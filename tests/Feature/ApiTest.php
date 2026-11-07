<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Öffentliche API: Einstieg, OpenAPI, Doku-Seite, Fehlerformat (#25, #26). */
class ApiTest extends TestCase
{
    public function test_einstieg_v1(): void
    {
        $this->getJson('/api/v1')
            ->assertOk()
            ->assertJsonPath('api_version', 'v1')
            ->assertJsonPath('release.release', 0)
            ->assertJsonPath('stability', 'preview')
            ->assertJsonPath('license.name', 'CC BY 4.0')
            ->assertJsonStructure(['name', 'api_version', 'app_version', 'release', 'openapi', 'docs', 'endpoints']);
    }

    public function test_openapi_json_und_yaml(): void
    {
        $spec = $this->get('/api/openapi.json')->assertOk()->assertHeader('Content-Type', 'application/json; charset=utf-8')->json();
        $this->assertSame('3.1.0', $spec['openapi']);
        foreach (['/v1', '/v1/terms', '/v1/terms/{idOrSlug}', '/v1/categories', '/v1/sources', '/v1/releases/latest', '/v1/changes', '/v1/exports'] as $path) {
            $this->assertArrayHasKey($path, $spec['paths'], "Pfad {$path} fehlt in der OpenAPI-Beschreibung");
        }

        $this->assertStringStartsWith('application/yaml', (string) $this->get('/api/openapi.yaml')->assertOk()->headers->get('Content-Type'));
    }

    public function test_jede_route_der_api_ist_beschrieben(): void
    {
        $spec = json_decode((string) file_get_contents(base_path('openapi/glossary-v1.json')), true);
        foreach (app('router')->getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/v1')) {
                continue;
            }
            $path = '/'.substr($uri, strlen('api/'));
            $this->assertArrayHasKey(rtrim($path, '/') ?: '/v1', $spec['paths'], "Route {$uri} fehlt in openapi/glossary-v1.json");
        }
    }

    public function test_doku_seite(): void
    {
        $this->get('/api/docs')->assertOk()->assertSee('api-reference', false)->assertSee('/api/openapi.json', false)
            ->assertSee('Vorschau')->assertSee('CC BY 4.0');
        $this->getJson('/api/v1/exports?format=json')->assertJsonPath('license.attribution', 'EduKSX-Glossary, glossary.eduksx.ch');
        $this->assertSame('CC BY 4.0 (Daten)', $this->get('/api/openapi.json')->json('info.license.name'));
    }

    public function test_unbekannter_pfad_als_problem_json(): void
    {
        $this->get('/api/v1/gibt-es-nicht')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 404)
            ->assertJsonPath('title', 'Not Found');
    }

    public function test_cors_fuer_lesezugriffe(): void
    {
        $this->get('/api/v1', ['Origin' => 'https://intranet.example.org'])
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }
}
