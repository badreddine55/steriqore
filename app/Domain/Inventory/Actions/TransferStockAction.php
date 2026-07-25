<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Tenancy\Models\StorageLocation;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A transfer is two linked stock_movements rows — a debit at the source, a
 * credit at the destination — sharing group_id, not a single "moved" row.
 * See stock_movements' migration docblock for why.
 */
class TransferStockAction
{
    public function __construct(
        private RecordStockMovementAction $recordStockMovement,
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @return array{debit: StockMovement, credit: StockMovement}
     */
    public function execute(Batch $batch, StorageLocation $from, StorageLocation $to, int $qty, User $actor, ?string $reason): array
    {
        if ($from->id === $to->id) {
            throw new ApiException('INVALID_TRANSFER', 'Source and destination locations must be different.', 422);
        }

        return DB::transaction(function () use ($batch, $from, $to, $qty, $actor, $reason) {
            $level = StockLevel::where('tenant_id', $batch->tenant_id)
                ->where('batch_id', $batch->id)
                ->where('location_id', $from->id)
                ->lockForUpdate()
                ->first();

            $available = $level instanceof StockLevel ? $level->quantity : 0;

            if ($available < $qty) {
                throw new ApiException(
                    'INSUFFICIENT_STOCK',
                    "Only {$available} unit(s) of this batch are available at the source location.",
                    409,
                );
            }

            $groupId = (string) Str::uuid7();

            $debit = $this->recordStockMovement->execute(
                tenant: $batch->tenant,
                type: StockMovementType::Transfer,
                batch: $batch,
                location: $from,
                qty: -$qty,
                groupId: $groupId,
                reason: $reason,
                actor: $actor,
            );

            $credit = $this->recordStockMovement->execute(
                tenant: $batch->tenant,
                type: StockMovementType::Transfer,
                batch: $batch,
                location: $to,
                qty: $qty,
                groupId: $groupId,
                reason: $reason,
                actor: $actor,
            );

            $this->recordAuditEvent->execute(
                tenant: $batch->tenant,
                actor: $actor,
                action: 'stock.transferred',
                subjectType: Batch::class,
                subjectId: $batch->id,
                newValues: ['from_location_id' => $from->id, 'to_location_id' => $to->id, 'qty' => $qty],
                reason: $reason,
                request: $this->request,
            );

            return ['debit' => $debit, 'credit' => $credit];
        });
    }
}
