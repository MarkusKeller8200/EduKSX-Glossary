<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Spam-Schutz mit Cloudflare Turnstile (#41, docs/KONZEPT-BENUTZER.md 5.1). Ohne geheimen Schlüssel ausgeschaltet
 * (lokal, Tests). Prüfung serverseitig über Siteverify; bei Ausfall von Cloudflare wird abgewiesen.
 */
final class Turnstile
{
    public static function enabled(): bool
    {
        return (string) config('glossary.turnstile.secret') !== '' && (string) config('glossary.turnstile.site_key') !== '';
    }

    public static function siteKey(): ?string
    {
        return self::enabled() ? (string) config('glossary.turnstile.site_key') : null;
    }

    public static function verify(?string $token, ?string $ip): bool
    {
        if (! self::enabled()) {
            return true;
        }
        if ($token === null || $token === '') {
            return false;
        }
        try {
            $response = Http::asForm()->timeout(10)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => (string) config('glossary.turnstile.secret'),
                'response' => $token,
                'remoteip' => $ip,
            ]);

            return $response->ok() && $response->json('success') === true;
        } catch (Throwable $e) {
            Log::warning('Turnstile nicht erreichbar', ['message' => $e->getMessage()]);

            return false;
        }
    }
}
