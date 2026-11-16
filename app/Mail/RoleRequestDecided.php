<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Entscheid über die Bewerbung als Moderator/in (#460). */
class RoleRequestDecided extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public bool $accepted, public ?string $note) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->accepted ? 'Willkommen in der Moderation – ' : 'Ihre Bewerbung als Moderator/in – ').config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.role-request-decided', with: ['url' => url($this->accepted ? '/admin' : '/konto')]);
    }
}
