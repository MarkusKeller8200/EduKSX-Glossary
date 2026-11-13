<?php

namespace App\Support;

/**
 * Selbstregistrierung (#302): offen nur mit Schalter «Registrierung offen» (Einstellungen). Mit E-Mail braucht es
 * zusätzlich den Versand (Bestätigungslink); mit Microsoft nicht. «Passwort vergessen» braucht nur den Versand.
 */
final class Registration
{
    /** Neue Konten mit E-Mail und Passwort (Double-Opt-in). */
    public static function open(): bool
    {
        return self::allowed() && MailSetup::configured();
    }

    /** Neue Konten überhaupt erlaubt (Schalter), z. B. über Microsoft. */
    public static function allowed(): bool
    {
        return Settings::bool('registration.open');
    }
}
