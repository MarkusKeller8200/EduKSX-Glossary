<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Hinweis vor der Löschung eines inaktiven Community-Kontos (#42, Löschkonzept). */
class InactivityNotice extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public string $deleteOn) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Ihr Konto wird gelöscht – '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.inactivity-notice', with: ['url' => url('/anmelden')]);
    }
}
