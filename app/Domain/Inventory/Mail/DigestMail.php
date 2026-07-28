<?php

namespace App\Domain\Inventory\Mail;

use App\Domain\Inventory\Models\Alert;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * UI-28's digest — one email per (tenant, subscribed role) send, listing
 * every currently open alert grouped by type. Mirrors InvitationMail's
 * shape (ShouldQueue, envelope()/content() style).
 */
class DigestMail extends Mailable implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Alert>  $alerts
     */
    public function __construct(
        public User $recipient,
        public Tenant $tenant,
        public Collection $alerts,
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->alerts->count();

        return new Envelope(
            subject: "SteriQore — {$this->tenant->name}: {$count} open alert".($count === 1 ? '' : 's'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.digest',
            with: [
                'recipientName' => $this->recipient->name,
                'tenantName' => $this->tenant->name,
                'alertsByType' => $this->alerts->groupBy(fn ($alert) => $alert->type->value),
            ],
        );
    }
}
