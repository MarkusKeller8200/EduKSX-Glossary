<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Testmail aus der Systemseite (#40): prüft die Einstellung des Versands ohne Registrierung. */
class TestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Wiederholen, falls der Dienst kurz nicht erreichbar ist. */
    public int $tries = 3;

    /** @var list<int> Sekunden bis zum nächsten Versuch */
    public array $backoff = [60, 300];

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Testmail – '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.test');
    }
}
