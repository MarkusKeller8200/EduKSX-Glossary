<?php

namespace App\Support;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Mime\Address;
use Throwable;

/**
 * Mail-Protokoll (#463): Was wurde wann an wen (maskiert) versendet, was wartet oder ist fehlgeschlagen. Nie gespeichert
 * oder angezeigt: Inhalt, Betreff, Links, volle Adressen, Fehlermeldungen (können Adressen enthalten).
 */
final class MailLog
{
    public const RETENTION_DAYS = 90;

    /** Art der Mail für die Anzeige */
    public const KINDS = [
        'TestMail' => 'Testmail',
        'ConfirmRegistration' => 'Registrierung bestätigen',
        'ResetPasswordNotification' => 'Passwort zurücksetzen',
        'ConfirmEmailChange' => 'Neue E-Mail-Adresse bestätigen',
        'EmailChangeNotice' => 'Hinweis: E-Mail-Adresse geändert',
        'InactivityNotice' => 'Hinweis vor Löschung (inaktiv)',
        'OrganizationInvitationMail' => 'Einladung in eine Organisation',
        'TwoFactorResetLink' => 'Zweiten Faktor zurücksetzen',
        'TwoFactorRemoved' => 'Hinweis: zweiter Faktor entfernt',
        'RecoveryCodesRenewed' => 'Hinweis: neue Wiederherstellungscodes',
        'ConfirmAccountDeletion' => 'Konto löschen bestätigen',
        'InvitationAccepted' => 'Hinweis: Einladung angenommen',
        'DomainMembersJoined' => 'Hinweis: Konten der Domain aufgenommen',
        'JoinedByDomain' => 'Hinweis: in Organisation aufgenommen',
        'RoleRequestDecided' => 'Bewerbung als Moderator/in entschieden',
    ];

    public static function sent(MessageSent $event): void
    {
        $class = (string) ($event->data['__laravel_mailable'] ?? $event->data['__laravel_notification'] ?? 'Mail');
        $to = array_map(fn (Address $a): string => self::mask($a->getAddress()), $event->message->getTo());
        self::write(class_basename($class), implode(', ', $to), 'sent', null);
    }

    /** Fehlgeschlagene Mail in der Warteschlange (nach allen Versuchen); Empfänger unbekannt, ohne Fehlermeldung. */
    public static function failed(JobFailed $event): void
    {
        $name = (string) ($event->job->payload()['displayName'] ?? '');
        if (! str_starts_with($name, 'App\\Mail\\') && ! str_starts_with($name, 'App\\Notifications\\')) {
            return;
        }
        self::write(class_basename($name), '–', 'failed', class_basename($event->exception));
    }

    private static function write(string $kind, string $recipient, string $status, ?string $error): void
    {
        try {
            DB::table('mail_log')->insert([
                'created_at' => now(), 'kind' => mb_substr($kind, 0, 80), 'recipient' => mb_substr($recipient, 0, 255),
                'status' => $status, 'mailer' => mb_substr((string) config('mail.default'), 0, 20),
                'error' => $error === null ? null : mb_substr($error, 0, 120),
            ]);
        } catch (Throwable) {
            // Protokoll darf den Versand nie stören (z. B. vor der Migration)
        }
    }

    /** m***@schule.ch */
    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', mb_strtolower($email), 2), 2, '');

        return mb_substr($local, 0, 1).'***@'.$domain;
    }

    public static function label(string $kind): string
    {
        return self::KINDS[$kind] ?? $kind;
    }

    /**
     * Für die Systemseite: wartende und fehlgeschlagene Aufträge mit Mails, zuletzt versendete.
     *
     * @return array{wartend: list<array<string, mixed>>, fehlgeschlagen: list<array<string, mixed>>, versendet: list<array<string, mixed>>}
     */
    public static function overview(int $limit = 50): array
    {
        $isMail = fn (string $name): bool => str_starts_with($name, 'App\\Mail\\') || str_starts_with($name, 'App\\Notifications\\');
        $name = function (string $payload): string {
            $data = json_decode($payload, true);

            return is_array($data) ? (string) ($data['displayName'] ?? '') : '';
        };

        $waiting = [];
        foreach (DB::table('jobs')->orderBy('id')->limit(200)->get(['id', 'payload', 'attempts', 'available_at', 'created_at']) as $job) {
            $n = $name((string) $job->payload);
            if ($isMail($n)) {
                $waiting[] = ['id' => (int) $job->id, 'art' => self::label(class_basename($n)), 'versuche' => (int) $job->attempts,
                    'angelegt' => date('c', (int) $job->created_at), 'naechster' => date('c', (int) $job->available_at)];
            }
        }
        $failed = [];
        foreach (DB::table('failed_jobs')->orderByDesc('failed_at')->limit(100)->get(['uuid', 'payload', 'exception', 'failed_at']) as $job) {
            $n = $name((string) $job->payload);
            if ($isMail($n)) {
                // nur die Art des Fehlers (Klassenname), nicht die Meldung – sie kann Adressen enthalten
                $type = strtok((string) $job->exception, ':') ?: 'Fehler';
                $failed[] = ['uuid' => (string) $job->uuid, 'art' => self::label(class_basename($n)),
                    'fehler' => class_basename(trim($type)), 'am' => (string) $job->failed_at];
            }
        }
        $sent = DB::table('mail_log')->orderByDesc('id')->limit($limit)->get()->map(fn ($r): array => [
            'id' => (int) $r->id, 'am' => (string) $r->created_at, 'art' => self::label((string) $r->kind),
            'an' => (string) $r->recipient, 'ergebnis' => (string) $r->status, 'weg' => (string) $r->mailer,
            'fehler' => $r->error,
        ])->all();

        return ['wartend' => $waiting, 'fehlgeschlagen' => $failed, 'versendet' => array_values($sent)];
    }

    /** Einträge älter als die Frist löschen; liefert die Anzahl. */
    public static function prune(): int
    {
        return DB::table('mail_log')->where('created_at', '<', now()->subDays(self::RETENTION_DAYS))->delete();
    }

    /** Fehlgeschlagener Auftrag gehört zu einer Mail? (Schutz für Wiederholen/Verwerfen) */
    public static function isFailedMail(string $uuid): bool
    {
        $payload = DB::table('failed_jobs')->where('uuid', $uuid)->value('payload');
        $data = is_string($payload) ? json_decode($payload, true) : null;
        $n = is_array($data) ? (string) ($data['displayName'] ?? '') : '';

        return str_starts_with($n, 'App\\Mail\\') || str_starts_with($n, 'App\\Notifications\\');
    }
}
