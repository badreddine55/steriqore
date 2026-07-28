<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Not evidence-affecting — a plain audited update (old/new values +
 * reason), not versioned like the DLU rule table or label format.
 */
class UpdatePracticeIdentityAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Tenant $tenant, User $actor, array $attributes, string $reason): Tenant
    {
        $oldValues = $tenant->only(array_keys($attributes));
        $tenant->update($attributes);

        $this->recordAuditEvent->execute(
            tenant: $tenant,
            actor: $actor,
            action: 'tenant.identity_updated',
            subjectType: Tenant::class,
            subjectId: $tenant->id,
            oldValues: $oldValues,
            newValues: $attributes,
            reason: $reason,
            request: $this->request,
        );

        return $tenant;
    }
}
