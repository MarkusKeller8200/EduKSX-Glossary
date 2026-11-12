<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Hinweis auf neue Versionen (#176, DEC-027 U8): fragt höchstens einmal täglich die öffentlichen Releases auf GitHub
 * ab – nur die Versionsnummer, keine Daten dieser Instanz. Abschaltbar in den Einstellungen bzw. mit
 * GLOSSARY_UPDATE_CHECK=false (z. B. im Schulnetz ohne Internet).
 */
final class UpdateCheck
{
    private const CACHE_KEY = 'glossary.update.latest';

    public static function enabled(): bool
    {
        return (bool) config('glossary.update_check', true) && Settings::bool('updates.check');
    }

    /** Neueste veröffentlichte Version (ohne «v»), null wenn aus, unbekannt oder nicht erreichbar. */
    public static function latest(): ?string
    {
        if (! self::enabled()) {
            return null;
        }
        $repo = (string) config('glossary.update_repo', 'MarkusKeller8200/EduKSX-Glossary');

        /** @var string $cached */
        $cached = Cache::remember(self::CACHE_KEY, 86400, function () use ($repo): string {
            try {
                $response = Http::timeout(5)->acceptJson()
                    ->withHeaders(['User-Agent' => 'EduKSX-Glossary-Update-Check'])
                    ->get("https://api.github.com/repos/{$repo}/releases/latest");
                $tag = $response->successful() ? (string) $response->json('tag_name', '') : '';
            } catch (Throwable) {
                $tag = '';
            }

            return ltrim($tag, 'v');
        });

        return preg_match('/^\d+\.\d+\.\d+$/', $cached) === 1 ? $cached : null;
    }

    /**
     * Stand für Systemseite und Übersicht.
     *
     * @return array{aktiv: bool, installiert: string, neueste: string|null, neu: bool, art: string|null, url: string|null}
     */
    public static function status(): array
    {
        $installed = (string) config('glossary.version', '0.0.0');
        $latest = self::latest();
        $newer = $latest !== null && version_compare($latest, $installed, '>');
        $repo = (string) config('glossary.update_repo', 'MarkusKeller8200/EduKSX-Glossary');

        return [
            'aktiv' => self::enabled(),
            'installiert' => $installed,
            'neueste' => $latest,
            'neu' => $newer,
            'art' => $newer ? self::kind($installed, (string) $latest) : null,
            'url' => $newer ? "https://github.com/{$repo}/releases/tag/v{$latest}" : null,
        ];
    }

    /** Art der Änderung nach Semantic Versioning: korrektur (PATCH), funktion (MINOR), hauptversion (MAJOR). */
    public static function kind(string $from, string $to): string
    {
        [$a1, $a2] = array_map('intval', explode('.', $from) + [0, 0, 0]);
        [$b1, $b2] = array_map('intval', explode('.', $to) + [0, 0, 0]);
        if ($b1 !== $a1) {
            return 'hauptversion';
        }

        return $b2 !== $a2 ? 'funktion' : 'korrektur';
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
