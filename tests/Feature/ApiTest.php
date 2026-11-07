<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Öffentliche API (docs/KONZEPT-API.md). */
class ApiTest extends TestCase
{
    public function test_einstieg_v1(): void
    {
        $this->getJson('/api/v1')
            ->assertOk()
            ->assertJsonPath('api_version', 'v1')
            ->assertJsonStructure(['name', 'api_version', 'app_version', 'endpoints']);
    }

    public function test_openapi_beschreibung(): void
    {
        $response = $this->get('/api/openapi.yaml')->assertOk();
        $this->assertStringContainsString('openapi: 3.1.0', (string) file_get_contents(base_path('openapi/glossary-v1.yaml')));
        $this->assertStringStartsWith('application/yaml', (string) $response->headers->get('Content-Type'));
    }

    public function test_unbekannter_pfad_als_json(): void
    {
        $this->get('/api/v1/gibt-es-nicht')->assertNotFound()->assertHeader('Content-Type', 'application/json');
    }
}
