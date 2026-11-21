<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Vollständige Content Security Policy mit Nonce, zuerst Report-Only (#397). */
final class CspTest extends TestCase
{
    public function test_report_only_mit_nonce_und_trusted_types(): void
    {
        $response = $this->get('/')->assertOk();
        $policy = (string) $response->headers->get('Content-Security-Policy-Report-Only');
        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("require-trusted-types-for 'script'", $policy);
        $this->assertStringContainsString('report-uri /csp-bericht', $policy);
        $this->assertSame(1, preg_match("/script-src 'self' 'nonce-([A-Za-z0-9+\\/=]+)'/", $policy, $m));
        // Inline-Script und -Stil der Seite tragen dieselbe Nonce
        $this->assertStringContainsString('<script nonce="'.$m[1].'">', (string) $response->getContent());
        $this->assertStringContainsString('<style nonce="'.$m[1].'">', (string) $response->getContent());
        // erzwungen bleibt vorerst nur die Richtlinie aus eduksx/core
        $this->assertStringNotContainsString('default-src', (string) $response->headers->get('Content-Security-Policy'));
        $this->assertFalse($response->headers->has('X-Glossary-Csp'));
        $this->assertStringContainsString("frame-ancestors 'self'", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_erzwingen_mit_schalter(): void
    {
        config(['glossary.csp_enforce' => true]);
        $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringNotContainsString('require-trusted-types-for', $csp);
    }

    public function test_nicht_fuer_json(): void
    {
        $this->assertNull($this->getJson('/api/v1')->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_bericht_ohne_abfrage_und_ohne_csrf(): void
    {
        $this->call('POST', '/csp-bericht', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], (string) json_encode(['csp-report' => [
            'violated-directive' => 'script-src', 'blocked-uri' => 'https://boese.example/x.js?token=geheim', 'document-uri' => 'https://glossary.eduksx.ch/konto?x=1',
        ]]))->assertNoContent();
    }
}
