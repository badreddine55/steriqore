<?php

namespace App\Domain\Purchasing\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Inventory\Actions\RecordStockMovementAction;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\GoodsReceipt;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\PurchaseOrderLine;
use App\Domain\Tenancy\Models\StorageLocation;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Receiving a batch is what creates it (CLAUDE.md's domain model ties
 * batches to receipt data: supplier, received_at), and what credits stock —
 * every line becomes a stock_movements row (type=receipt) at the given
 * location, via RecordStockMovementAction, the one place stock_levels
 * changes (CLAUDE.md invariant #1).
 */
class ReceiveGoodsAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private RecordStockMovementAction $recordStockMovement,
        private Request $request,
    ) {}

    /**
     * @param  array<int, array{purchase_order_line_id: string, batch_number: string, expiry_date: ?string, qty: int, discrepancy_reason?: ?string}>  $lines
     */
    public function execute(PurchaseOrder $purchaseOrder, StorageLocation $location, User $actor, array $lines): GoodsReceipt
    {
        if (! in_array($purchaseOrder->status, [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::PartiallyReceived], true)) {
            throw new ApiException(
                'INVALID_STATE_TRANSITION',
                "Goods can only be received for an ordered or partially received purchase order (currently {$purchaseOrder->status->value}).",
                409,
            );
        }

        $receipt = DB::transaction(function () use ($purchaseOrder, $location, $actor, $lines) {
            $receipt = GoodsReceipt::create([
                'tenant_id' => $purchaseOrder->tenant_id,
                'purchase_order_id' => $purchaseOrder->id,
                'received_by' => $actor->id,
                'received_at' => now(),
            ]);

            foreach ($lines as $line) {
                // FOR UPDATE: two concurrent receipts against the same line
                // must not race on qty_received (CLAUDE.md cross-cutting
                // requirement — locking on stock-adjacent rows).
                $orderLine = PurchaseOrderLine::where('id', $line['purchase_order_line_id'])
                    ->where('purchase_order_id', $purchaseOrder->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $batch = Batch::firstOrCreate(
                    [
                        'tenant_id' => $purchaseOrder->tenant_id,
                        'product_id' => $orderLine->product_id,
                        'batch_number' => $line['batch_number'],
                    ],
                    [
                        'supplier_id' => $purchaseOrder->supplier_id,
                        'expiry_date' => $line['expiry_date'] ?? null,
                        'received_at' => now(),
                        'status' => 'active',
                    ],
                );

                $receiptLine = $receipt->lines()->create([
                    'tenant_id' => $purchaseOrder->tenant_id,
                    'purchase_order_line_id' => $orderLine->id,
                    'product_id' => $orderLine->product_id,
                    'batch_id' => $batch->id,
                    'qty' => $line['qty'],
                    'discrepancy_reason' => $line['discrepancy_reason'] ?? null,
                ]);

                $this->recordStockMovement->execute(
                    tenant: $purchaseOrder->tenant,
                    type: StockMovementType::Receipt,
                    batch: $batch,
                    location: $location,
                    qty: $line['qty'],
                    groupId: $receipt->id,
                    reason: $receiptLine->discrepancy_reason,
                    actor: $actor,
                );

                $orderLine->increment('qty_received', $line['qty']);
            }

            $purchaseOrder->refresh();
            $isFullyReceived = $purchaseOrder->lines()->whereColumn('qty_received', '<', 'qty_ordered')->doesntExist();

            $purchaseOrder->update([
                'status' => $isFullyReceived ? PurchaseOrderStatus::Received : PurchaseOrderStatus::PartiallyReceived,
            ]);

            return $receipt;
        });

        $this->recordAuditEvent->execute(
            tenant: $purchaseOrder->tenant,
            actor: $actor,
            action: 'goods_receipt.created',
            subjectType: GoodsReceipt::class,
            subjectId: $receipt->id,
            newValues: ['purchase_order_id' => $purchaseOrder->id, 'location_id' => $location->id, 'lines' => $lines],
            request: $this->request,
        );

        return $receipt->load('lines');
    }
}
