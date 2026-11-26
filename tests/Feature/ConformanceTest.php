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

    /**
     * Protokoll im Image (LOG_FORMAT=json): Format und Maskierung von eduksx/core (KONZEPT-PROTOKOLLIERUNG §6, #521).
     */
    public function test_protokoll_maskiert_personendaten(): void
    {
        $previous = getenv('LOG_FORMAT');
        putenv('LOG_FORMAT=json');
        $_ENV['LOG_FORMAT'] = $_SERVER['LOG_FORMAT'] = 'json';
        try {
            $channels = (require base_path('config/logging.php'))['channels'];
        } finally {
            $previous === false ? putenv('LOG_FORMAT') : putenv('LOG_FORMAT='.$previous);
            unset($_ENV['LOG_FORMAT'], $_SERVER['LOG_FORMAT']);
        }
        config(['logging.channels.stderr' => $channels['stderr']]);

        $this->assertLoggingMasksPersonalData('stderr');
    }

    /** Steckbrief und /health nach spec/v1 (#189). */
    public function test_steckbrief_und_health(): void
    {
        $this->assertPlatformEndpointsConform();
    }
}
