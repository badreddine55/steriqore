<?php

namespace App\Http\Controllers\Api\V1\Labeling;

use App\Domain\Labeling\Actions\PrintLabelAction;
use App\Domain\Labeling\Actions\RenderLabelPdfAction;
use App\Domain\Labeling\Data\LabelData;
use App\Domain\Labeling\Models\Label;
use App\Domain\Labeling\Support\LabelCodeRenderer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Labeling\PrintLabelRequest;
use Illuminate\Http\Response;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * No show() here on purpose — GET /labels/{code} is the scan-resolution
 * endpoint's URI (S3-ANA-05, currently LabelScanMockController). These are
 * the staff-facing management actions, all one level below that path so
 * the two don't collide.
 */
class LabelController extends Controller
{
    public function print(Label $label, PrintLabelRequest $request, PrintLabelAction $action): LabelData
    {
        $this->authorize('print', $label);

        return LabelData::fromModel($action->execute($label, $request->user(), $request->string('reason')->toString() ?: null));
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
