<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Tenancy\Models\StorageLocation;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * A signed correction (a count discrepancy, damage, loss). Unlike issue/
 * transfer, a negative adjustment is allowed to take a location's recorded
 * quantity below what a naive read would expect — that's the point of an
 * adjustment — but the reason is mandatory (validated at the request
 * layer), since this is the one movement type with no other evidence
 * behind it.
 */
class AdjustStockAction
{
    public function __construct(
        private RecordStockMovementAction $recordStockMovement,
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(Batch $batch, StorageLocation $location, int $signedQty, User $actor, string $reason): StockMovement
    {
        $movement = $this->recordStockMovement->execute(
            tenant: $batch->tenant,
            type: StockMovementType::Adjustment,
            batch: $batch,
            location: $location,
            qty: $signedQty,
            reason: $reason,
            actor: $actor,
        );

        $this->recordAuditEvent->execute(
            tenant: $batch->tenant,
            actor: $actor,
            action: 'stock.adjusted',
            subjectType: StockMovement::class,
            subjectId: $movement->id,
            newValues: ['batch_id' => $batch->id, 'location_id' => $location->id, 'qty' => $signedQty],
            reason: $reason,
            request: $this->request,
        );

        return $movement;
    }
}
