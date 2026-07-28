<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Enums\AlertSeverity;
use App\Domain\Inventory\Enums\AlertState;
use App\Domain\Inventory\Enums\AlertType;
use App\Domain\Inventory\Models\Alert;
use App\Domain\Inventory\Models\AlertSettings;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic, idempotent: running this twice in a row with nothing
 * changed produces the same open alerts, not duplicates (unique index on
 * tenant_id/type/subject_type/subject_id/state) — and clears an alert as
 * soon as its condition stops being true (restock above threshold, batch
 * fully consumed), rather than leaving stale alerts around.
 *
 * The near-expiry window was originally a hardcoded DECISION NEEDED
 * default (docs/open-questions.md, OQ-6) — UI-28 makes it a real
 * per-tenant setting (AlertSettings::near_expiry_days); DEFAULT_NEAR_EXPIRY_DAYS
 * is only the column's own default for a tenant that's never saved one.
 */
class DetectAlertsAction
{
    /** AlertSettings.near_expiry_days migration default — also the batches list's (UI-15) fallback. */
    public const int DEFAULT_NEAR_EXPIRY_DAYS = 30;

    /**
     * @return array{low_stock: int, near_expiry: int, expired: int}
     */
    public function execute(Tenant $tenant): array
    {
        $opened = ['low_stock' => 0, 'near_expiry' => 0, 'expired' => 0];
        $nearExpiryDays = AlertSettings::forTenant($tenant)->near_expiry_days;

        $stockByProduct = DB::table('stock_levels')
            ->join('batches', 'batches.id', '=', 'stock_levels.batch_id')
            ->where('stock_levels.tenant_id', $tenant->id)
            ->groupBy('batches.product_id')
            ->selectRaw('batches.product_id as product_id, SUM(stock_levels.quantity) as total')
            ->pluck('total', 'product_id');

        Product::where('tenant_id', $tenant->id)->where('min_threshold', '>', 0)->each(function (Product $product) use ($tenant, $stockByProduct, &$opened) {
            $total = (int) ($stockByProduct[$product->id] ?? 0);

            if ($total < $product->min_threshold) {
                $created = $this->openAlert(
                    $tenant, AlertType::LowStock, AlertSeverity::Warning, Product::class, $product->id,
                    "Stock for \"{$product->name}\" is below threshold ({$total}/{$product->min_threshold}).",
                );
                $opened['low_stock'] += $created ? 1 : 0;
            } else {
                $this->resolveIfOpen($tenant, AlertType::LowStock, Product::class, $product->id);
            }
        });

        $batchIdsWithStock = DB::table('stock_levels')
            ->where('tenant_id', $tenant->id)
            ->groupBy('batch_id')
            ->havingRaw('SUM(quantity) > 0')
            ->pluck('batch_id');

        Batch::where('tenant_id', $tenant->id)
            ->whereIn('id', $batchIdsWithStock)
            ->whereNotNull('expiry_date')
            ->each(function (Batch $batch) use ($tenant, $nearExpiryDays, &$opened) {
                if ($batch->expiry_date->isPast()) {
                    $created = $this->openAlert(
                        $tenant, AlertType::Expired, AlertSeverity::Critical, Batch::class, $batch->id,
                        "Batch \"{$batch->batch_number}\" expired on {$batch->expiry_date->toDateString()}.",
                    );
                    $opened['expired'] += $created ? 1 : 0;
                    $this->resolveIfOpen($tenant, AlertType::NearExpiry, Batch::class, $batch->id);
                } elseif ($batch->expiry_date->lte(now()->addDays($nearExpiryDays))) {
                    $created = $this->openAlert(
                        $tenant, AlertType::NearExpiry, AlertSeverity::Warning, Batch::class, $batch->id,
                        "Batch \"{$batch->batch_number}\" expires on {$batch->expiry_date->toDateString()}.",
                    );
                    $opened['near_expiry'] += $created ? 1 : 0;
                } else {
                    $this->resolveIfOpen($tenant, AlertType::NearExpiry, Batch::class, $batch->id);
                }
            });

        return $opened;
    }

    private function openAlert(Tenant $tenant, AlertType $type, AlertSeverity $severity, string $subjectType, string $subjectId, string $message): bool
    {
        $alert = Alert::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'type' => $type,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'state' => AlertState::Open,
            ],
            ['severity' => $severity, 'message' => $message],
        );

        return $alert->wasRecentlyCreated;
    }

    private function resolveIfOpen(Tenant $tenant, AlertType $type, string $subjectType, string $subjectId): void
    {
        Alert::where('tenant_id', $tenant->id)
            ->where('type', $type)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('state', AlertState::Open)
            ->update(['state' => AlertState::Resolved, 'resolved_at' => now()]);
    }
}
