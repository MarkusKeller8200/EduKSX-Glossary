<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Link zum Löschen eines Kontos ohne eigenes Passwort (#445); sofort versendet, der Link gilt nur kurz. */
class ConfirmAccountDeletion extends Mailable
{
    use Queueable;

    public function __construct(public string $url, public int $minutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Konto löschen bestätigen – '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.confirm-account-deletion');
    }
}
