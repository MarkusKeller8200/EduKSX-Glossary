<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Bestätigung der neuen E-Mail-Adresse (#416); sofort versendet, der Link gilt 60 Minuten. */
class ConfirmEmailChange extends Mailable
{
    use Queueable;

    public function __construct(public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Neue E-Mail-Adresse bestätigen – '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.confirm-email-change');
    }
}
