<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Dump aus `glossary:dump` lesen (#268, #313, #353): Manifest, Prüfsummen und Tabellen Zeile für Zeile. Prüft vor jeder
 * Verwendung, dass alle Dateien vorhanden und unverändert sind (SHA-256 aus dem Manifest).
 */
final class DumpArchive
{
    /** @var array<string, mixed> */
    public readonly array $manifest;

    /** @param  array<string, mixed>  $manifest */
    private function __construct(private readonly ZipArchive $zip, array $manifest)
    {
        $this->manifest = $manifest;
    }

    /** @throws RuntimeException wenn die Datei fehlt, das Passwort falsch ist oder eine Prüfsumme nicht stimmt */
    public static function open(string $file, ?string $password = null): self
    {
        $zip = new ZipArchive;
        if (! is_file($file) || $zip->open($file) !== true) {
            throw new RuntimeException('ZIP-Datei lässt sich nicht öffnen.');
        }
        if ($password !== null && $password !== '') {
            $zip->setPassword($password);
        }
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        if (! is_array($manifest) || ! is_array($manifest['tables'] ?? null)) {
            $zip->close();

            throw new RuntimeException('Manifest fehlt oder ist nicht lesbar (Passwort?).');
        }
        if (($manifest['format'] ?? null) !== 1) {
            $zip->close();

            throw new RuntimeException('Unbekanntes Format des Dumps.');
        }
        foreach ((array) ($manifest['files'] ?? []) as $name => $sha) {
            $content = $zip->getFromName((string) $name);
            if ($content === false || hash('sha256', $content) !== $sha) {
                $zip->close();

                throw new RuntimeException("Prüfsumme stimmt nicht: {$name} – Dump beschädigt oder verändert.");
            }
        }

        return new self($zip, $manifest);
    }

    public function anonymized(): bool
    {
        return ($this->manifest['anonymized'] ?? false) === true;
    }

    /** @return array<string, int> Tabelle → Zeilen laut Manifest */
    public function tables(): array
    {
        return array_map('intval', (array) $this->manifest['tables']);
    }

    /** @return list<string> Migrationen im Dump, sortiert */
    public function migrations(): array
    {
        $names = array_map(fn (array $r): string => (string) $r['migration'], $this->rows('migrations'));
        sort($names);

        return $names;
    }

    /**
     * Zeilen einer Tabelle; Binärwerte (im Dump als `{"base64": …}`) werden zurückverwandelt.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(string $table): array
    {
        $content = $this->zip->getFromName('tabellen/'.$table.'.jsonl');
        if ($content === false) {
            return [];
        }
        $rows = [];
        foreach (explode("\n", $content) as $line) {
            if (trim($line) === '') {
                continue;
            }
            /** @var array<string, mixed> $row */
            $row = (array) json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            $rows[] = array_map(fn ($v) => is_array($v) && isset($v['base64']) ? base64_decode((string) $v['base64']) : $v, $row);
        }

        return $rows;
    }

    public function close(): void
    {
        $this->zip->close();
    }
}
