<?php

namespace App\Http\Controllers\Api\V1\Sterilization;

use App\Domain\Sterilization\Actions\ReleaseCycleAction;
use App\Domain\Sterilization\Data\CycleReleaseData;
use App\Domain\Sterilization\Enums\CycleReleaseDecision;
use App\Domain\Sterilization\Models\Cycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Sterilization\ReleaseCycleRequest;

class CycleReleaseController extends Controller
{
    public function show(Cycle $cycle): CycleReleaseData
    {
        $this->authorize('view', $cycle);

        $release = $cycle->release()->first();
        abort_if($release === null, 404);

        return CycleReleaseData::fromModel($release);
    }

    public function store(Cycle $cycle, ReleaseCycleRequest $request, ReleaseCycleAction $action): CycleReleaseData
    {
        $this->authorize('release', $cycle);

        $decision = CycleReleaseDecision::from($request->string('decision')->toString());

        $release = $action->execute($cycle, $request->user(), $decision, $request->string('reason')->toString() ?: null);

        return CycleReleaseData::fromModel($release);
    }
}
