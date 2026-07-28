<?php

namespace App\Domain\Labeling\Actions;

use App\Domain\Labeling\Enums\LabelSheetLayout;
use App\Domain\Labeling\Enums\PrimaryLabelCode;
use App\Domain\Labeling\Support\LabelCodeRenderer;
use App\Models\User;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * UI-28's "test-print" button — a real sample sheet built from synthetic
 * data (no cycle/label ever exists for it), so a practice can check their
 * scanner and printer against the CURRENT format settings before saving a
 * change that "applies to future prints only" (the brief's own words).
 * Never touches a real Label — this is not evidence, it's a preview.
 */
class RenderLabelFormatSampleAction
{
    public function execute(
        User $actor,
        PrimaryLabelCode $primaryCode,
        LabelSheetLayout $sheetLayout,
        bool $showBatchNumber,
        bool $showProductName,
    ): PdfBuilder {
        $sampleUrn = 'urn:steriqore:label:sample';
        [$columns, $rows] = $sheetLayout->grid();

        return Pdf::view('pdf.label-format-sample', [
            'operatorName' => $actor->name,
            'primaryCode' => $primaryCode,
            'sheetLayout' => $sheetLayout,
            'showBatchNumber' => $showBatchNumber,
            'showProductName' => $showProductName,
            'columns' => $columns,
            'tileCount' => min($columns * $rows, 12),
            'qrSvg' => LabelCodeRenderer::qrSvg($sampleUrn),
            'dataMatrixSvg' => LabelCodeRenderer::dataMatrixSvg($sampleUrn),
        ])
            ->format('a4')
            ->name('label-format-sample.pdf');
    }
}
