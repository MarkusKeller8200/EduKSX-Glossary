<?php

namespace App\Sync;

use App\Models\SyncPeer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Verbindung zu einer Gegenstelle im Entwicklungs-Sync (#478, DEC-041 A22): nur HTTPS (lokal und in Tests auch http),
 * ohne Weiterleitungen, mit Zeitlimit; der Dump höchstens 100 MB.
 */
final class PeerClient
{
    public const MAX_DUMP_BYTES = 100 * 1024 * 1024;

    public function __construct(private readonly SyncPeer $peer) {}

    /** Adresse prüfen (SSRF-Schutz): https, ohne Benutzerangaben; http nur für localhost bzw. ausserhalb production. */
    public static function validUrl(string $url): bool
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['query']) || isset($parts['fragment'])) {
            return false;
        }
        if ($parts['scheme'] === 'https') {
            return true;
        }

        return $parts['scheme'] === 'http' && (in_array($parts['host'], ['localhost', '127.0.0.1'], true) || ! app()->environment('production'));
    }

    /**
     * Stand der Gegenstelle mit ihrer Rolle und den Scopes des Tokens.
     *
     * @return array{role: string, scopes: list<string>, terms: array<string, array{0: int, 1: string}>, categories: array<string, string>, sources: array<string, string>}
     */
    public function state(): array
    {
        $json = $this->json($this->request()->get($this->url('/api/sync/state')));
        $role = (string) ($json['role'] ?? '');
        if (! in_array($role, SyncRole::ALL, true)) {
            throw new RuntimeException('Gegenstelle meldet keine gültige Rolle.');
        }

        return [
            'role' => $role,
            'scopes' => array_values(array_map('strval', (array) ($json['scopes'] ?? []))),
            'terms' => array_map(fn (mixed $v): array => [(int) ((array) $v)[0], (string) ((array) $v)[1]], (array) ($json['terms'] ?? [])),
            'categories' => array_map('strval', (array) ($json['categories'] ?? [])),
            'sources' => array_map('strval', (array) ($json['sources'] ?? [])),
        ];
    }

    /** Anonymisierten Dump in die Datei $target laden. */
    public function dump(string $target): void
    {
        $response = $this->request()->timeout(300)->sink($target)->get($this->url('/api/sync/dump'));
        $this->json($response, false);
        if ((int) @filesize($target) > self::MAX_DUMP_BYTES) {
            @unlink($target);
            throw new RuntimeException('Dump der Gegenstelle ist grösser als 100 MB.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function upload(array $payload): array
    {
        return $this->json($this->request()->timeout(120)->post($this->url('/api/sync/upload'), $payload));
    }

    private function request(): PendingRequest
    {
        if (! self::validUrl($this->peer->url)) {
            throw new RuntimeException('Adresse der Gegenstelle ist nicht erlaubt (https).');
        }

        return Http::withToken($this->peer->token)->acceptJson()->timeout(30)->withoutRedirecting()
            ->withHeaders(['User-Agent' => 'EduKSX-Glossary-Sync/'.config('glossary.version')]);
    }

    private function url(string $path): string
    {
        return rtrim($this->peer->url, '/').$path;
    }

    /** @return array<string, mixed> */
    private function json(Response $response, bool $decode = true): array
    {
        if ($response->status() === 401) {
            throw new RuntimeException('Token der Gegenstelle ungültig oder ohne die nötigen Scopes (401).');
        }
        if ($response->status() === 403) {
            throw new RuntimeException('Gegenstelle lehnt ab (403): '.self::detail($response));
        }
        if (! $response->successful()) {
            throw new RuntimeException('Gegenstelle antwortet mit '.$response->status().': '.self::detail($response));
        }
        if (! $decode) {
            return [];
        }
        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Antwort der Gegenstelle ist kein JSON.');
        }

        return $json;
    }

    private static function detail(Response $response): string
    {
        $json = json_decode((string) $response->body(), true);

        return is_array($json) ? mb_substr((string) ($json['detail'] ?? $json['message'] ?? ''), 0, 300) : '';
    }

    /** Verbindungsfehler verständlich melden. */
    public static function describe(\Throwable $e): string
    {
        return $e instanceof ConnectionException ? 'Gegenstelle nicht erreichbar.' : $e->getMessage();
    }
}
