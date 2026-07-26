<?php

namespace App\Domain\Sterilization\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Inventory\Enums\AlertSeverity;
use App\Domain\Inventory\Enums\AlertState;
use App\Domain\Inventory\Enums\AlertType;
use App\Domain\Inventory\Models\Alert;
use App\Domain\Sterilization\Enums\ControlTestResult;
use App\Domain\Sterilization\Enums\CycleStatus;
use App\Domain\Sterilization\Models\ControlTest;
use App\Domain\Sterilization\Models\Cycle;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;

/**
 * Allowed any time from Completed through AwaitingRelease — not locked the
 * instant a cycle is submitted, because a biological indicator's result
 * can take 24-48h to incubate and legitimately arrives after submission.
 */
class RecordControlTestAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Cycle $cycle, User $actor, array $attributes): ControlTest
    {
        if (! in_array($cycle->status, [CycleStatus::Completed, CycleStatus::AwaitingRelease], true)) {
            throw new ApiException('CYCLE_NOT_READY_FOR_TESTS', 'Control tests can only be recorded once a cycle has completed running.', 409);
        }

        $test = $cycle->controlTests()->create($attributes + ['tenant_id' => $cycle->tenant_id]);

        $this->recordAuditEvent->execute(
            tenant: $cycle->tenant,
            actor: $actor,
            action: 'control_test.recorded',
            subjectType: ControlTest::class,
            subjectId: $test->id,
            newValues: $attributes,
            request: $this->request,
        );

        if ($test->result === ControlTestResult::Fail) {
            Alert::firstOrCreate(
                [
                    'tenant_id' => $cycle->tenant_id,
                    'type' => AlertType::FailedCycle,
                    'subject_type' => Cycle::class,
                    'subject_id' => $cycle->id,
                    'state' => AlertState::Open,
                ],
                [
                    'severity' => AlertSeverity::Critical,
                    'message' => "Cycle #{$cycle->cycle_number} failed a {$test->type->value} control test.",
                ],
            );
        }

        return $test;
    }
}
