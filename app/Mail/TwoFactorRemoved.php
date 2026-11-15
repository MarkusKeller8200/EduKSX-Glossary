<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Hinweis an das Konto, dass der zweite Faktor entfernt wurde (#403, #404) – durch einen Organisations-Admin oder
 * über den Link per Mail. Wer das nicht selbst veranlasst hat, soll sich melden.
 */
class TwoFactorRemoved extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public string $by) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Zweiter Faktor entfernt – '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.two-factor-removed', with: ['url' => url('/konto/zwei-faktor')]);
    }
}
