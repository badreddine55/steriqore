<?php

namespace App\Domain\Reporting\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Reporting\Enums\DataExportStatus;
use App\Domain\Reporting\Models\DataExportRequest;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * UI-29: a stored presigned URL is never rendered — it's generated here, on
 * click, and this call is the thing that gets audited (data_export.downloaded),
 * distinct from the data_export.requested event RequestDataExportAction
 * writes. A 30-minute URL sitting in a page for an hour is both a broken
 * download and a leaked link, so nothing upstream of this Action ever sees
 * or stores the URL.
 */
class GenerateDataExportDownloadUrlAction
{
    public const int URL_TTL_MINUTES = 30;

    public function __construct(private RecordAuditEventAction $recordAuditEvent, private Request $request) {}

    public function execute(Tenant $tenant, DataExportRequest $exportRequest, User $actor): string
    {
        if ($exportRequest->status !== DataExportStatus::Completed || $exportRequest->file_path === null) {
            throw new ApiException('EXPORT_NOT_READY', 'This export is not ready to download yet.', 409);
        }

        if ($exportRequest->expires_at !== null && $exportRequest->expires_at->isPast()) {
            throw new ApiException('EXPORT_EXPIRED', 'This export has expired. Request a new one.', 410);
        }

        $url = Storage::disk('backups')->temporaryUrl(
            $exportRequest->file_path,
            now()->addMinutes(self::URL_TTL_MINUTES),
        );

        $this->recordAuditEvent->execute(
            tenant: $tenant,
            actor: $actor,
            action: 'data_export.downloaded',
            subjectType: DataExportRequest::class,
            subjectId: $exportRequest->id,
            request: $this->request,
        );

        return $url;
    }
}
