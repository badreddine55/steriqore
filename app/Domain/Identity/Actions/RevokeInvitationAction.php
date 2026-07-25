<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Identity\Models\Invitation;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;

class RevokeInvitationAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(Invitation $invitation, User $actor): void
    {
        if ($invitation->accepted_at !== null) {
            throw new ApiException('INVITATION_ALREADY_ACCEPTED', 'An accepted invitation cannot be revoked.', 409);
        }

        if ($invitation->revoked_at === null) {
            $invitation->update(['revoked_at' => now()]);

            $this->recordAuditEvent->execute(
                tenant: $invitation->tenant,
                actor: $actor,
                action: 'invitation.revoked',
                subjectType: Invitation::class,
                subjectId: $invitation->id,
                request: $this->request,
            );
        }
    }
}
