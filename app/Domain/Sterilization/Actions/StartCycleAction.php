<?php

namespace App\Domain\Sterilization\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Sterilization\Enums\CycleStatus;
use App\Domain\Sterilization\Models\Cycle;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;

class StartCycleAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(Cycle $cycle, User $actor): Cycle
    {
        if ($cycle->status !== CycleStatus::Draft) {
            throw new ApiException('CYCLE_INVALID_TRANSITION', 'Only a draft cycle can be started.', 409);
        }

        if ($cycle->items()->doesntExist()) {
            throw new ApiException('CYCLE_LOAD_EMPTY', 'At least one item must be in the load before starting the cycle.', 409);
        }

        $cycle->update(['status' => CycleStatus::Running, 'started_at' => now()]);

        $this->recordAuditEvent->execute(
            tenant: $cycle->tenant,
            actor: $actor,
            action: 'cycle.started',
            subjectType: Cycle::class,
            subjectId: $cycle->id,
            request: $this->request,
        );

        return $cycle;
    }
}
