<?php

namespace App\Domain\Labeling\Actions;

use App\Domain\Labeling\Models\Label;
use App\Domain\Labeling\Support\LabelCodeRenderer;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class RenderLabelPdfAction
{
    public function execute(Label $label): PdfBuilder
    {
        $item = $label->cycleItem()->with(['cycle.device.site', 'cycle.operator'])->firstOrFail();
        $cycle = $item->cycle;

        return Pdf::view('pdf.label', [
            'label' => $label,
            'item' => $item,
            'cycle' => $cycle,
            'device' => $cycle->device,
            'site' => $cycle->device->site,
            'operator' => $cycle->operator,
            'qrSvg' => LabelCodeRenderer::qrSvg($label->urn()),
            'dataMatrixSvg' => LabelCodeRenderer::dataMatrixSvg($label->urn()),
        ])
            ->format('a4')
            ->name("label-{$label->id}.pdf");
    }
}
