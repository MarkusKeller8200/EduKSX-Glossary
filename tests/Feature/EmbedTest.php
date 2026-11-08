<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Einbindung für Entwickler (#155, DEC-026). */
class EmbedTest extends TestCase
{
    public function test_entwickler_seite_mit_sri(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('embed/manifest.json')), true);
        $this->assertIsArray($manifest);

        $this->get('/entwickler')->assertOk()->assertInertia(fn (Assert $p) => $p->component('developers')
            ->where('einbindung.version', $manifest['version'])
            ->where('einbindung.integrity', fn (string $sri): bool => str_starts_with($sri, 'sha384-')));
    }

    public function test_script_und_testseite_liegen_bereit(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('embed/manifest.json')), true);
        $file = public_path(ltrim((string) $manifest['file'], '/'));
        $this->assertFileExists($file);
        $this->assertFileEquals($file, public_path('embed/v1/glossary.js'));
        $this->assertSame($manifest['integrity'], 'sha384-'.base64_encode(hash_file('sha384', $file, true)));
        // Fremde Seiten laden mit crossorigin="anonymous": CORS-Kopfzeile in der Apache-Konfiguration
        $this->assertStringContainsString('Access-Control-Allow-Origin "*"', (string) file_get_contents(public_path('embed/v1/.htaccess')));
        $this->assertFileExists(public_path('embed/demo.html'));
    }
}
