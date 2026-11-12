<?php

namespace App\Enums;

/**
 * Ergebnis eines Protokolleintrags.
 * Werte englisch (KONZEPT-KONVENTIONEN K2, #275), Anzeige über label(); Werte bis 0.11.0 über legacyMap().
 */
enum AuditResult: string
{
    use EnumValues;

    case Ok = 'ok';
    case Denied = 'denied';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'ausgeführt',
            self::Denied => 'verweigert',
            self::Failed => 'fehlgeschlagen',
        };
    }

    /** @return array<string, string> Wert bis 0.11.0 → Wert */
    public static function legacyMap(): array
    {
        return [
            'ok' => 'ok',
            'denied' => 'denied',
            'failed' => 'failed',
        ];
    }
}
