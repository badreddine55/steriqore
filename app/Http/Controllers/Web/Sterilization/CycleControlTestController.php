<?php

namespace App\Http\Controllers\Web\Sterilization;

use App\Domain\Sterilization\Actions\RecordControlTestAction;
use App\Domain\Sterilization\Models\Cycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Sterilization\RecordControlTestRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CycleControlTestController extends Controller
{
    public function store(Cycle $cycle, RecordControlTestRequest $request, RecordControlTestAction $action): RedirectResponse
    {
        $this->authorize('update', $cycle);

        $action->execute($cycle, $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Control test recorded.']);

        return back();
    }
}
