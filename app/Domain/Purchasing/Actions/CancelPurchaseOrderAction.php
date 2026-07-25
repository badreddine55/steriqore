<?php

namespace App\Domain\Purchasing\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;

class CancelPurchaseOrderAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(PurchaseOrder $purchaseOrder, User $actor, ?string $reason): PurchaseOrder
    {
        if (! in_array($purchaseOrder->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Ordered], true)) {
            throw new ApiException(
                'INVALID_STATE_TRANSITION',
                "A purchase order can only be cancelled from draft or ordered (currently {$purchaseOrder->status->value}).",
                409,
            );
        }

        $purchaseOrder->update(['status' => PurchaseOrderStatus::Cancelled]);

        $this->recordAuditEvent->execute(
            tenant: $purchaseOrder->tenant,
            actor: $actor,
            action: 'purchase_order.cancelled',
            subjectType: PurchaseOrder::class,
            subjectId: $purchaseOrder->id,
            reason: $reason,
            request: $this->request,
        );

        return $purchaseOrder;
    }
}
