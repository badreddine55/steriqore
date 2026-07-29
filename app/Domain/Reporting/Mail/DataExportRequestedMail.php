<?php

namespace App\Domain\Reporting\Mail;

use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * UI-29's out-of-band control: sent to every owner whenever a full-account
 * data export is requested, including when the requester is an owner
 * themselves — a compromised admin/owner quietly exfiltrating a practice's
 * data must be visible outside the audit log too. Mirrors DigestMail's
 * shape (ShouldQueue, envelope()/content() style).
 */
class DataExportRequestedMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $recipient,
        public Tenant $tenant,
        public User $requestedBy,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "SteriQore — {$this->tenant->name}: data export requested",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.data-export-requested',
            with: [
                'recipientName' => $this->recipient->name,
                'tenantName' => $this->tenant->name,
                'requestedByName' => $this->requestedBy->name,
                'isSelf' => $this->requestedBy->is($this->recipient),
            ],
        );
    }
}
