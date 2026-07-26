<?php

namespace App\Http\Controllers\Api\V1\Sterilization;

use App\Domain\Equipment\Models\Device;
use App\Domain\Sterilization\Actions\CompleteCycleAction;
use App\Domain\Sterilization\Actions\CreateCycleAction;
use App\Domain\Sterilization\Actions\StartCycleAction;
use App\Domain\Sterilization\Actions\SubmitCycleForReleaseAction;
use App\Domain\Sterilization\Data\CycleData;
use App\Domain\Sterilization\Models\Cycle;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Sterilization\CreateCycleRequest;
use Illuminate\Http\Request;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CycleController extends Controller
{
    /**
     * @return CursorPaginatedDataCollection<int, CycleData>
     */
    public function index(Request $request): CursorPaginatedDataCollection
    {
        $this->authorize('viewAny', Cycle::class);

        $cycles = QueryBuilder::for(Cycle::class)
            ->allowedFilters(
                AllowedFilter::exact('device_id'),
                AllowedFilter::exact('status'),
            )
            ->allowedSorts('cycle_number', 'created_at')
            ->defaultSort('-created_at')
            ->cursorPaginate(perPage: (int) $request->integer('limit', 20));

        return CycleData::collect($cycles, CursorPaginatedDataCollection::class);
    }

    public function store(CreateCycleRequest $request, CreateCycleAction $action): CycleData
    {
        $this->authorize('create', Cycle::class);

        $device = Device::findOrFail($request->string('device_id')->toString());

        $cycle = $action->execute(Tenant::current(), $device, $request->user(), $request->safe()->except('device_id'));

        return CycleData::fromModel($cycle);
    }

    public function show(Cycle $cycle): CycleData
    {
        $this->authorize('view', $cycle);

        return CycleData::fromModel($cycle);
    }

    public function start(Cycle $cycle, Request $request, StartCycleAction $action): CycleData
    {
        $this->authorize('update', $cycle);

        return CycleData::fromModel($action->execute($cycle, $request->user()));
    }

    public function complete(Cycle $cycle, Request $request, CompleteCycleAction $action): CycleData
    {
        $this->authorize('update', $cycle);

        return CycleData::fromModel($action->execute($cycle, $request->user()));
    }

    public function submitForRelease(Cycle $cycle, Request $request, SubmitCycleForReleaseAction $action): CycleData
    {
        $this->authorize('update', $cycle);

        return CycleData::fromModel($action->execute($cycle, $request->user()));
    }
}
