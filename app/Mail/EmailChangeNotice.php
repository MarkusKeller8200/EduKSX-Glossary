<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Hinweis an die bisherige Adresse, dass eine Änderung angefragt wurde (#416). */
class EmailChangeNotice extends Mailable
{
    use Queueable;

    public function __construct(public string $neu) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Änderung Ihrer E-Mail-Adresse – '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.email-change-notice');
    }
}
