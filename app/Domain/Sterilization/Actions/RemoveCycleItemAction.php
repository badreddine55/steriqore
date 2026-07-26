<?php

namespace App\Domain\Sterilization\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Sterilization\Enums\CycleStatus;
use App\Domain\Sterilization\Models\Cycle;
use App\Domain\Sterilization\Models\CycleItem;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;

/**
 * A hard delete, not soft — a draft-only item never became evidence
 * (invariant #7 only protects a cycle once it has actually run).
 */
class RemoveCycleItemAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(Cycle $cycle, CycleItem $item, User $actor): void
    {
        if ($cycle->status !== CycleStatus::Draft) {
            throw new ApiException('CYCLE_LOAD_LOCKED', 'Items can only be removed from a cycle while it is still in draft.', 409);
        }

        $item->delete();

        $this->recordAuditEvent->execute(
            tenant: $cycle->tenant,
            actor: $actor,
            action: 'cycle_item.deleted',
            subjectType: CycleItem::class,
            subjectId: $item->id,
            request: $this->request,
        );
    }
}
