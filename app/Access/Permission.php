<?php

namespace App\Access;

/**
 * Rechte des Glossary (docs/KONZEPT-BENUTZER.md Abschnitt 3, DEC-017). Rollen bündeln Rechte; geprüft wird immer das
 * Recht (Gate), nie der Rollenname. Drei Schutzklassen:
 *
 * - normal: durch die Rollenverwaltung anpassbar
 * - privileged: nur innerhalb der eigenen Verwaltungsgrenze vergebbar, niemand erhöht eigene Rechte
 * - system: nur Developer, nicht über die Rollen-Matrix änderbar
 */
final class Permission
{
    public const NORMAL = 'normal';

    public const PRIVILEGED = 'privileged';

    public const SYSTEM = 'system';

    /** @var array<string, array{label: string, class: string}> */
    public const ALL = [
        'propose' => ['label' => 'Vorschläge einreichen', 'class' => self::NORMAL],
        'export' => ['label' => 'Exportieren (Excel, CSV, PDF)', 'class' => self::NORMAL],
        'personal-display' => ['label' => 'Persönliche Darstellung', 'class' => self::NORMAL],
        'moderate' => ['label' => 'Vorschläge prüfen', 'class' => self::NORMAL],
        'edit-terms' => ['label' => 'Begriffe bearbeiten (Entwurf)', 'class' => self::NORMAL],
        'publish' => ['label' => 'Veröffentlichen und zurückziehen', 'class' => self::PRIVILEGED],
        'edit-official' => ['label' => 'Offizielle Felder ändern', 'class' => self::PRIVILEGED],
        'manage-users' => ['label' => 'Benutzer und Rollen verwalten', 'class' => self::PRIVILEGED],
        'import' => ['label' => 'Excel-Import', 'class' => self::PRIVILEGED],
        'lists' => ['label' => 'Listen (Kategorien, Herkunft) pflegen', 'class' => self::PRIVILEGED],
        'service-tokens' => ['label' => 'Servicekonten und Token verwalten', 'class' => self::PRIVILEGED],
        'system' => ['label' => 'System und Sicherheit', 'class' => self::SYSTEM],
    ];

    /** @return list<string> */
    /**
     * Grundrechte der Rollen Benutzer und Organisations-Admin (#110): ohne Zugang zur Administration und ohne
     * zweiten Faktor. Alle anderen Rechte öffnen /admin und verlangen den zweiten Faktor.
     *
     * @var list<string>
     */
    public const BASIC = ['propose', 'export', 'personal-display'];

    public static function isBasic(string $permission): bool
    {
        return in_array($permission, self::BASIC, true);
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    public static function exists(string $permission): bool
    {
        return isset(self::ALL[$permission]);
    }

    public static function classOf(string $permission): string
    {
        return self::ALL[$permission]['class'] ?? self::SYSTEM;
    }

    /**
     * Rechte, für die die Administration (Bereich /admin) offensteht: alles ausser den Grundrechten (BASIC). Wer eines
     * davon hat, braucht den zweiten Faktor (docs/KONZEPT-BENUTZER.md 5.3).
     *
     * @return list<string>
     */
    public static function privilegedArea(): array
    {
        return array_values(array_diff(self::keys(), self::BASIC));
    }
}
