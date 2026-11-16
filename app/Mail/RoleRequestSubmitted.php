<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Hinweis an die Admins: neue Bewerbung als Moderator/in (#460). Ohne Begründung – die steht nur in der Administration. */
class RoleRequestSubmitted extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public string $applicant) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Neue Bewerbung als Moderator/in – '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.role-request-submitted', with: ['url' => url('/admin/anfragen')]);
    }
}
