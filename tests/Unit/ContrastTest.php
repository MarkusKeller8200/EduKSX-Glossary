<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Kontrast der Farbwerte (#63, docs/KONZEPT-DESIGN.md): Text-Paare mindestens 4.5 : 1 (WCAG AA), Linien und Fokus
 * mindestens 3 : 1 – hell und dunkel, direkt aus resources/css/app.css.
 */
class ContrastTest extends TestCase
{
    /** Vordergrund, Hintergrund, Mindestwert. */
    private const PAIRS = [
        ['foreground', 'background', 4.5],
        ['card-foreground', 'card', 4.5],
        ['popover-foreground', 'popover', 4.5],
        ['muted-foreground', 'muted', 4.5],
        ['muted-foreground', 'background', 4.5],
        ['primary-foreground', 'primary', 4.5],
        ['secondary-foreground', 'secondary', 4.5],
        ['accent-foreground', 'accent', 4.5],
        ['link', 'background', 4.5],
        ['link', 'sidebar', 4.5],
        ['muted-foreground', 'sidebar', 4.5],
        ['sidebar-foreground', 'sidebar', 4.5],
        ['destructive-foreground', 'background', 4.5],
        // Status-Labels (#91): Text auf der hellen Fläche
        ['success', 'success-soft', 4.5],
        ['warning', 'warning-soft', 4.5],
        ['info', 'info-soft', 4.5],
        // Linien und Fokus
        ['ring', 'background', 3.0],
        ['term-underline', 'background', 3.0],
    ];

    public function test_farbpaare_hell_und_dunkel(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');
        foreach ([':root' => 'hell', '.dark' => 'dunkel'] as $selector => $mode) {
            $tokens = $this->tokens($css, $selector);
            foreach (self::PAIRS as [$fg, $bg, $min]) {
                $this->assertArrayHasKey($fg, $tokens, "--{$fg} fehlt ({$mode})");
                $this->assertArrayHasKey($bg, $tokens, "--{$bg} fehlt ({$mode})");
                $ratio = $this->ratio($tokens[$fg], $tokens[$bg]);
                $this->assertGreaterThanOrEqual($min, $ratio, sprintf('--%s auf --%s (%s): %.2f : 1, verlangt %.1f : 1', $fg, $bg, $mode, $ratio, $min));
            }
        }
    }

    public function test_orange_nie_als_schrift_auf_weiss(): void
    {
        // Befund KONZEPT-DESIGN: Orange #FF8A00 auf Weiss nur 2.36 : 1 – der Test hält den Wert fest
        $this->assertLessThan(3.0, $this->ratio('#ff8a00', '#ffffff'));
    }

    /** @return array<string, string> */
    private function tokens(string $css, string $selector): array
    {
        $this->assertSame(1, preg_match('/'.preg_quote($selector, '/').'\s*\{(.*?)\n\}/s', $css, $block), "{$selector} fehlt in app.css");
        preg_match_all('/--([a-z0-9-]+):\s*(#[0-9a-fA-F]{6})\b/', $block[1] ?? '', $m, PREG_SET_ORDER);
        $tokens = [];
        foreach ($m as [, $name, $hex]) {
            $tokens[$name] = strtolower($hex);
        }

        return $tokens;
    }

    private function ratio(string $a, string $b): float
    {
        [$l1, $l2] = [$this->luminance($a), $this->luminance($b)];

        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    private function luminance(string $hex): float
    {
        $channels = [];
        foreach ([1, 3, 5] as $i) {
            $c = hexdec(substr($hex, $i, 2)) / 255;
            $channels[] = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
