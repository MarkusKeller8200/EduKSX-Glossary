<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Hinweis an die Org-Admins: Konten mit der Domain der Organisation wurden aufgenommen (#462). */
class DomainMembersJoined extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    /** @param  list<string>  $names */
    public function __construct(public string $organization, public string $domain, public array $names) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Konten der Domain aufgenommen – '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.domain-members-joined', with: ['url' => url('/organisation')]);
    }
}
