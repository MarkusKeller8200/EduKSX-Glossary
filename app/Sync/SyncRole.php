<?php

namespace App\Sync;

use App\Support\Settings;

/**
 * Rolle der Instanz im Entwicklungs-Sync (#478, DEC-041 A12, A14, A19): wer von wem holt und wohin hochlädt.
 */
final class SyncRole
{
    public const MASTER = 'master';

    public const TEST = 'test';

    public const DEVELOPMENT = 'development';

    public const INSTANCE = 'instance';

    public const ALL = [self::MASTER, self::TEST, self::DEVELOPMENT, self::INSTANCE];

    /** Gegenstellen, von denen eine Rolle holen darf */
    private const PULL_FROM = [self::DEVELOPMENT => [self::TEST, self::MASTER], self::TEST => [self::MASTER]];

    /** Gegenstellen, zu denen eine Rolle hochladen darf */
    private const UPLOAD_TO = [self::DEVELOPMENT => [self::TEST, self::MASTER], self::TEST => [self::MASTER]];

    public static function current(): string
    {
        $role = Settings::string('sync.role');
        if (! in_array($role, self::ALL, true)) {
            return self::INSTANCE;
        }
        // «Entwicklung» nie in production, auch nicht über die Umgebung (A12)
        if ($role === self::DEVELOPMENT && app()->environment('production')) {
            return self::INSTANCE;
        }

        return $role;
    }

    public static function canPullFrom(string $remote, ?string $own = null): bool
    {
        return in_array($remote, self::PULL_FROM[$own ?? self::current()] ?? [], true);
    }

    public static function canUploadTo(string $remote, ?string $own = null): bool
    {
        return in_array($remote, self::UPLOAD_TO[$own ?? self::current()] ?? [], true);
    }

    /** Darf diese Instanz einen Dump abgeben bzw. Uploads annehmen? */
    public static function isSource(?string $own = null): bool
    {
        return in_array($own ?? self::current(), [self::MASTER, self::TEST], true);
    }

    public static function label(string $role): string
    {
        return match ($role) {
            self::MASTER => 'Master',
            self::TEST => 'Test',
            self::DEVELOPMENT => 'Entwicklung',
            default => 'Instanz',
        };
    }
}
