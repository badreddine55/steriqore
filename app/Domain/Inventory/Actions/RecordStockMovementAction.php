<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Tenancy\Models\StorageLocation;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The one place that writes to stock_movements (CLAUDE.md invariant #1:
 * stock is derived from this ledger, no endpoint sets a quantity
 * directly). stock_levels is updated in the same statement via an atomic
 * upsert (INSERT ... ON CONFLICT DO UPDATE) — Postgres takes the row lock
 * as part of the upsert itself, so two concurrent movements against the
 * same (batch, location) can't race and silently drop one increment,
 * without needing a separate SELECT ... FOR UPDATE step first.
 */
class RecordStockMovementAction
{
    public function execute(
        Tenant $tenant,
        StockMovementType $type,
        Batch $batch,
        StorageLocation $location,
        int $qty,
        ?string $groupId = null,
        ?string $reason = null,
        ?User $actor = null,
        ?string $idempotencyKey = null,
    ): StockMovement {
        return DB::transaction(function () use ($tenant, $type, $batch, $location, $qty, $groupId, $reason, $actor, $idempotencyKey) {
            $movement = StockMovement::create([
                'tenant_id' => $tenant->id,
                'type' => $type,
                'batch_id' => $batch->id,
                'location_id' => $location->id,
                'qty' => $qty,
                'group_id' => $groupId,
                'reason' => $reason,
                'actor_id' => $actor?->id,
                'idempotency_key' => $idempotencyKey,
                'occurred_at' => now(),
            ]);

            DB::statement(<<<'SQL'
                INSERT INTO stock_levels (id, tenant_id, batch_id, location_id, quantity, created_at, updated_at)
                VALUES (uuidv7(), ?, ?, ?, ?, now(), now())
                ON CONFLICT (tenant_id, batch_id, location_id)
                DO UPDATE SET quantity = stock_levels.quantity + excluded.quantity, updated_at = now()
                SQL, [$tenant->id, $batch->id, $location->id, $qty]);

            return $movement;
        });
    }
}
