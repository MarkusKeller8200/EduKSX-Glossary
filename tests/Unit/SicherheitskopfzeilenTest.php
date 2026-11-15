<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Pentest 15.11.2026 (#396): Session-Cookie mit «__Host-», Apache-Kopfzeilen genau einmal, Server ohne Version. */
final class SicherheitskopfzeilenTest extends TestCase
{
    /** @param array<string, string|null> $env */
    private function sessionCookie(array $env): string
    {
        $keys = ['SESSION_COOKIE', 'SESSION_SECURE_COOKIE', 'SESSION_DOMAIN', 'SESSION_PATH', 'APP_ENV', 'APP_NAME'];
        $before = [];
        foreach ($keys as $key) {
            $before[$key] = getenv($key);
            $value = $env[$key] ?? null;
            if ($value === null) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
            } else {
                putenv("{$key}={$value}");
                $_ENV[$key] = $_SERVER[$key] = $value;
            }
        }
        try {
            $config = require dirname(__DIR__, 2).'/config/session.php';

            return (string) $config['cookie'];
        } finally {
            foreach ($before as $key => $value) {
                if ($value === false) {
                    putenv($key);
                    unset($_ENV[$key], $_SERVER[$key]);
                } else {
                    putenv("{$key}={$value}");
                    $_ENV[$key] = $_SERVER[$key] = $value;
                }
            }
        }
    }

    public function test_session_cookie_mit_host_praefix_nur_ueber_https(): void
    {
        $this->assertSame('__Host-eduksx-glossary-session', $this->sessionCookie(['APP_NAME' => 'EduKSX-Glossary', 'SESSION_SECURE_COOKIE' => 'true']));
        $this->assertSame('__Host-eduksx-glossary-session', $this->sessionCookie(['APP_NAME' => 'EduKSX-Glossary', 'APP_ENV' => 'production']));
        // lokal über http: Browser würden ein «__Host-»-Cookie ablehnen
        $this->assertSame('eduksx-glossary-session', $this->sessionCookie(['APP_NAME' => 'EduKSX-Glossary', 'SESSION_SECURE_COOKIE' => 'false']));
        // mit Domain ist «__Host-» nicht erlaubt
        $this->assertSame('eduksx-glossary-session', $this->sessionCookie(['APP_NAME' => 'EduKSX-Glossary', 'SESSION_SECURE_COOKIE' => 'true', 'SESSION_DOMAIN' => '.eduksx.ch']));
        // ausdrücklich gesetzt: bleibt
        $this->assertSame('eigenes', $this->sessionCookie(['SESSION_COOKIE' => 'eigenes', 'SESSION_SECURE_COOKIE' => 'true']));
    }

    public function test_apache_setzt_kopfzeilen_einmal_und_ohne_version(): void
    {
        $dockerfile = (string) file_get_contents(dirname(__DIR__, 2).'/Dockerfile');
        // nach security.conf von Debian geladen, sonst gilt «ServerTokens OS»
        $this->assertStringContainsString('/etc/apache2/conf-enabled/zz-glossary.conf', $dockerfile);
        $this->assertStringContainsString("'ServerTokens Prod'", $dockerfile);
        foreach (['X-Content-Type-Options', 'Referrer-Policy'] as $header) {
            $this->assertStringContainsString("'Header onsuccess unset {$header}'", $dockerfile);
            $this->assertStringContainsString("'Header always set {$header} ", $dockerfile);
        }
    }
}
