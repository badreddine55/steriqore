<?php

namespace App\Domain\Reporting\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Reporting\Enums\DataExportStatus;
use App\Domain\Reporting\Jobs\GenerateDataExportJob;
use App\Domain\Reporting\Models\DataExportRequest;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * S6 data portability: an account-wide export is far more sensitive than
 * the S4-ANA-04 evidence dossier (every table, every file, not one
 * filtered slice), hence its own data_exports.manage permission
 * (owner/admin only) rather than reusing exports.manage.
 */
class RequestDataExportAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(Tenant $tenant, User $actor): DataExportRequest
    {
        return DB::transaction(function () use ($tenant, $actor) {
            $exportRequest = DataExportRequest::create([
                'tenant_id' => $tenant->id,
                'requested_by_user_id' => $actor->id,
                'status' => DataExportStatus::Pending,
            ]);

            $this->recordAuditEvent->execute(
                tenant: $tenant,
                actor: $actor,
                action: 'data_export.requested',
                subjectType: DataExportRequest::class,
                subjectId: $exportRequest->id,
                request: $this->request,
            );

            GenerateDataExportJob::dispatch($exportRequest->id);

            return $exportRequest;
        });
    }
}
