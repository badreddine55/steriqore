<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Identity\Models\TenantUser;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Disabling a membership revokes access immediately, not just at next
 * login: every token minted for that user+tenant is deleted outright.
 * Users are soft-disabled, never deleted (CLAUDE.md invariant #6) — this
 * only touches the membership/tokens, never the user record's identity.
 */
class DisableTenantMembershipAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(TenantUser $membership, User $actor): void
    {
        if ($membership->status !== 'disabled') {
            $membership->update(['status' => 'disabled', 'disabled_at' => now()]);

            $this->recordAuditEvent->execute(
                tenant: $membership->tenant,
                actor: $actor,
                action: 'tenant_user.disabled',
                subjectType: TenantUser::class,
                subjectId: $membership->id,
                request: $this->request,
            );
        }

        $membership->user->tokens()->where('tenant_id', $membership->tenant_id)->delete();
    }
}
