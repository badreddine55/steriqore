<?php

namespace App\Http\Controllers\Api\V1\Equipment;

use App\Domain\Equipment\Actions\CreateDeviceProgramAction;
use App\Domain\Equipment\Actions\DeleteDeviceProgramAction;
use App\Domain\Equipment\Actions\UpdateDeviceProgramAction;
use App\Domain\Equipment\Data\DeviceProgramData;
use App\Domain\Equipment\Models\Device;
use App\Domain\Equipment\Models\DeviceProgram;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Equipment\CreateDeviceProgramRequest;
use App\Http\Requests\Api\V1\Equipment\UpdateDeviceProgramRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DeviceProgramController extends Controller
{
    /**
     * @return array<int, DeviceProgramData>
     */
    public function index(Device $device): array
    {
        $this->authorize('view', $device);

        return $device->programs()->get()
            ->map(fn (DeviceProgram $program) => DeviceProgramData::fromModel($program))
            ->all();
    }

    public function store(Device $device, CreateDeviceProgramRequest $request, CreateDeviceProgramAction $action): DeviceProgramData
    {
        $this->authorize('update', $device);

        $program = $action->execute($device, $request->user(), $request->validated());

        return DeviceProgramData::fromModel($program);
    }

    public function update(Device $device, DeviceProgram $program, UpdateDeviceProgramRequest $request, UpdateDeviceProgramAction $action): DeviceProgramData
    {
        $this->authorize('update', $device);
        abort_if($program->device_id !== $device->id, 404);

        return DeviceProgramData::fromModel($action->execute($program, $request->user(), $request->validated()));
    }

    public function destroy(Device $device, DeviceProgram $program, Request $request, DeleteDeviceProgramAction $action): Response
    {
        $this->authorize('update', $device);
        abort_if($program->device_id !== $device->id, 404);

        $action->execute($program, $request->user());

        return response()->noContent();
    }
}
