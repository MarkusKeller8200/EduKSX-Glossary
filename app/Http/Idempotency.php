<?php

namespace App\Http;

use App\Models\ServiceAccount;
use Closure;
use EduKSX\Core\Http\ProblemException;
use Illuminate\Database\QueryException;
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

        $stored = self::find($account, $key);
        if ($stored !== null) {
            return self::replay($stored, $hash);
        }

        // abgelaufener Eintrag mit demselben Schlüssel: Platz machen
        DB::table('api_idempotency_keys')->where('service_account_id', $account->id)->where('idempotency_key', $key)
            ->where('created_at', '<', now()->subHours(self::HOURS))->delete();

        $response = $action();
        if ($response->getStatusCode() >= 500) {
            return $response;   // Serverfehler nicht festhalten: eine Wiederholung darf es nochmals versuchen
        }
        try {
            DB::table('api_idempotency_keys')->insert([
                'service_account_id' => $account->id,
                'idempotency_key' => $key,
                'request_hash' => $hash,
                'response_status' => $response->getStatusCode(),
                'response_body' => (string) $response->getContent(),
                'created_at' => now(),
            ]);
        } catch (QueryException) {
            // gleichzeitige Anfrage mit demselben Schlüssel: die erste gewinnt
            $stored = self::find($account, $key);
            if ($stored !== null) {
                return self::replay($stored, $hash);
            }
        }

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

        return JsonResponse::fromJsonString($stored['response_body'], $stored['response_status'], [
            'Idempotency-Replayed' => 'true',
            'Cache-Control' => 'no-store',
        ]);
    }
}
