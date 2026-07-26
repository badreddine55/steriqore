<?php

namespace App\Http\Controllers\Api\V1\Equipment;

use App\Domain\Equipment\Actions\RecordMaintenanceAction;
use App\Domain\Equipment\Data\MaintenanceRecordData;
use App\Domain\Equipment\Models\Device;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Equipment\RecordMaintenanceRequest;

class MaintenanceRecordController extends Controller
{
    /**
     * @return array<int, MaintenanceRecordData>
     */
    public function index(Device $device): array
    {
        $this->authorize('view', $device);

        return $device->maintenanceRecords()->orderByDesc('performed_at')->get()
            ->map(fn ($record) => MaintenanceRecordData::fromModel($record))
            ->all();
    }

    public function store(Device $device, RecordMaintenanceRequest $request, RecordMaintenanceAction $action): MaintenanceRecordData
    {
        $this->authorize('update', $device);

        $record = $action->execute($device, $request->user(), $request->validated());

        return MaintenanceRecordData::fromModel($record);
    }
}
