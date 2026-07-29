<?php

namespace App\Console\Commands;

use App\Domain\Reporting\Actions\ExpireDataExportsAction;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Scheduled in routes/console.php. Reclaims the S3 object for any
 * completed export past its GenerateDataExportJob::RETENTION_DAYS window
 * and flips the row to Expired, so the history screen stops offering a
 * download button that would otherwise 410 on click.
 *
 * data_export_requests has a Postgres RLS policy (unlike alerts/digest
 * subscriptions), so this needs the full TenantContext::run() — makeCurrent()
 * alone satisfies Eloquent's TenantScope but leaves the RLS session
 * variable unset, which fails the query closed at the database level.
 */
class ExpireDataExports extends Command
{
    protected $signature = 'data-exports:expire';

    protected $description = 'Expire and delete data export archives past their retention window, for every tenant';

    public function handle(ExpireDataExportsAction $action): int
    {
        Tenant::query()->each(function (Tenant $tenant) use ($action) {
            $count = TenantContext::run($tenant, fn () => $action->execute($tenant));

            if ($count > 0) {
                $this->line("{$tenant->name}: expired {$count} data export(s)");
            }
        });

        return self::SUCCESS;
    }
}
