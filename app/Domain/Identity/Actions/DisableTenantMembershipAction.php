<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\TenantUser;

/**
 * Disabling a membership revokes access immediately, not just at next
 * login: every token minted for that user+tenant is deleted outright.
 * Users are soft-disabled, never deleted (CLAUDE.md invariant #6) — this
 * only touches the membership/tokens, never the user record's identity.
 *
 * No HTTP endpoint calls this yet: "who is allowed to disable a member" is
 * an RBAC question (S1-ANA-03). This exists now, tested, so that endpoint
 * is a thin controller wrapping an already-correct action once roles land.
 */
class DisableTenantMembershipAction
{
    public function execute(TenantUser $membership): void
    {
        if ($membership->status !== 'disabled') {
            $membership->update(['status' => 'disabled', 'disabled_at' => now()]);
        }

        $membership->user->tokens()->where('tenant_id', $membership->tenant_id)->delete();
    }
}
