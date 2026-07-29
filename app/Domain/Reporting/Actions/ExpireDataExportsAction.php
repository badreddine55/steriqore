<?php

namespace App\Domain\Reporting\Actions;

use App\Domain\Reporting\Enums\DataExportStatus;
use App\Domain\Reporting\Models\DataExportRequest;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Storage;

/**
 * UI-29: once GenerateDataExportJob's expires_at has passed, the archive is
 * no longer meant to be downloadable (GenerateDataExportDownloadUrlAction
 * already refuses at that point) — this reclaims the S3 object itself and
 * flips the row to Expired so the history screen stops offering a download
 * button instead of erroring on click.
 */
class ExpireDataExportsAction
{
    public function execute(Tenant $tenant): int
    {
        $expired = DataExportRequest::where('tenant_id', $tenant->id)
            ->where('status', DataExportStatus::Completed)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expired as $exportRequest) {
            if ($exportRequest->file_path !== null) {
                Storage::disk('backups')->delete($exportRequest->file_path);
            }

            $exportRequest->update(['status' => DataExportStatus::Expired, 'file_path' => null]);
        }

        return $expired->count();
    }
}
