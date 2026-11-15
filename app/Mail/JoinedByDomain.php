<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Hinweis an die Person: über die Domain ihrer Adresse in eine Organisation aufgenommen (#462). */
class JoinedByDomain extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public string $organization, public string $domain) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'In eine Organisation aufgenommen – '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.joined-by-domain', with: ['url' => url('/organisation')]);
    }
}
