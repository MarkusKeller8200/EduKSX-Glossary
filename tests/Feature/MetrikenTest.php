<?php

namespace Tests\Feature;

use App\Models\Role;
use Illuminate\Support\Facades\Log;
use Monolog\Formatter\JsonFormatter;
use Tests\TestCase;

/** Strukturierte Protokolle und Metriken (#192). */
final class MetrikenTest extends TestCase
{
    public function test_ohne_token_aus(): void
    {
        config(['glossary.metrics_token' => '']);
        $this->get('/metrics')->assertNotFound();
    }

    public function test_nur_mit_token_und_im_format_von_prometheus(): void
    {
        config(['glossary.metrics_token' => 'geheimes-metrik-token']);
        $this->get('/metrics')->assertStatus(401);
        $this->get('/metrics', ['Authorization' => 'Bearer falsch'])->assertStatus(401);

        $this->getJson('/api/v1/terms')->assertOk();
        $this->get('/')->assertOk();
        $this->post('/anmelden', ['email' => 'niemand@example.org', 'password' => 'falsch-falsch-falsch']);

        $body = (string) $this->get('/metrics', ['Authorization' => 'Bearer geheimes-metrik-token'])
            ->assertOk()->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8')->getContent();
        $this->assertStringContainsString('glossary_build_info{version="'.config('glossary.version').'"} 1', $body);
        $this->assertStringContainsString('glossary_http_requests_total{group="api",status="2xx"} 1', $body);
        $this->assertStringContainsString('glossary_http_request_duration_seconds_bucket{group="api",le="+Inf"} 1', $body);
        $this->assertStringContainsString('glossary_logins_total{result="failed"} 1', $body);
        $this->assertStringContainsString('glossary_terms_published 0', $body);
        $this->assertStringNotContainsString('@', $body, 'keine Personendaten');
    }

    public function test_protokoll_als_json_mit_request_id(): void
    {
        config(['logging.channels.stderr.formatter' => JsonFormatter::class]);
        $handler = Log::channel('stderr')->getLogger()->getHandlers()[0] ?? null;
        $this->assertNotNull($handler);
        $this->assertInstanceOf(JsonFormatter::class, $handler->getFormatter());

        $this->actingAs($this->userWithRoles([Role::USER], 'lea@schule.example'));
        $response = $this->getJson('/api/v1/terms');
        $this->assertNotSame('', (string) $response->headers->get('X-Request-Id'));
    }
}
