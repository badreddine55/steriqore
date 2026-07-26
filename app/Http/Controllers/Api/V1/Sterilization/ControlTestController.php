<?php

namespace App\Http\Controllers\Api\V1\Sterilization;

use App\Domain\Sterilization\Actions\RecordControlTestAction;
use App\Domain\Sterilization\Data\ControlTestData;
use App\Domain\Sterilization\Models\ControlTest;
use App\Domain\Sterilization\Models\Cycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Sterilization\RecordControlTestRequest;

class ControlTestController extends Controller
{
    /**
     * @return array<int, ControlTestData>
     */
    public function index(Cycle $cycle): array
    {
        $this->authorize('view', $cycle);

        return $cycle->controlTests()->orderBy('performed_at')->get()
            ->map(fn (ControlTest $test) => ControlTestData::fromModel($test))
            ->all();
    }

    public function store(Cycle $cycle, RecordControlTestRequest $request, RecordControlTestAction $action): ControlTestData
    {
        $this->authorize('update', $cycle);

        return ControlTestData::fromModel($action->execute($cycle, $request->user(), $request->validated()));
    }
}
