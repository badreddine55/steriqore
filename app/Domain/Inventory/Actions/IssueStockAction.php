<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Inventory\Enums\BatchStatus;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Tenancy\Models\StorageLocation;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Issuing (using up / removing stock) can never take a location negative —
 * checked against the current stock_levels projection inside the same
 * locking transaction RecordStockMovementAction's upsert runs in, so a
 * concurrent issue can't both pass the check and overdraw.
 */
class IssueStockAction
{
    public function __construct(
        private RecordStockMovementAction $recordStockMovement,
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(Batch $batch, StorageLocation $location, int $qty, User $actor, ?string $reason): StockMovement
    {
        if ($batch->status === BatchStatus::Quarantined) {
            throw new ApiException('BATCH_QUARANTINED', 'This batch is quarantined following a non-conformity and cannot be issued.', 409);
        }

        return DB::transaction(function () use ($batch, $location, $qty, $actor, $reason) {
            $level = StockLevel::where('tenant_id', $batch->tenant_id)
                ->where('batch_id', $batch->id)
                ->where('location_id', $location->id)
                ->lockForUpdate()
                ->first();

            $available = $level instanceof StockLevel ? $level->quantity : 0;

            if ($available < $qty) {
                throw new ApiException(
                    'INSUFFICIENT_STOCK',
                    "Only {$available} unit(s) of this batch are available at this location.",
                    409,
                );
            }

            $movement = $this->recordStockMovement->execute(
                tenant: $batch->tenant,
                type: StockMovementType::Issue,
                batch: $batch,
                location: $location,
                qty: -$qty,
                reason: $reason,
                actor: $actor,
            );

            $this->recordAuditEvent->execute(
                tenant: $batch->tenant,
                actor: $actor,
                action: 'stock.issued',
                subjectType: StockMovement::class,
                subjectId: $movement->id,
                newValues: ['batch_id' => $batch->id, 'location_id' => $location->id, 'qty' => $qty],
                reason: $reason,
                request: $this->request,
            );

            return $movement;
        });
    }
}
