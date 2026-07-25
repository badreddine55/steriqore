<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Mail\InvitationMail;
use App\Domain\Identity\Models\Invitation;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CreateInvitationAction
{
    public function execute(Tenant $tenant, User $invitedBy, string $email, string $role): Invitation
    {
        if ($tenant->users()->where('email', $email)->exists()) {
            throw new ApiException('USER_ALREADY_MEMBER', 'This person already has access to this practice.', 409);
        }

        $alreadyPending = Invitation::where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->exists();

        if ($alreadyPending) {
            throw new ApiException('INVITATION_ALREADY_PENDING', 'An invitation is already pending for this email.', 409);
        }

        $invitation = Invitation::create([
            'tenant_id' => $tenant->id,
            'email' => $email,
            'role' => $role,
            'token' => Str::random(64),
            'invited_by' => $invitedBy->id,
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($email)->queue(new InvitationMail($invitation));

        return $invitation;
    }
}
