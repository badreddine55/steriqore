<?php

namespace App\Http\Controllers\Web\Sterilization;

use App\Domain\Sterilization\Actions\AddCycleItemAction;
use App\Domain\Sterilization\Actions\RemoveCycleItemAction;
use App\Domain\Sterilization\Models\Cycle;
use App\Domain\Sterilization\Models\CycleItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Sterilization\AddCycleItemRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CycleItemController extends Controller
{
    public function store(Cycle $cycle, AddCycleItemRequest $request, AddCycleItemAction $action): RedirectResponse
    {
        $this->authorize('update', $cycle);

        $action->execute($cycle, $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Item added to the load.']);

        return back();
    }

    public function destroy(Cycle $cycle, CycleItem $item, Request $request, RemoveCycleItemAction $action): RedirectResponse
    {
        $this->authorize('update', $cycle);
        abort_if($item->cycle_id !== $cycle->id, 404);

        $action->execute($cycle, $item, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Item removed.']);

        return back();
    }
}
