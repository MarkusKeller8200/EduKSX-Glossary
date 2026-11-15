<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Hinweis an das Konto, dass neue Wiederherstellungscodes erzeugt wurden (#446). */
class RecoveryCodesRenewed extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Neue Wiederherstellungscodes – '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.recovery-codes-renewed', with: ['url' => url('/konto/zwei-faktor')]);
    }
}
