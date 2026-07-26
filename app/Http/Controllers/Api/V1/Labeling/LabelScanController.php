<?php

namespace App\Http\Controllers\Api\V1\Labeling;

use App\Domain\Labeling\Actions\ResolveLabelScanAction;
use App\Domain\Labeling\Data\LabelScanResultData;
use App\Domain\Labeling\Models\Label;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LabelScanController extends Controller
{
    public function show(string $code, Request $request, ResolveLabelScanAction $action): LabelScanResultData
    {
        $this->authorize('viewAny', Label::class);

        return LabelScanResultData::fromLabel($action->execute($code, $request->user()));
    }
}
