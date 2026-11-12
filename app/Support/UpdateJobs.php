<?php

namespace App\Support;

use App\Access\Audit;
use App\Models\User;
use InvalidArgumentException;

/**
 * Aufträge an den Update-Dienst (#177, #178, DEC-027 U3/U4/U9): Die App schreibt nur einen Auftrag nach
 * storage/app/updates/auftrag.json; der Dienst «updater» (docker/compose.updater.yaml) führt ihn aus und meldet
 * Stand und Protokoll in denselben Ordner zurück. Die App selbst hat keinen Zugriff auf Docker.
 */
final class UpdateJobs
{
    /** Der Dienst gilt als aktiv, wenn er sich in dieser Zeit gemeldet hat (Sekunden). */
    private const HEARTBEAT = 300;

    public static function dir(): string
    {
        return storage_path('app/updates');
    }

    /** @return array<string, mixed>|null */
    private static function read(string $file): ?array
    {
        $path = self::dir().'/'.$file;
        if (! is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    /** @return array<string, mixed>|null Stand des Dienstes (stand.json) */
    public static function state(): ?array
    {
        return self::read('stand.json');
    }

    public static function serviceActive(): bool
    {
        $seen = strtotime((string) (self::state()['gemeldet'] ?? ''));

        return $seen !== false && time() - $seen < self::HEARTBEAT;
    }

    /** @return array<string, mixed>|null offener Auftrag */
    public static function pending(): ?array
    {
        return self::read('auftrag.json');
    }

    /** @return list<array<string, mixed>> letzte Einträge des Protokolls, neueste zuerst */
    public static function history(int $limit = 20): array
    {
        $path = self::dir().'/protokoll.jsonl';
        if (! is_file($path)) {
            return [];
        }
        $lines = array_slice(array_filter(explode("\n", (string) file_get_contents($path))), -$limit);
        $entries = [];
        foreach (array_reverse($lines) as $line) {
            $e = json_decode($line, true);
            if (is_array($e)) {
                $entries[] = $e;
            }
        }

        return $entries;
    }

    /** Auftrag ablegen; $actor null = automatisch nach Richtlinie. */
    public static function request(?User $actor, string $target, string $reason): void
    {
        if (preg_match('/^\d+\.\d+\.\d+$/', $target) !== 1) {
            throw new InvalidArgumentException('Zielversion ungültig.');
        }
        if (self::pending() !== null) {
            throw new InvalidArgumentException('Es wartet bereits ein Auftrag.');
        }
        if (! is_dir(self::dir())) {
            mkdir(self::dir(), 0775, true);
        }
        $job = [
            'id' => bin2hex(random_bytes(8)),
            'ziel' => $target,
            'von' => (string) config('glossary.version'),
            'anlass' => $reason,
            'angefordert_von' => $actor !== null ? $actor->email : 'automatisch',
            'am' => now()->toIso8601String(),
        ];
        $tmp = self::dir().'/auftrag.json.tmp';
        file_put_contents($tmp, json_encode($job, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        rename($tmp, self::dir().'/auftrag.json');
        Audit::log($actor, null, 'system.update.requested', ['ziel' => $target, 'von' => $job['von'], 'anlass' => $reason], 'ok', $actor === null ? 'automatisch' : null);
    }

    /**
     * Zielversion nach der Richtlinie für automatische Updates (#178, Entscheid 12.11.2026: Vorgabe «nur
     * Korrekturen», abschaltbar; nie automatisch über eine Hauptversion). Null = nichts zu tun.
     */
    public static function automaticTarget(string $installed, ?string $latest, string $policy): ?string
    {
        if ($latest === null || $policy === 'aus' || ! version_compare($latest, $installed, '>')) {
            return null;
        }
        $kind = UpdateCheck::kind($installed, $latest);

        return match ($policy) {
            'korrektur' => $kind === 'korrektur' ? $latest : null,
            'funktion' => $kind !== 'hauptversion' ? $latest : null,
            default => null,
        };
    }
}
