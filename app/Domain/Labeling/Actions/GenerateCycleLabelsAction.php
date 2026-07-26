<?php

namespace App\Domain\Labeling\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Labeling\Models\DluRule;
use App\Domain\Labeling\Models\Label;
use App\Domain\Sterilization\Enums\CycleStatus;
use App\Domain\Sterilization\Models\Cycle;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CLAUDE.md invariant #2: "No label may exist for a cycle that is not
 * released+compliant." Released only ever results from a compliant
 * decision (ReleaseCycleAction never sets it for a rejection), so checking
 * cycle.status === Released is exactly that check.
 */
class GenerateCycleLabelsAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @return Collection<int, Label>
     */
    public function execute(Cycle $cycle, User $actor, string $packagingType, string $storageCondition): Collection
    {
        if ($cycle->status !== CycleStatus::Released) {
            throw new ApiException('CYCLE_NOT_RELEASED', 'Labels can only be generated for a released cycle.', 409);
        }

        if ($cycle->items()->whereHas('label')->exists()) {
            throw new ApiException('CYCLE_LABELS_ALREADY_GENERATED', 'Labels have already been generated for this cycle.', 409);
        }

        $rule = DluRule::where('packaging_type', $packagingType)
            ->where('storage_condition', $storageCondition)
            ->first();

        if ($rule === null) {
            throw new ApiException('DLU_RULE_NOT_FOUND', 'No DLU rule is configured for this packaging/storage combination.', 422);
        }

        return DB::transaction(function () use ($cycle, $actor, $rule) {
            $sterilizedAt = $cycle->completed_at;
            $useByDate = $sterilizedAt->clone()->addDays($rule->shelf_life_days);

            $labels = $cycle->items()->orderBy('sequence_in_cycle')->get()->map(
                fn ($item) => Label::create([
                    'tenant_id' => $cycle->tenant_id,
                    'cycle_item_id' => $item->id,
                    'status' => 'created',
                    'packaging_type' => $rule->packaging_type,
                    'storage_condition' => $rule->storage_condition,
                    'shelf_life_days' => $rule->shelf_life_days,
                    'sterilized_at' => $sterilizedAt,
                    'use_by_date' => $useByDate,
                    'print_counter' => 0,
                ])
            );

            $this->recordAuditEvent->execute(
                tenant: $cycle->tenant,
                actor: $actor,
                action: 'labels.generated',
                subjectType: Cycle::class,
                subjectId: $cycle->id,
                newValues: ['count' => $labels->count(), 'dlu_rule_id' => $rule->id],
                request: $this->request,
            );

            return $labels;
        });
    }
}
