<?php

namespace App\Domain\Sterilization\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Sterilization\Enums\CycleStatus;
use App\Domain\Sterilization\Models\Cycle;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;

class CompleteCycleAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(Cycle $cycle, User $actor): Cycle
    {
        if ($cycle->status !== CycleStatus::Running) {
            throw new ApiException('CYCLE_INVALID_TRANSITION', 'Only a running cycle can be completed.', 409);
        }

        $cycle->update(['status' => CycleStatus::Completed, 'completed_at' => now()]);

        $this->recordAuditEvent->execute(
            tenant: $cycle->tenant,
            actor: $actor,
            action: 'cycle.completed',
            subjectType: Cycle::class,
            subjectId: $cycle->id,
            request: $this->request,
        );

        return $cycle;
    }
}
