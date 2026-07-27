<?php

namespace App\Http\Controllers\Web\Equipment;

use App\Domain\Equipment\Actions\CreateDeviceProgramAction;
use App\Domain\Equipment\Actions\DeleteDeviceProgramAction;
use App\Domain\Equipment\Actions\UpdateDeviceProgramAction;
use App\Domain\Equipment\Models\Device;
use App\Domain\Equipment\Models\DeviceProgram;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Equipment\CreateDeviceProgramRequest;
use App\Http\Requests\Api\V1\Equipment\UpdateDeviceProgramRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeviceProgramController extends Controller
{
    public function store(Device $device, CreateDeviceProgramRequest $request, CreateDeviceProgramAction $action): RedirectResponse
    {
        $this->authorize('update', $device);

        $action->execute($device, $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Program added.']);

        return back();
    }

    public function update(Device $device, DeviceProgram $program, UpdateDeviceProgramRequest $request, UpdateDeviceProgramAction $action): RedirectResponse
    {
        $this->authorize('update', $device);
        abort_if($program->device_id !== $device->id, 404);

        $action->execute($program, $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Program updated.']);

        return back();
    }

    public function destroy(Device $device, DeviceProgram $program, Request $request, DeleteDeviceProgramAction $action): RedirectResponse
    {
        $this->authorize('update', $device);
        abort_if($program->device_id !== $device->id, 404);

        $action->execute($program, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Program removed.']);

        return back();
    }
}
