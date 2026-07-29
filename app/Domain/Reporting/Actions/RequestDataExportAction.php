<?php

namespace App\Domain\Reporting\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Identity\Actions\ResolveTenantUsersWithRoleAction;
use App\Domain\Reporting\Enums\DataExportStatus;
use App\Domain\Reporting\Jobs\GenerateDataExportJob;
use App\Domain\Reporting\Mail\DataExportRequestedMail;
use App\Domain\Reporting\Models\DataExportRequest;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * S6 data portability: an account-wide export is far more sensitive than
 * the S4-ANA-04 evidence dossier (every table, every file, not one
 * filtered slice), hence its own data_exports.manage permission
 * (owner/admin only) rather than reusing exports.manage.
 *
 * UI-29: rate-limited two ways — never more than one in flight per
 * tenant, and a weekly cap. Both numbers are DECISION NEEDED defaults
 * (docs/open-questions.md), not specified by the brief.
 */
class RequestDataExportAction
{
    public const int WEEKLY_LIMIT = 5;

    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private ResolveTenantUsersWithRoleAction $resolveUsersWithRole,
        private Request $request,
    ) {}

    public function execute(Tenant $tenant, User $actor): DataExportRequest
    {
        if (DataExportRequest::whereIn('status', [DataExportStatus::Pending, DataExportStatus::Processing])->exists()) {
            throw new ApiException('EXPORT_ALREADY_RUNNING', 'An export is already in progress for this practice. Wait for it to finish before requesting another.', 409);
        }

        $recentCount = DataExportRequest::where('created_at', '>=', now()->subWeek())->count();

        if ($recentCount >= self::WEEKLY_LIMIT) {
            throw new ApiException('EXPORT_WEEKLY_LIMIT_REACHED', 'No more than '.self::WEEKLY_LIMIT.' exports can be requested per week for this practice.', 429);
        }

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

            // Out-of-band notification so a compromised admin/owner
            // account quietly exporting everything is detectable — sent
            // to every owner, including when the requester is one.
            foreach ($this->resolveUsersWithRole->execute($tenant, 'owner') as $owner) {
                Mail::to($owner->email)->queue(new DataExportRequestedMail($owner, $tenant, $actor));
            }

            GenerateDataExportJob::dispatch($exportRequest->id);

            return $exportRequest;
        });
    }
}
