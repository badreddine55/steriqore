<?php

namespace App\Domain\Compliance\Actions;

use App\Domain\Compliance\Models\NonConformity;
use App\Models\User;
use App\Support\Api\ApiException;
use App\Support\Dashboard\DashboardCache;
use Illuminate\Http\Request;

/**
 * The "audited correction" — closing out a non-conformity with a note.
 * Does not un-recall labels or un-quarantine batches: those need their
 * own deliberate follow-up action once someone has actually verified the
 * pouch/batch is safe, which isn't something a resolution note alone can
 * assert.
 */
class ResolveNonConformityAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(NonConformity $nonConformity, User $actor, string $resolution): NonConformity
    {
        if ($nonConformity->resolved_at !== null) {
            throw new ApiException('NON_CONFORMITY_ALREADY_RESOLVED', 'This non-conformity has already been resolved.', 409);
        }

        $nonConformity->update([
            'resolved_by_user_id' => $actor->id,
            'resolved_at' => now(),
            'resolution' => $resolution,
        ]);

        $this->recordAuditEvent->execute(
            tenant: $nonConformity->tenant,
            actor: $actor,
            action: 'non_conformity.resolved',
            subjectType: NonConformity::class,
            subjectId: $nonConformity->id,
            reason: $resolution,
            request: $this->request,
        );

        DashboardCache::forget($nonConformity->tenant);

        return $nonConformity;
    }
}
