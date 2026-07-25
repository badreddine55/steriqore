<?php

namespace App\Console\Commands;

use App\Domain\Inventory\Actions\DetectAlertsAction;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Scheduled in routes/console.php. Runs detection tenant by tenant — each
 * iteration binds that tenant as current so BelongsToTenant/RLS apply
 * exactly as they would for a real request.
 */
class DetectAlerts extends Command
{
    protected $signature = 'alerts:detect';

    protected $description = 'Detect low-stock, near-expiry, and expired-batch alerts for every tenant';

    public function handle(DetectAlertsAction $action): int
    {
        Tenant::query()->each(function (Tenant $tenant) use ($action) {
            $tenant->makeCurrent();
            $opened = $action->execute($tenant);
            Tenant::forgetCurrent();

            $this->line("{$tenant->name}: {$opened['low_stock']} low-stock, {$opened['near_expiry']} near-expiry, {$opened['expired']} expired");
        });

        return self::SUCCESS;
    }
}
