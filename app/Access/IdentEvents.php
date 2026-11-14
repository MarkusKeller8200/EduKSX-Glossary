<?php

namespace App\Access;

use App\Models\User;
use App\Models\UserIdentity;
use EduKSX\Core\Events\PlatformEventReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Ereignisse von EduKSX-Ident verarbeiten (#187, Plattform S4). Empfang, Signatur und Idempotenz übernimmt
 * eduksx/core; hier nur die Wirkung im Glossary – dieselbe wie bei der Verwaltung (AccountManager, Akteur «Server»):
 *
 * - `ident.user.deleted`: Konto löschen wie heute (Sitzungen enden, Beiträge pseudonymisiert).
 * - `ident.user.disabled`: Konto sperren, Sitzungen enden.
 *
 * Zuordnung über die Anmeldung mit Ident (user_identities, provider «ident», `sub` = data.subject). Unbekannte Personen
 * und Schutzregeln (letztes Developer-Konto) gelten als verarbeitet – sonst stellte Ident endlos erneut zu; sie stehen
 * im Protokoll bzw. im Audit.
 */
final class IdentEvents
{
    public const PROVIDER = 'ident';

    public function __construct(private readonly AccountManager $accounts) {}

    public static function register(): void
    {
        Event::listen('eduksx:ident.user.deleted', fn (PlatformEventReceived $e) => app(self::class)->userDeleted($e));
        Event::listen('eduksx:ident.user.disabled', fn (PlatformEventReceived $e) => app(self::class)->userDisabled($e));
    }

    public function userDeleted(PlatformEventReceived $event): void
    {
        $user = $this->user($event);
        if ($user === null) {
            return;
        }
        try {
            $this->accounts->delete(null, $user);
        } catch (AccessDenied $e) {
            Log::warning('glossary.ident.user_deleted_denied', ['event' => $event->id, 'reason' => $e->getMessage()]);
        }
    }

    public function userDisabled(PlatformEventReceived $event): void
    {
        $user = $this->user($event);
        if ($user === null || $user->status === 'blocked') {
            return;
        }
        try {
            $this->accounts->block(null, $user);
        } catch (AccessDenied $e) {
            Log::warning('glossary.ident.user_disabled_denied', ['event' => $event->id, 'reason' => $e->getMessage()]);
        }
    }

    private function user(PlatformEventReceived $event): ?User
    {
        $subject = $event->data['subject'] ?? null;
        $identity = is_string($subject)
            ? UserIdentity::query()->where('provider', self::PROVIDER)->where('subject', $subject)->first()
            : null;
        if ($identity === null) {
            Log::info('glossary.ident.unknown_user', ['event' => $event->id, 'type' => $event->type]);

            return null;
        }

        return User::query()->find($identity->user_id);
    }
}
