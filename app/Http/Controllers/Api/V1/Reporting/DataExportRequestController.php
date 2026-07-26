<?php

namespace App\Http\Controllers\Api\V1\Reporting;

use App\Domain\Reporting\Actions\RequestDataExportAction;
use App\Domain\Reporting\Data\DataExportRequestData;
use App\Domain\Reporting\Models\DataExportRequest;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DataExportRequestController extends Controller
{
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
}
