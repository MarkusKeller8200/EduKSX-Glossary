<?php

namespace App\Support;

use App\Access\Audit;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Mail-Versand aus der Administration (#219). Gespeichert in `settings` unter MailSettings::KEY, das Passwort
 * verschlüsselt mit APP_KEY und nie wieder ausgegeben. Werte aus der Umgebung (MAIL_MAILER bzw. MAIL_*_FILE) haben
 * Vorrang: dann gilt die Umgebung und die Einstellung in der Administration ist gesperrt.
 */
final class MailSettings
{
    public const KEY = 'mail.versand';

    /** Mailer, die in der Administration wählbar sind. */
    public const MAILERS = ['smtp', 'log'];

    /** Felder, die im Audit-Protokoll erscheinen dürfen (nie das Passwort). */
    private const AUDIT_FIELDS = ['mailer', 'host', 'port', 'scheme', 'username', 'from_address', 'from_name'];

    /** Der Versand ist über die Umgebung eingestellt (Server, Komodo, Secrets; config/glossary.php mail_env). */
    public static function fromEnvironment(): bool
    {
        return (bool) config('glossary.mail_env', false);
    }

    /**
     * Gespeicherte Einstellung, Passwort entschlüsselt; null ohne Eintrag oder vor der Migration.
     *
     * @return array{mailer: string, host: string, port: int|null, scheme: string, username: string, password: string, from_address: string, from_name: string, verified_at: string|null, password_unreadable: bool}|null
     */
    public static function stored(): ?array
    {
        try {
            $raw = DB::table('settings')->where('key', self::KEY)->value('value');
        } catch (QueryException) {
            return null;
        }
        if (! is_string($raw)) {
            return null;
        }
        $v = json_decode($raw, true);
        if (! is_array($v)) {
            return null;
        }
        $password = '';
        $unreadable = false;
        if (is_string($v['password'] ?? null) && $v['password'] !== '') {
            try {
                $password = Crypt::decryptString($v['password']);
            } catch (DecryptException) {
                // anderer APP_KEY (z. B. Datenbank von einem anderen Server): Passwort neu eingeben
                $unreadable = true;
            }
        }

        return [
            'mailer' => in_array($v['mailer'] ?? null, self::MAILERS, true) ? (string) $v['mailer'] : 'log',
            'host' => (string) ($v['host'] ?? ''),
            'port' => isset($v['port']) ? (int) $v['port'] : null,
            'scheme' => (string) ($v['scheme'] ?? ''),
            'username' => (string) ($v['username'] ?? ''),
            'password' => $password,
            'from_address' => (string) ($v['from_address'] ?? ''),
            'from_name' => (string) ($v['from_name'] ?? ''),
            'verified_at' => isset($v['verified_at']) ? (string) $v['verified_at'] : null,
            'password_unreadable' => $unreadable,
        ];
    }

    /**
     * Gespeicherte Einstellung in die Konfiguration übernehmen – einmal je Anwendungsinstanz, erst wenn Mail gebraucht
     * wird (Mail-Manager, MailSetup). Nicht beim Start: sonst gelangte das Passwort mit `config:cache` in die Datei.
     */
    public static function apply(bool $force = false): void
    {
        if (! $force && app()->bound('glossary.mail.applied')) {
            return;
        }
        app()->instance('glossary.mail.applied', true);
        if (self::fromEnvironment()) {
            return;
        }
        $s = self::stored();
        if ($s === null) {
            return;
        }
        $config = [
            'mail.default' => $s['mailer'],
            'mail.from.address' => $s['from_address'],
            'mail.from.name' => $s['from_name'] !== '' ? $s['from_name'] : (string) config('app.name'),
        ];
        if ($s['mailer'] === 'smtp') {
            $config += [
                'mail.mailers.smtp.host' => $s['host'],
                'mail.mailers.smtp.port' => $s['port'],
                'mail.mailers.smtp.scheme' => $s['scheme'] !== '' ? $s['scheme'] : null,
                'mail.mailers.smtp.username' => $s['username'] !== '' ? $s['username'] : null,
                'mail.mailers.smtp.password' => $s['password'] !== '' ? $s['password'] : null,
            ];
        }
        config($config);
        if (app()->resolved('mail.manager')) {
            Mail::purge($s['mailer']);
        }
    }

    /** Herkunft der wirksamen Einstellung. */
    public static function source(): string
    {
        if (self::fromEnvironment()) {
            return 'umgebung';
        }

        return self::stored() !== null ? 'administration' : 'vorgabe';
    }

    /** In der Administration eingestellt, aber noch ohne erfolgreiche Testmail. */
    public static function unverified(): bool
    {
        if (self::fromEnvironment()) {
            return false;
        }
        $s = self::stored();

        return $s !== null && $s['verified_at'] === null;
    }

    /**
     * Werte für das Formular – ohne Passwort.
     *
     * @return array{quelle: string, mailer: string, host: string, port: int|null, scheme: string, username: string, passwort_gesetzt: bool, passwort_unlesbar: bool, from_address: string, from_name: string, geprueft_am: string|null}
     */
    public static function form(): array
    {
        $s = self::stored();

        return [
            'quelle' => self::source(),
            'mailer' => $s['mailer'] ?? 'smtp',
            'host' => $s['host'] ?? '',
            'port' => $s['port'] ?? 587,
            'scheme' => $s['scheme'] ?? '',
            'username' => $s['username'] ?? '',
            'passwort_gesetzt' => ($s['password'] ?? '') !== '',
            'passwort_unlesbar' => $s['password_unreadable'] ?? false,
            'from_address' => $s['from_address'] ?? '',
            'from_name' => $s['from_name'] ?? '',
            'geprueft_am' => $s['verified_at'] ?? null,
        ];
    }

    /**
     * Einstellung speichern. Leeres Passwort = bisheriges behalten, ausser $removePassword.
     *
     * @param  array{mailer: string, host?: string|null, port?: int|null, scheme?: string|null, username?: string|null, password?: string|null, from_address: string, from_name?: string|null}  $values
     */
    public static function save(User $actor, array $values, bool $removePassword = false): void
    {
        $old = self::stored();
        $password = (string) ($values['password'] ?? '');
        $passwordState = 'unverändert';
        if ($removePassword) {
            $encrypted = '';
            $passwordState = 'gelöscht';
        } elseif ($password !== '') {
            $encrypted = Crypt::encryptString($password);
            $passwordState = 'neu gesetzt';
        } else {
            $encrypted = ($old['password'] ?? '') !== '' ? Crypt::encryptString((string) $old['password']) : '';
        }

        $new = [
            'mailer' => $values['mailer'],
            'host' => (string) ($values['host'] ?? ''),
            'port' => $values['port'] ?? null,
            'scheme' => (string) ($values['scheme'] ?? ''),
            'username' => (string) ($values['username'] ?? ''),
            'from_address' => $values['from_address'],
            'from_name' => (string) ($values['from_name'] ?? ''),
        ];
        DB::table('settings')->updateOrInsert(['key' => self::KEY], [
            // nach jeder Änderung erst wieder nach einer erfolgreichen Testmail als eingerichtet
            'value' => json_encode($new + ['password' => $encrypted, 'verified_at' => null], JSON_THROW_ON_ERROR),
            'updated_by_id' => $actor->id,
            'updated_at' => now(),
        ]);

        $audit = array_intersect_key($new, array_flip(self::AUDIT_FIELDS));
        Audit::log($actor, null, 'system.mail.changed', $audit + ['passwort' => $passwordState]);
        self::apply(force: true);
    }

    /** Erfolgreiche Testmail vermerken. */
    public static function markVerified(): void
    {
        $raw = DB::table('settings')->where('key', self::KEY)->value('value');
        if (! is_string($raw)) {
            return;
        }
        $v = json_decode($raw, true);
        if (! is_array($v)) {
            return;
        }
        $v['verified_at'] = now()->toIso8601String();
        DB::table('settings')->where('key', self::KEY)->update(['value' => json_encode($v, JSON_THROW_ON_ERROR)]);
    }

    /** Einstellung aus der Administration entfernen; danach gelten wieder die Vorgaben. */
    public static function remove(User $actor): void
    {
        DB::table('settings')->where('key', self::KEY)->delete();
        Audit::log($actor, null, 'system.mail.removed');
    }
}
