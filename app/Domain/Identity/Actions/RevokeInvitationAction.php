<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\Invitation;
use App\Support\Api\ApiException;

class RevokeInvitationAction
{
    public function execute(Invitation $invitation): void
    {
        if ($invitation->accepted_at !== null) {
            throw new ApiException('INVITATION_ALREADY_ACCEPTED', 'An accepted invitation cannot be revoked.', 409);
        }

        if ($invitation->revoked_at === null) {
            $invitation->update(['revoked_at' => now()]);
        }
    }
}
