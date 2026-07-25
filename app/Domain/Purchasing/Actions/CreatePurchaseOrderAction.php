<?php

namespace App\Domain\Purchasing\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreatePurchaseOrderAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<int, array{product_id: string, qty_ordered: int, unit_price?: string|null}>  $lines
     */
    public function execute(Tenant $tenant, Supplier $supplier, User $actor, array $lines): PurchaseOrder
    {
        $purchaseOrder = DB::transaction(function () use ($tenant, $supplier, $lines) {
            $purchaseOrder = PurchaseOrder::create([
                'tenant_id' => $tenant->id,
                'supplier_id' => $supplier->id,
                'status' => PurchaseOrderStatus::Draft,
            ]);

            foreach ($lines as $line) {
                $purchaseOrder->lines()->create([
                    'tenant_id' => $tenant->id,
                    'product_id' => $line['product_id'],
                    'qty_ordered' => $line['qty_ordered'],
                    'unit_price' => $line['unit_price'] ?? null,
                ]);
            }

            return $purchaseOrder;
        });

        $this->recordAuditEvent->execute(
            tenant: $tenant,
            actor: $actor,
            action: 'purchase_order.created',
            subjectType: PurchaseOrder::class,
            subjectId: $purchaseOrder->id,
            newValues: ['supplier_id' => $supplier->id, 'lines' => $lines],
            request: $this->request,
        );

        return $purchaseOrder->load('lines');
    }
}
