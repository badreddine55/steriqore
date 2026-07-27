<?php

namespace App\Http\Controllers\Web\Labeling;

use App\Domain\Labeling\Actions\PrintLabelAction;
use App\Domain\Labeling\Actions\RenderLabelPdfAction;
use App\Domain\Labeling\Enums\LabelStatus;
use App\Domain\Labeling\Models\Label;
use App\Domain\Labeling\Support\LabelCodeRenderer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Labeling\PrintLabelRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Spatie\LaravelPdf\PdfBuilder;

class LabelController extends Controller
{
    public function print(Label $label, PrintLabelRequest $request, PrintLabelAction $action): RedirectResponse
    {
        $this->authorize('print', $label);

        $isReprint = $label->status === LabelStatus::Printed;

        $action->execute($label, $request->user(), $request->string('reason')->toString() ?: null);

        Inertia::flash('toast', ['type' => 'success', 'message' => $isReprint ? 'Label reprinted.' : 'Label printed.']);

        return back();
    }

    public function pdf(Label $label, RenderLabelPdfAction $action): PdfBuilder
    {
        $this->authorize('view', $label);

        return $action->execute($label);
    }

    public function qrCode(Label $label): Response
    {
        $this->authorize('view', $label);

        return response(LabelCodeRenderer::qrSvg($label->urn()), 200, ['Content-Type' => 'image/svg+xml']);
    }

    public function dataMatrix(Label $label): Response
    {
        $this->authorize('view', $label);

        return response(LabelCodeRenderer::dataMatrixSvg($label->urn()), 200, ['Content-Type' => 'image/svg+xml']);
    }
}
