<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Hinweis an den einladenden Org-Admin: Einladung durch Anlegen eines Kontos angenommen (#461). */
class InvitationAccepted extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public string $organization, public string $member) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Einladung angenommen – '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.invitation-accepted', with: ['url' => url('/organisation')]);
    }
}
