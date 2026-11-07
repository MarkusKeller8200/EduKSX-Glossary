<?php

namespace App\Support;

/**
 * Stand des E-Mail-Versands (#40, docs/KONZEPT-BENUTZER.md 7). Der Dienst wird nur über die Umgebung eingestellt
 * (MAIL_MAILER, MAIL_HOST …); ohne echten Versand bleibt die Selbstregistrierung ausgeschaltet.
 */
final class MailSetup
{
    /** Mailer, die nichts versenden. */
    private const NOT_SENDING = ['log', 'array', 'null'];

    /** Ein echter Versand ist eingerichtet (Mailer sendet und Absender ist gesetzt). */
    public static function configured(): bool
    {
        $mailer = (string) config('mail.default');
        $from = (string) config('mail.from.address');

        return ! in_array($mailer, self::NOT_SENDING, true)
            && $from !== '' && ! str_ends_with($from, '@example.com');
    }

    /**
     * Angaben für die Systemseite – ohne Zugangsdaten.
     *
     * @return array{mailer: string, host: string|null, port: int|null, scheme: string|null, from: string, from_name: string, queue: string, configured: bool}
     */
    public static function info(): array
    {
        $mailer = (string) config('mail.default');
        /** @var array<string, mixed> $settings */
        $settings = (array) config("mail.mailers.{$mailer}", []);

        return [
            'mailer' => $mailer,
            'host' => isset($settings['host']) ? (string) $settings['host'] : null,
            'port' => isset($settings['port']) ? (int) $settings['port'] : null,
            'scheme' => isset($settings['scheme']) ? (string) $settings['scheme'] : null,
            'from' => (string) config('mail.from.address'),
            'from_name' => (string) config('mail.from.name'),
            'queue' => (string) config('queue.default'),
            'configured' => self::configured(),
        ];
    }
}
