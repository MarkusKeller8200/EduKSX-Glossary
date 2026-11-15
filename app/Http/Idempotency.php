<?php

namespace App\Http;

use App\Models\ServiceAccount;
use Closure;
use EduKSX\Core\Http\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Idempotenz für schreibende API-Aufrufe (API-Standard B3, #348): Mit der Kopfzeile `Idempotency-Key` liefert eine
 * Wiederholung derselben Anfrage dieselbe Antwort, ohne die Aktion nochmals auszuführen. Gleicher Schlüssel mit
 * anderem Inhalt → 422 `idempotency_key_reused`. Schlüssel je Servicekonto, 24 Stunden aufbewahrt
 * (glossary:loeschfristen). Ohne Kopfzeile ändert sich nichts.
 */
final class Idempotency
{
    public const HEADER = 'Idempotency-Key';

    public const HOURS = 24;

    /** Platzhalter während der Bearbeitung (HTTP 102 Processing); liegt im erlaubten Bereich 100–599. */
    private const PROCESSING = 102;

    /** @param  Closure(): JsonResponse  $action */
    public static function handle(Request $request, ServiceAccount $account, Closure $action): JsonResponse
    {
        $key = $request->headers->get(self::HEADER);
        if ($key === null) {
            return $action();
        }
        if ($key === '' || mb_strlen($key) > 200 || preg_match('/^[\x21-\x7E]+$/', $key) !== 1) {
            throw ProblemException::common(422, 'validation_failed', 'Idempotency-Key: 1 bis 200 sichtbare ASCII-Zeichen.', [], [self::HEADER => ['ungültig']]);
        }
        $hash = hash('sha256', $request->method().' '.$request->path()."\n".$request->getContent());

        // abgelaufener Eintrag bzw. hängengebliebene Reservierung (älter als 5 Minuten): Platz machen
        DB::table('api_idempotency_keys')->where('service_account_id', $account->id)->where('idempotency_key', $key)
            ->where(fn ($q) => $q->where('created_at', '<', now()->subHours(self::HOURS))
                ->orWhere(fn ($q) => $q->where('response_status', self::PROCESSING)->where('created_at', '<', now()->subMinutes(5))))
            ->delete();

        // Schlüssel vor der Aktion reservieren (#447, Security-Review SEC-03): der eindeutige Index lässt bei
        // gleichzeitigen Anfragen nur eine durch; die anderen erhalten die gespeicherte Antwort bzw. 409.
        $reserved = DB::table('api_idempotency_keys')->insertOrIgnore([
            'service_account_id' => $account->id,
            'idempotency_key' => $key,
            'request_hash' => $hash,
            'response_status' => self::PROCESSING,
            'response_body' => '',
            'created_at' => now(),
        ]);
        if ($reserved === 0) {
            $stored = self::find($account, $key);
            if ($stored === null) {
                throw ProblemException::common(409, 'conflict', 'Diese Anfrage wird gerade bearbeitet. Bitte später wiederholen.', ['Retry-After' => '1']);
            }

            return self::replay($stored, $hash);
        }

        $release = fn () => DB::table('api_idempotency_keys')->where('service_account_id', $account->id)
            ->where('idempotency_key', $key)->where('response_status', self::PROCESSING)->delete();
        try {
            $response = $action();
        } catch (\Throwable $e) {
            $release();   // Fehler: eine Wiederholung darf es nochmals versuchen

            throw $e;
        }
        if ($response->getStatusCode() >= 500) {
            $release();   // Serverfehler nicht festhalten

            return $response;
        }
        DB::table('api_idempotency_keys')->where('service_account_id', $account->id)->where('idempotency_key', $key)
            ->update(['response_status' => $response->getStatusCode(), 'response_body' => (string) $response->getContent()]);

        return $response;
    }

    /** Abgelaufene Schlüssel löschen; liefert die Anzahl. */
    public static function prune(): int
    {
        return DB::table('api_idempotency_keys')->where('created_at', '<', now()->subHours(self::HOURS))->delete();
    }

    /** @return array{request_hash: string, response_status: int, response_body: string}|null */
    private static function find(ServiceAccount $account, string $key): ?array
    {
        $row = DB::table('api_idempotency_keys')
            ->where('service_account_id', $account->id)
            ->where('idempotency_key', $key)
            ->where('created_at', '>=', now()->subHours(self::HOURS))
            ->first(['request_hash', 'response_status', 'response_body']);
        if ($row === null) {
            return null;
        }
        $row = (array) $row;

        return [
            'request_hash' => (string) ($row['request_hash'] ?? ''),
            'response_status' => (int) ($row['response_status'] ?? 200),
            'response_body' => (string) ($row['response_body'] ?? ''),
        ];
    }

    /** @param  array{request_hash: string, response_status: int, response_body: string}  $stored */
    private static function replay(array $stored, string $hash): JsonResponse
    {
        if (! hash_equals($stored['request_hash'], $hash)) {
            throw ProblemException::common(422, 'idempotency_key_reused', 'Dieser Idempotency-Key wurde schon für eine andere Anfrage verwendet.');
        }
        if ($stored['response_status'] === self::PROCESSING) {
            throw ProblemException::common(409, 'conflict', 'Diese Anfrage wird gerade bearbeitet. Bitte später wiederholen.', ['Retry-After' => '1']);
        }

        return JsonResponse::fromJsonString($stored['response_body'], $stored['response_status'], [
            'Idempotency-Replayed' => 'true',
            'Cache-Control' => 'no-store',
        ]);
    }
}
