<?php

namespace App\Http\Controllers\Web\Equipment;

use App\Domain\Equipment\Actions\RecordMaintenanceAction;
use App\Domain\Equipment\Models\Device;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Equipment\RecordMaintenanceRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Create-only, matching the domain: MaintenanceRecord is a historical log
 * entry with no update/delete route anywhere (see the model's own
 * docblock) — a correction is a new record, not an edit.
 */
class MaintenanceRecordController extends Controller
{
    public function store(Device $device, RecordMaintenanceRequest $request, RecordMaintenanceAction $action): RedirectResponse
    {
        $this->authorize('update', $device);

        $action->execute($device, $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Maintenance recorded.']);

        return back();
    }
}
