<?php

namespace App\Domain\Sterilization\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Sterilization\Enums\CycleStatus;
use App\Domain\Sterilization\Models\Cycle;
use App\Domain\Sterilization\Models\CycleItem;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The load can only be composed before the door closes — once a cycle is
 * Running, its item list is locked (real autoclaves don't let you add a
 * pouch mid-run).
 */
class AddCycleItemAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Cycle $cycle, User $actor, array $attributes): CycleItem
    {
        if ($cycle->status !== CycleStatus::Draft) {
            throw new ApiException('CYCLE_LOAD_LOCKED', 'Items can only be added to a cycle while it is still in draft.', 409);
        }

        return DB::transaction(function () use ($cycle, $actor, $attributes) {
            Cycle::where('id', $cycle->id)->lockForUpdate()->first();

            $nextSequence = ($cycle->items()->max('sequence_in_cycle') ?? 0) + 1;

            $item = $cycle->items()->create($attributes + [
                'tenant_id' => $cycle->tenant_id,
                'sequence_in_cycle' => $nextSequence,
            ]);

            $this->recordAuditEvent->execute(
                tenant: $cycle->tenant,
                actor: $actor,
                action: 'cycle_item.created',
                subjectType: CycleItem::class,
                subjectId: $item->id,
                newValues: $attributes + ['sequence_in_cycle' => $nextSequence],
                request: $this->request,
            );

            return $item;
        });
    }
}
