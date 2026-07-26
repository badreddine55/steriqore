<?php

namespace App\Domain\Sterilization\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Sterilization\Enums\CycleStatus;
use App\Domain\Sterilization\Models\Cycle;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;

/**
 * Deliberately does NOT require any specific control test to be recorded
 * first — which tests are mandatory (daily Bowie-Dick? weekly biological?)
 * is a regulatory rule this codebase must not invent (see OQ-7). Gaps are
 * surfaced as an advisory alert instead of a hard block once the missing
 * -test alert exists.
 */
class SubmitCycleForReleaseAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(Cycle $cycle, User $actor): Cycle
    {
        if ($cycle->status !== CycleStatus::Completed) {
            throw new ApiException('CYCLE_INVALID_TRANSITION', 'Only a completed cycle can be submitted for release.', 409);
        }

        $cycle->update(['status' => CycleStatus::AwaitingRelease]);

        $this->recordAuditEvent->execute(
            tenant: $cycle->tenant,
            actor: $actor,
            action: 'cycle.submitted_for_release',
            subjectType: Cycle::class,
            subjectId: $cycle->id,
            request: $this->request,
        );

        return $cycle;
    }
}
