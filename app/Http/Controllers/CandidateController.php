<?php

namespace App\Http\Controllers;

use App\Candidates\CandidateService;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

/**
 * Begriff melden aus Browser-Erweiterung und Einbindung (#480, DEC-042): anonym mit Abruflimit je IP-Bereich und
 * Killswitch, angemeldet immer. Es entsteht nur ein Kandidat für die Redaktion.
 */
class CandidateController extends Controller
{
    /** M5: anonym je IP-Bereich 5 je Stunde und 20 je Tag, insgesamt 200 je Tag; angemeldet 60 je Stunde */
    private const LIMITS = ['hour' => 5, 'day' => 20, 'all' => 200, 'user' => 60];

    public function __construct(private readonly CandidateService $candidates) {}

    /** GET /erweiterung/ich: angemeldet? und ist anonymes Melden offen? Ohne Personendaten ausser dem Community-Namen. */
    public function me(Request $request): JsonResponse
    {
        $user = $this->sessionUser($request);

        return response()->json([
            'angemeldet' => $user !== null,
            'name' => $user !== null ? (string) ($user->community_name ?: 'angemeldet') : null,
            'anonym' => Settings::bool('candidates.anonymous'),
            'anmelden' => url('/anmelden'),
        ]);
    }

    /** POST /erweiterung/kandidaten */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'wort' => ['required', 'string', 'max:'.CandidateService::MAX_WORD],
            'art' => ['nullable', 'in:fehlt,falsch'],
            'code' => ['nullable', 'string', 'max:10'],
            'kommentar' => ['nullable', 'string', 'max:'.CandidateService::MAX_COMMENT],
            'adresse' => ['nullable', 'string', 'max:2000'],
        ]);
        $user = $this->sessionUser($request);
        if ($user === null && ! Settings::bool('candidates.anonymous')) {
            return response()->json(['detail' => 'Zurzeit nur angemeldet möglich.', 'anmelden' => url('/anmelden')], 403);
        }
        if (($retry = $this->limited($request, $user)) !== null) {
            return response()->json(['detail' => 'Zu viele Meldungen – bitte später nochmals.'], 429, ['Retry-After' => (string) $retry]);
        }
        try {
            $candidate = $this->candidates->report(
                (string) $data['wort'],
                ($data['art'] ?? 'fehlt') === 'falsch' ? 'wrong' : 'missing',
                (string) $request->attributes->get('candidate_channel', 'extension'),
                isset($data['code']) ? (string) $data['code'] : null,
                isset($data['kommentar']) ? (string) $data['kommentar'] : null,
                isset($data['adresse']) ? (string) $data['adresse'] : null,
                $user,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['detail' => $e->getMessage()], 422);
        }

        // gesperrte Wörter ohne Hinweis verwerfen (kein Rückschluss auf die Sperrliste)
        return response()->json([
            'ok' => true,
            'text' => 'Danke! Die Redaktion prüft die Meldung.',
            'angemeldet' => $user !== null,
            'neu' => $candidate !== null && $candidate->count === 1,
        ], 202);
    }

    /** Konto aus der Sitzung, nur für Erweiterungen und die eigene Seite (eingetragene Webseiten immer anonym). */
    private function sessionUser(Request $request): ?User
    {
        if ($request->attributes->get('candidate_channel') === 'embed') {
            return null;
        }
        $user = $request->user();

        return $user instanceof User && $user->isActive() ? $user : null;
    }

    /** Sekunden bis zum nächsten Versuch, wenn ein Limit erreicht ist; sonst null (und gezählt). */
    private function limited(Request $request, ?User $user): ?int
    {
        if ($user !== null) {
            $keys = ['kandidat:user:'.$user->id => [self::LIMITS['user'], 3600]];
        } else {
            $range = hash('sha256', self::range((string) $request->ip()).'|'.config('app.key'));
            $keys = [
                'kandidat:h:'.$range => [self::LIMITS['hour'], 3600],
                'kandidat:d:'.$range => [self::LIMITS['day'], 86400],
                'kandidat:all' => [self::LIMITS['all'], 86400],
            ];
        }
        foreach ($keys as $key => [$max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return RateLimiter::availableIn($key);
            }
        }
        foreach ($keys as $key => [, $decay]) {
            RateLimiter::hit($key, $decay);
        }

        return null;
    }

    /** IP-Bereich: IPv4 /24, IPv6 /48 (M5, M9: die Adresse selbst wird nie gespeichert). */
    public static function range(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $packed = (string) inet_pton($ip);

            return bin2hex(substr($packed, 0, 6));
        }
        $parts = explode('.', $ip);

        return implode('.', array_slice($parts, 0, 3));
    }
}
