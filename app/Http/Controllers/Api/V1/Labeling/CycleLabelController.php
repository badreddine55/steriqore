<?php

namespace App\Http\Controllers\Api\V1\Labeling;

use App\Domain\Labeling\Actions\GenerateCycleLabelsAction;
use App\Domain\Labeling\Data\LabelData;
use App\Domain\Labeling\Models\Label;
use App\Domain\Sterilization\Models\Cycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Labeling\GenerateCycleLabelsRequest;
use Spatie\LaravelData\DataCollection;

class CycleLabelController extends Controller
{
    /**
     * @return array<int, LabelData>
     */
    public function index(Cycle $cycle): array
    {
        $this->authorize('view', $cycle);

        return Label::whereIn('cycle_item_id', $cycle->items()->pluck('id'))
            ->get()
            ->map(fn (Label $label) => LabelData::fromModel($label))
            ->all();
    }

    /**
     * @return DataCollection<int, LabelData>
     */
    public function store(Cycle $cycle, GenerateCycleLabelsRequest $request, GenerateCycleLabelsAction $action): DataCollection
    {
        $this->authorize('create', Label::class);

        $labels = $action->execute(
            $cycle,
            $request->user(),
            $request->string('packaging_type')->toString(),
            $request->string('storage_condition')->toString(),
        );

        return LabelData::collect($labels, DataCollection::class);
    }
}
