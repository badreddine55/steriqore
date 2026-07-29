<?php

namespace App\Http\Controllers\Web\Reporting;

use App\Domain\Reporting\Actions\GenerateDataExportDownloadUrlAction;
use App\Domain\Reporting\Actions\RequestDataExportAction;
use App\Domain\Reporting\Data\DataExportRequestData;
use App\Domain\Reporting\Enums\DataExportStatus;
use App\Domain\Reporting\Models\DataExportRequest;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\CursorPaginatedDataCollection;

/**
 * UI-29: the single most sensitive artifact the product can emit — every
 * table, every attached file for the whole practice — gated on its own
 * data_exports.manage permission (owner/admin only, see
 * SeedTenantRolesAction), never on the more general exports.manage used by
 * the S4 evidence dossier. The download URL is never part of this
 * controller's Inertia props or JSON payload except from the dedicated
 * download() action, which mints it fresh and audits the click — see
 * GenerateDataExportDownloadUrlAction's docblock.
 */
class DataExportRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', DataExportRequest::class);

        $tenant = Tenant::current();

        $exports = DataExportRequest::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->cursorPaginate(20)
            ->withQueryString();

        $hasRunningExport = DataExportRequest::where('tenant_id', $tenant->id)
            ->whereIn('status', [DataExportStatus::Pending, DataExportStatus::Processing])
            ->exists();

        return Inertia::render('reporting/data-exports/index', [
            'exports' => DataExportRequestData::collect($exports, CursorPaginatedDataCollection::class),
            'hasRunningExport' => $hasRunningExport,
            'weeklyLimit' => RequestDataExportAction::WEEKLY_LIMIT,
        ]);
    }

    public function store(Request $request, RequestDataExportAction $action): RedirectResponse
    {
        $this->authorize('create', DataExportRequest::class);

        $action->execute(Tenant::current(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Export requested. We've emailed the practice owners, and this page will update once it's ready."]);

        return back();
    }

    public function download(DataExportRequest $dataExportRequest, Request $request, GenerateDataExportDownloadUrlAction $action): JsonResponse
    {
        $this->authorize('download', $dataExportRequest);

        $url = $action->execute(Tenant::current(), $dataExportRequest, $request->user());

        return response()->json([
            'download_url' => $url,
            'url_expires_at' => now()->addMinutes(GenerateDataExportDownloadUrlAction::URL_TTL_MINUTES)->toIso8601String(),
        ]);
    }
}
