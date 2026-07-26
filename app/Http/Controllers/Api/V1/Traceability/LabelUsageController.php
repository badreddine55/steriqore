<?php

namespace App\Http\Controllers\Api\V1\Traceability;

use App\Domain\Labeling\Models\Label;
use App\Domain\Traceability\Actions\RecordLabelUsageAction;
use App\Domain\Traceability\Data\LabelUsageData;
use App\Domain\Traceability\Models\LabelUsage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Traceability\RecordLabelUsageRequest;

class LabelUsageController extends Controller
{
    public function show(Label $label): LabelUsageData
    {
        $usage = $label->usage()->first();
        abort_if($usage === null, 404);

        $this->authorize('view', $usage);

        return LabelUsageData::fromModel($usage);
    }

    public function store(Label $label, RecordLabelUsageRequest $request, RecordLabelUsageAction $action): LabelUsageData
    {
        $this->authorize('create', LabelUsage::class);

        return LabelUsageData::fromModel($action->execute($label, $request->user(), $request->validated()));
    }
}
