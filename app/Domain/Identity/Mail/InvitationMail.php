<?php

namespace App\Domain\Identity\Mail;

use App\Domain\Identity\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvitationMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public Invitation $invitation)
    {
        $this->invitation->loadMissing('tenant');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're invited to join {$this->invitation->tenant->name} on SteriQore",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invitation',
            with: [
                'tenantName' => $this->invitation->tenant->name,
                'role' => $this->invitation->role,
                // The frontend accept-invitation page is not built yet — this
                // is a placeholder path, not a routed page (backend scope
                // only, S1-ANA-02).
                'acceptUrl' => rtrim(config('app.url'), '/').'/accept-invitation?token='.$this->invitation->token,
            ],
        );
    }
}
