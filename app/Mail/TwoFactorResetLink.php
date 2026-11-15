<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Link zum Zurücksetzen des zweiten Faktors (#403); sofort versendet, der Link gilt nur kurz. */
class TwoFactorResetLink extends Mailable
{
    use Queueable;

    public function __construct(public string $url, public int $minutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Zweiten Faktor zurücksetzen – '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.two-factor-reset-link');
    }
}
