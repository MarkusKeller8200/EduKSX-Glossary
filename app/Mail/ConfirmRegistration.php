<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Bestätigungslink der Registrierung (Double-Opt-in, #41); 60 Minuten gültig. Ohne weitere Personendaten. */
class ConfirmRegistration extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'E-Mail-Adresse bestätigen – '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.confirm-registration');
    }
}
