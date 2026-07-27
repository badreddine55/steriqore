<?php

namespace App\Http\Controllers\Web\Traceability;

use App\Domain\Labeling\Actions\ResolveLabelScanAction;
use App\Domain\Labeling\Models\Label;
use App\Domain\Traceability\Actions\RecordLabelUsageAction;
use App\Domain\Traceability\Models\LabelUsage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Traceability\RecordLabelUsageRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The web fallback for the mobile scan (UI-20) — a desk without a camera
 * still needs to be able to link a label to a procedure. Reuses the exact
 * same two Actions a real scan+link does, in the same order:
 * ResolveLabelScanAction (blocks recalled/voided/expired, transitions
 * printed -> used, writes the same audit trail) then
 * RecordLabelUsageAction — not a shortcut around either.
 */
class LabelUsageController extends Controller
{
    public function store(
        Label $label,
        RecordLabelUsageRequest $request,
        ResolveLabelScanAction $resolveScan,
        RecordLabelUsageAction $recordUsage,
    ): RedirectResponse {
        $this->authorize('create', LabelUsage::class);

        $resolved = $resolveScan->execute($label->id, $request->user());
        $recordUsage->execute($resolved, $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Label linked to procedure.']);

        return back();
    }
}
