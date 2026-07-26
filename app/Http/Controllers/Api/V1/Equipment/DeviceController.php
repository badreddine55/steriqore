<?php

namespace App\Http\Controllers\Api\V1\Equipment;

use App\Domain\Equipment\Actions\CreateDeviceAction;
use App\Domain\Equipment\Actions\DeleteDeviceAction;
use App\Domain\Equipment\Actions\UpdateDeviceAction;
use App\Domain\Equipment\Data\DeviceData;
use App\Domain\Equipment\Models\Device;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Equipment\CreateDeviceRequest;
use App\Http\Requests\Api\V1\Equipment\UpdateDeviceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DeviceController extends Controller
{
    /**
     * @return CursorPaginatedDataCollection<int, DeviceData>
     */
    public function index(Request $request): CursorPaginatedDataCollection
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
            ->cursorPaginate(perPage: (int) $request->integer('limit', 20));

        return DeviceData::collect($devices, CursorPaginatedDataCollection::class);
    }

    public function store(CreateDeviceRequest $request, CreateDeviceAction $action): DeviceData
    {
        $this->authorize('create', Device::class);

        $device = $action->execute(Tenant::current(), $request->user(), $request->validated());

        return DeviceData::fromModel($device);
    }

    public function show(Device $device): DeviceData
    {
        $this->authorize('view', $device);

        return DeviceData::fromModel($device);
    }

    public function update(Device $device, UpdateDeviceRequest $request, UpdateDeviceAction $action): DeviceData
    {
        $this->authorize('update', $device);

        return DeviceData::fromModel($action->execute($device, $request->user(), $request->validated()));
    }

    public function destroy(Device $device, Request $request, DeleteDeviceAction $action): Response
    {
        $this->authorize('delete', $device);

        $action->execute($device, $request->user());

        return response()->noContent();
    }
}
