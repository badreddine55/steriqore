<?php

namespace App\Policies;

use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;

class TenantUserPolicy
{
    /**
     * TenantUser isn't BelongsToTenant-scoped (same bootstrap reason as
     * Invitation), so the tenant match is checked here.
     */
    public function disable(User $user, TenantUser $membership): bool
    {
        return $membership->tenant_id === Tenant::current()?->id && $user->can('memberships.disable');
    }
}
