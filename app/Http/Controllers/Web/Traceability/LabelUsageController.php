<?php

namespace App\Http\Controllers\Web\Traceability;

use App\Domain\Labeling\Actions\ResolveLabelScanAction;
use App\Domain\Labeling\Models\Label;
use App\Domain\Reporting\Actions\RenderEvidenceDossierAction;
use App\Domain\Traceability\Actions\RecordLabelUsageAction;
use App\Domain\Traceability\Models\LabelUsage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Traceability\RecordLabelUsageRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Spatie\LaravelPdf\PdfBuilder;

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

    /**
     * Same RenderEvidenceDossierAction the mobile API's
     * LabelUsageController@dossier serves — inline by default (Spatie's
     * PdfBuilder), so this one link is both the preview and the download
     * (UI-22), the same pattern already used for the label PDF (UI-06/07).
     */
    public function dossier(Label $label, RenderEvidenceDossierAction $action): PdfBuilder
    {
        $usage = $label->usage()->first();
        abort_if($usage === null, 404);

        $this->authorize('view', $usage);

        return $action->execute($usage);
    }
}
