<?php

namespace App\Http\Controllers\Api\V1\Traceability;

use App\Domain\Labeling\Models\Label;
use App\Domain\Reporting\Actions\RenderEvidenceDossierAction;
use App\Domain\Traceability\Actions\RecordLabelUsageAction;
use App\Domain\Traceability\Data\LabelUsageData;
use App\Domain\Traceability\Models\LabelUsage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Traceability\RecordLabelUsageRequest;
use Spatie\LaravelPdf\PdfBuilder;

class LabelUsageController extends Controller
{
    public function show(Label $label): LabelUsageData
    {
        $usage = $this->usageOrFail($label);

        $this->authorize('view', $usage);

        return LabelUsageData::fromModel($usage);
    }

    public function store(Label $label, RecordLabelUsageRequest $request, RecordLabelUsageAction $action): LabelUsageData
    {
        $this->authorize('create', LabelUsage::class);

        return LabelUsageData::fromModel($action->execute($label, $request->user(), $request->validated()));
    }

    public function dossier(Label $label, RenderEvidenceDossierAction $action): PdfBuilder
    {
        $usage = $this->usageOrFail($label);

        $this->authorize('view', $usage);

        return $action->execute($usage);
    }

    private function usageOrFail(Label $label): LabelUsage
    {
        $usage = $label->usage()->first();
        abort_if($usage === null, 404);

        return $usage;
    }
}
