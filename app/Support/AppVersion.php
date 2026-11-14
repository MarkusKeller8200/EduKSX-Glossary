<?php

namespace App\Support;

/**
 * Version des Glossary (docs/KONZEPT-VERSIONIERUNG.md). Übernommen aus sek-intranet app/Support/AppVersion.php
 * und Release.php (planned), ohne die Mitteilungen «Für Mitarbeitende».
 *
 * Nummer aus der Datei VERSION, Build (Commit) und Zeitpunkt aus dem Deployment (GLOSSARY_BUILD,
 * GLOSSARY_BUILD_DATE), Änderungen aus CHANGELOG.md. Developer Release: ausgerollter Stand mit Commits nach dem Tag
 * v<VERSION>; das Deployment übergibt dazu `git describe --tags --long` gegen den letzten Release-Tag
 * (GLOSSARY_BUILD_DESCRIBE). Bezeichnung: Release `X.Y.Z`, Beta `X.Y.Z-beta.N`, Developer `X.Y.Z-dev.N` mit der
 * geplanten nächsten Version und der Zahl der Commits seit dem letzten Release (#308).
 */
final class AppVersion
{
    public const SEMVER = '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(-[0-9A-Za-z.-]+)?$/';

    public const UNRELEASED = '## [Unveröffentlicht]';

    /**
     * Quellcode genau dieser Version (AGPL-3.0 §13, #336): Release-Tag, bei einem Developer Release der Commit des
     * Builds, sonst das Repository.
     */
    public static function sourceUrl(): string
    {
        $base = rtrim((string) config('glossary.source_url', ''), '/');
        $info = self::info();
        if ($info['dev'] === null && preg_match(self::SEMVER, $info['version']) === 1) {
            return $base.'/tree/v'.$info['version'];
        }
        $build = (string) ($info['build'] ?? '');
        if (preg_match('/([0-9a-f]{7,40})$/', $build, $m) === 1) {
            return $base.'/tree/'.$m[1];
        }

        return $base;
    }

    /**
     * @return array{version: string, build: string|null, build_date: string|null, prototype: bool, dev: array{commits: int, base: string, planned: string, label: string}|null}
     */
    public static function info(): array
    {
        $version = (string) config('glossary.version', '0.0.0');
        $build = config('glossary.build');
        $date = config('glossary.build_date');
        $describe = config('glossary.build_describe');

        return [
            'version' => $version,
            'build' => is_string($build) && $build !== '' ? $build : null,
            'build_date' => is_string($date) && $date !== '' ? $date : null,
            'prototype' => str_starts_with($version, '0.'),
            'dev' => self::developerRelease($version, is_string($describe) && $describe !== '' ? $describe : null),
        ];
    }

    /**
     * Aus `git describe --tags --long --match 'v[0-9]*'` (z. B. v0.1.0-5-gabc1234): Commits nach dem Tag und die
     * geplante nächste Version, dazu die Bezeichnung `<geplant>-dev.<Commits>`. Null = genau ein Release bzw. eine
     * Beta oder unbekannt.
     *
     * @return array{commits: int, base: string, planned: string, label: string}|null
     */
    public static function developerRelease(string $version, ?string $describe): ?array
    {
        if ($describe === null || preg_match('/^v(.+)-(\d+)-g[0-9a-f]+$/', trim($describe), $m) !== 1) {
            return null;
        }
        $commits = (int) $m[2];
        if ($commits === 0 && $m[1] === $version) {
            return null;
        }
        $changelog = (string) @file_get_contents(base_path('CHANGELOG.md'));
        $planned = self::planned($version, $changelog);

        return ['commits' => $commits, 'base' => $m[1], 'planned' => $planned, 'label' => "{$planned}-dev.{$commits}"];
    }

    /** Nächste Version: Patch, wenn unter «Unveröffentlicht» nur «Behoben» steht, sonst Minor. */
    public static function planned(string $version, string $changelog): string
    {
        if (preg_match(self::SEMVER, $version, $m) !== 1) {
            return $version;
        }
        if (($m[4] ?? '') !== '') {
            return "{$m[1]}.{$m[2]}.{$m[3]}";
        }
        $start = strpos($changelog, self::UNRELEASED);
        $text = '';
        if ($start !== false) {
            $rest = substr($changelog, $start + strlen(self::UNRELEASED));
            $end = preg_match('/^## \[/m', $rest, $n, PREG_OFFSET_CAPTURE) === 1 ? $n[0][1] : strlen($rest);
            $text = substr($rest, 0, $end);
        }
        preg_match_all('/^### (.+)$/mu', $text, $found);
        $titles = array_map('trim', $found[1]);
        $onlyFixes = $titles !== [] && array_diff($titles, ['Behoben']) === [];

        return $onlyFixes ? "{$m[1]}.{$m[2]}.".((int) $m[3] + 1) : "{$m[1]}.".((int) $m[2] + 1).'.0';
    }

    /**
     * CHANGELOG.md als Liste von Versionen mit Abschnitten und Einträgen; «Unveröffentlicht» nur mit Einträgen.
     *
     * @return list<array{version: string, date: string|null, sections: list<array{title: string, items: list<string>}>}>
     */
    public static function changelog(): array
    {
        $path = base_path('CHANGELOG.md');
        if (! is_file($path)) {
            return [];
        }

        return array_values(array_filter(
            self::parse((string) file_get_contents($path)),
            fn (array $r): bool => $r['version'] !== 'Unveröffentlicht' || $r['sections'] !== [],
        ));
    }

    /**
     * Text eines CHANGELOG in Versionen, Abschnitte und Einträge zerlegen (Einträge unverändert).
     *
     * @return list<array{version: string, date: string|null, sections: list<array{title: string, items: list<string>}>}>
     */
    public static function parse(string $text): array
    {
        $releases = [];
        $current = null;
        $item = null;

        foreach ([...(preg_split('/\R/', $text) ?: []), ''] as $line) {
            // Fortsetzungszeile eines Eintrags (eingerückt)
            if ($item !== null && preg_match('/^\s{2,}(\S.*)$/u', $line, $m) === 1) {
                $item .= ' '.$m[1];

                continue;
            }
            if ($item !== null && $current !== null) {
                $current = self::addItem($current, $item);
            }
            $item = null;

            if (preg_match('/^## \[([^\]]+)\](?:\s+[–-]\s+(\S+))?/u', $line, $m) === 1) {
                if ($current !== null) {
                    $releases[] = $current;
                }
                $current = ['version' => $m[1], 'date' => $m[2] ?? null, 'sections' => []];
            } elseif ($current !== null && preg_match('/^### (.+)$/u', $line, $m) === 1) {
                $current['sections'][] = ['title' => trim($m[1]), 'items' => []];
            } elseif ($current !== null && preg_match('/^- (.+)$/u', $line, $m) === 1) {
                $item = $m[1];
            }
        }
        if ($current !== null) {
            $releases[] = $current;
        }

        return $releases;
    }

    /**
     * @param  array{version: string, date: string|null, sections: list<array{title: string, items: list<string>}>}  $release
     * @return array{version: string, date: string|null, sections: list<array{title: string, items: list<string>}>}
     */
    private static function addItem(array $release, string $item): array
    {
        if ($release['sections'] === []) {
            $release['sections'][] = ['title' => 'Änderungen', 'items' => []];
        }
        $last = count($release['sections']) - 1;
        $release['sections'][$last]['items'][] = trim($item);

        return $release;
    }
}
