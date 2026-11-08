<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Einladung in eine Organisation (#148): Konto anlegen oder anmelden, die Zuordnung folgt automatisch. */
class OrganizationInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public string $organization, public string $invitedBy, public string $validUntil) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Einladung in die Organisation «'.$this->organization.'» – '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.organization-invitation', with: [
            'register' => url('/registrieren'),
            'login' => url('/anmelden'),
        ]);
    }
}
