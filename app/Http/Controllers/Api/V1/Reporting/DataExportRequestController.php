<?php

namespace App\Http\Controllers\Api\V1\Reporting;

use App\Domain\Reporting\Actions\GenerateDataExportDownloadUrlAction;
use App\Domain\Reporting\Actions\RequestDataExportAction;
use App\Domain\Reporting\Data\DataExportRequestData;
use App\Domain\Reporting\Models\DataExportRequest;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DataExportRequestController extends Controller
{
    /**
     * @return CursorPaginatedDataCollection<int, DataExportRequestData>
     */
    public function index(Request $request): CursorPaginatedDataCollection
    {
        $this->authorize('viewAny', DataExportRequest::class);

        $exports = QueryBuilder::for(DataExportRequest::class)
            ->allowedFilters(AllowedFilter::exact('status'))
            ->defaultSort('-created_at')
            ->allowedSorts('created_at')
            ->cursorPaginate(perPage: (int) $request->integer('limit', 20));

        return DataExportRequestData::collect($exports, CursorPaginatedDataCollection::class);
    }

    public function store(Request $request, RequestDataExportAction $action): DataExportRequestData
    {
        $this->authorize('create', DataExportRequest::class);

        return DataExportRequestData::fromModel($action->execute(Tenant::current(), $request->user()));
    }

    public function show(DataExportRequest $dataExportRequest): DataExportRequestData
    {
        $this->authorize('view', $dataExportRequest);

        return DataExportRequestData::fromModel($dataExportRequest);
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
