<?php

namespace App\Domain\Sterilization\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Sterilization\Enums\CycleReleaseDecision;
use App\Domain\Sterilization\Enums\CycleStatus;
use App\Domain\Sterilization\Models\Cycle;
use App\Domain\Sterilization\Models\CycleRelease;
use App\Models\User;
use App\Support\Api\ApiException;
use App\Support\Dashboard\DashboardCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The only place a cycle_releases row is ever written — there is no
 * update/delete action for it anywhere (CLAUDE.md invariant #3). Running
 * this twice on the same cycle is impossible: after the first call the
 * cycle is no longer AwaitingRelease, so the second attempt fails the
 * status check before it ever reaches the unique cycle_id constraint.
 */
class ReleaseCycleAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(Cycle $cycle, User $actor, CycleReleaseDecision $decision, ?string $reason): CycleRelease
    {
        if ($cycle->status !== CycleStatus::AwaitingRelease) {
            throw new ApiException('CYCLE_INVALID_TRANSITION', 'Only a cycle awaiting release can be released or rejected.', 409);
        }

        return DB::transaction(function () use ($cycle, $actor, $decision, $reason) {
            $release = CycleRelease::create([
                'tenant_id' => $cycle->tenant_id,
                'cycle_id' => $cycle->id,
                'decision' => $decision,
                'released_by_user_id' => $actor->id,
                'released_at' => now(),
                'reason' => $reason,
            ]);

            $cycle->update([
                'status' => $decision === CycleReleaseDecision::Compliant ? CycleStatus::Released : CycleStatus::Rejected,
            ]);

            $this->recordAuditEvent->execute(
                tenant: $cycle->tenant,
                actor: $actor,
                action: $decision === CycleReleaseDecision::Compliant ? 'cycle.released' : 'cycle.rejected',
                subjectType: Cycle::class,
                subjectId: $cycle->id,
                newValues: ['decision' => $decision->value, 'reason' => $reason],
                reason: $reason,
                request: $this->request,
            );

            DashboardCache::forget($cycle->tenant);

            return $release;
        });
    }
}
