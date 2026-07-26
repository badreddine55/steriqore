<?php

namespace App\Http\Controllers\Api\V1\Sterilization;

use App\Domain\Sterilization\Actions\AddCycleItemAction;
use App\Domain\Sterilization\Actions\RemoveCycleItemAction;
use App\Domain\Sterilization\Data\CycleItemData;
use App\Domain\Sterilization\Models\Cycle;
use App\Domain\Sterilization\Models\CycleItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Sterilization\AddCycleItemRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CycleItemController extends Controller
{
    /**
     * @return array<int, CycleItemData>
     */
    public function index(Cycle $cycle): array
    {
        $this->authorize('view', $cycle);

        return $cycle->items()->orderBy('sequence_in_cycle')->get()
            ->map(fn (CycleItem $item) => CycleItemData::fromModel($item))
            ->all();
    }

    public function store(Cycle $cycle, AddCycleItemRequest $request, AddCycleItemAction $action): CycleItemData
    {
        $this->authorize('update', $cycle);

        return CycleItemData::fromModel($action->execute($cycle, $request->user(), $request->validated()));
    }

    public function destroy(Cycle $cycle, CycleItem $item, Request $request, RemoveCycleItemAction $action): Response
    {
        $this->authorize('update', $cycle);
        abort_if($item->cycle_id !== $cycle->id, 404);

        $action->execute($cycle, $item, $request->user());

        return response()->noContent();
    }
}
