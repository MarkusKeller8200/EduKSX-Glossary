<?php

namespace Tests\Feature;

use EduKSX\Core\Testing\AssertsConformance;
use Tests\TestCase;

/** Konformitätstest der Plattform aus eduksx/core (DB-Standard E5, API-Standard D8, #334). */
final class ConformanceTest extends TestCase
{
    use AssertsConformance;

    public function test_datenbank(): void
    {
        $this->assertDatabaseConforms();
    }

    public function test_api_grundlagen(): void
    {
        $this->assertApiBasicsConform();
    }

    public function test_openapi_und_routen(): void
    {
        $this->assertOpenApiConforms(base_path('openapi/glossary-v1.json'));
    }

    /** Steckbrief und /health nach spec/v1 (#189). */
    public function test_steckbrief_und_health(): void
    {
        $this->assertPlatformEndpointsConform();
    }
}
