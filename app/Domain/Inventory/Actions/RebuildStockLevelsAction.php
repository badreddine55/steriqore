<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Proves CLAUDE.md invariant #1 for real: stock_levels is a projection,
 * rebuildable by replaying stock_movements — not just a claim. Wipes and
 * recomputes this tenant's stock_levels from SUM(qty) grouped by
 * (batch_id, location_id). Safe to run any time; the ledger itself is
 * never touched.
 */
class RebuildStockLevelsAction
{
    public function execute(Tenant $tenant): int
    {
        return DB::transaction(function () use ($tenant) {
            DB::table('stock_levels')->where('tenant_id', $tenant->id)->delete();

            return DB::affectingStatement(<<<'SQL'
                INSERT INTO stock_levels (id, tenant_id, batch_id, location_id, quantity, created_at, updated_at)
                SELECT uuidv7(), tenant_id, batch_id, location_id, SUM(qty), now(), now()
                FROM stock_movements
                WHERE tenant_id = ?
                GROUP BY tenant_id, batch_id, location_id
                SQL, [$tenant->id]);
        });
    }
}
