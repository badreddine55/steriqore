<?php

namespace App\Http\Controllers\Web\Equipment;

use App\Domain\Equipment\Actions\CreateDeviceAction;
use App\Domain\Equipment\Actions\UpdateDeviceAction;
use App\Domain\Equipment\Data\DeviceData;
use App\Domain\Equipment\Data\DeviceProgramData;
use App\Domain\Equipment\Models\Device;
use App\Domain\Equipment\Models\DeviceProgram;
use App\Domain\Tenancy\Models\Site;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Equipment\CreateDeviceRequest;
use App\Http\Requests\Api\V1\Equipment\UpdateDeviceRequest;
use App\Support\Data\OptionData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Same Device model and DeviceData DTO the mobile API uses
 * (Api\V1\Equipment\DeviceController) — this is a second, web-only
 * presentation of the identical query, not new domain logic.
 */
class DeviceController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Device::class);

        $devices = QueryBuilder::for(Device::class)
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::exact('site_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('kind'),
            )
            ->allowedSorts('name', 'created_at')
            ->defaultSort('name')
            ->cursorPaginate(perPage: 25)
            ->withQueryString();

        $sites = Site::orderBy('name')->get()
            ->map(fn (Site $site) => new OptionData($site->id, $site->name));

        return Inertia::render('equipment/devices/index', [
            'devices' => DeviceData::collect($devices, CursorPaginatedDataCollection::class),
            'sites' => OptionData::collect($sites),
            'canCreate' => $request->user()->can('create', Device::class),
            'filters' => $request->input('filter', []),
        ]);
    }

    public function store(CreateDeviceRequest $request, CreateDeviceAction $action): RedirectResponse
    {
        $this->authorize('create', Device::class);

        $device = $action->execute(Tenant::current(), $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Device \"{$device->name}\" created."]);

        return redirect()->route('devices.show', $device);
    }

    public function show(Device $device, Request $request): Response
    {
        $this->authorize('view', $device);

        $device->loadMissing('site');

        return Inertia::render('equipment/devices/show', [
            'device' => DeviceData::fromModel($device),
            'site' => new OptionData($device->site->id, $device->site->name),
            'programs' => DeviceProgramData::collect(
                $device->programs()->orderBy('name')->get()
                    ->map(fn (DeviceProgram $program) => DeviceProgramData::fromModel($program))
            ),
            'canManage' => $request->user()->can('update', $device),
        ]);
    }

    public function update(Device $device, UpdateDeviceRequest $request, UpdateDeviceAction $action): RedirectResponse
    {
        $this->authorize('update', $device);

        $action->execute($device, $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Device updated.']);

        return back();
    }
}
