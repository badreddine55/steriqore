<?php

namespace App\Policies;

use App\Domain\Identity\Models\Invitation;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;

class InvitationPolicy
{
    public function create(User $user): bool
    {
        return $user->can('invitations.create');
    }

    /**
     * Invitation isn't BelongsToTenant-scoped (see its docblock — it's
     * resolved during auth bootstrap), so unlike Site the tenant match has
     * to be checked here, not just relied on from a query scope.
     */
    public function delete(User $user, Invitation $invitation): bool
    {
        return $invitation->tenant_id === Tenant::current()?->id && $user->can('invitations.revoke');
    }
}
