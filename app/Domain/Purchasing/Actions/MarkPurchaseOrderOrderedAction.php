<?php

namespace App\Domain\Purchasing\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;

class MarkPurchaseOrderOrderedAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(PurchaseOrder $purchaseOrder, User $actor): PurchaseOrder
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::Draft) {
            throw new ApiException(
                'INVALID_STATE_TRANSITION',
                "A purchase order can only be marked ordered from draft (currently {$purchaseOrder->status->value}).",
                409,
            );
        }

        $purchaseOrder->update(['status' => PurchaseOrderStatus::Ordered, 'ordered_at' => now()]);

        $this->recordAuditEvent->execute(
            tenant: $purchaseOrder->tenant,
            actor: $actor,
            action: 'purchase_order.ordered',
            subjectType: PurchaseOrder::class,
            subjectId: $purchaseOrder->id,
            request: $this->request,
        );

        return $purchaseOrder;
    }
}
