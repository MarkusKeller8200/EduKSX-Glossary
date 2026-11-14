<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Sicherungen auf dev (#355): vor einem Versuch eine Sicherung auf dem Server anlegen und sie später wieder
 * einspielen – Konten, Rollen und Sitzungen bleiben dabei (DumpRestore::runKeepingAccounts). Nur, wenn
 * `GLOSSARY_DEV_BACKUPS=true` gesetzt ist (nur auf dev, nie auf test oder prod). Die Dateien liegen in
 * `storage/app/backups/dev/` und sind Dumps im Format von `glossary:dump`.
 */
final class DevBackups
{
    /** erlaubte Dateinamen: nur, was diese Klasse selbst anlegt */
    private const NAME = '/^(sicherung|vor-wiederherstellung)-\d{8}T\d{6}Z(-[a-z0-9-]{1,40})?\.zip$/';

    public static function enabled(): bool
    {
        return (bool) config('glossary.dev_backups', false);
    }

    public static function dir(): string
    {
        return storage_path('app/backups/dev');
    }

    /**
     * Sicherungen, neueste zuerst.
     *
     * @return list<array{name: string, groesse: int, erstellt: string, version: string|null, zeilen: int|null, verschluesselt: bool, automatisch: bool}>
     */
    public static function list(): array
    {
        $files = glob(self::dir().'/*.zip') ?: [];
        $out = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (preg_match(self::NAME, $name) !== 1) {
                continue;
            }
            $manifest = self::manifest($file);
            $out[] = [
                'name' => $name,
                'groesse' => (int) filesize($file),
                'erstellt' => is_string($manifest['created_at'] ?? null) ? $manifest['created_at'] : date(DATE_ATOM, (int) filemtime($file)),
                'version' => is_string($manifest['app_version'] ?? null) ? $manifest['app_version'] : null,
                'zeilen' => is_array($manifest['tables'] ?? null) ? (int) array_sum($manifest['tables']) : null,
                'verschluesselt' => $manifest === null,
                'automatisch' => str_starts_with($name, 'vor-wiederherstellung-'),
            ];
        }
        usort($out, fn (array $a, array $b): int => strcmp($b['erstellt'], $a['erstellt']));

        return $out;
    }

    /**
     * Sicherung anlegen; mit Passwort AES-256.
     *
     * @return array{name: string, manifest: array<string, mixed>}
     */
    public static function create(?string $password = null, ?string $label = null, string $prefix = 'sicherung'): array
    {
        if (! is_dir(self::dir())) {
            mkdir(self::dir(), 0700, true);
        }
        $label = $label === null ? '' : trim((string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($label)), '-');
        $name = $prefix.'-'.now()->utc()->format('Ymd\THis\Z').($label !== '' ? '-'.mb_substr($label, 0, 40) : '').'.zip';
        $manifest = DatabaseDump::create(self::dir().'/'.$name, $password);

        return ['name' => $name, 'manifest' => $manifest];
    }

    /** Pfad einer vorhandenen Sicherung; nur Namen aus list(), nie Pfade von aussen. */
    public static function path(string $name): string
    {
        $file = self::dir().'/'.basename($name);
        if (preg_match(self::NAME, basename($name)) !== 1 || ! is_file($file)) {
            throw new RuntimeException('Sicherung nicht gefunden.');
        }

        return $file;
    }

    public static function delete(string $name): void
    {
        unlink(self::path($name));
    }

    /** @return array<string, mixed>|null null = verschlüsselt oder nicht lesbar */
    private static function manifest(string $file): ?array
    {
        $zip = new ZipArchive;
        if ($zip->open($file) !== true) {
            return null;
        }
        $content = $zip->getFromName('manifest.json');
        $zip->close();
        $manifest = is_string($content) ? json_decode($content, true) : null;

        return is_array($manifest) ? $manifest : null;
    }
}
