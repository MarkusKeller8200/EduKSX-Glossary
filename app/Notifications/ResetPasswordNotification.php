<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/** Link «Passwort zurücksetzen» (#41) auf Deutsch und über die Warteschlange (Wiederholung, #40). */
class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function toMail(mixed $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Passwort zurücksetzen – '.config('app.name'))
            ->markdown('mail.reset-password', ['url' => $url]);
    }
}
