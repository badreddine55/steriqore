<?php

namespace App\Domain\Reporting\Actions;

use App\Domain\Labeling\Support\LabelCodeRenderer;
use App\Domain\Traceability\Models\LabelUsage;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * "One click produces the inspection-ready evidence dossier PDF"
 * (CLAUDE.md) — the complete chain for one pouch: device, cycle, its
 * control tests, its release decision, the label itself, and who used it
 * on which (pseudonymized) patient for which procedure.
 */
class RenderEvidenceDossierAction
{
    public function execute(LabelUsage $usage): PdfBuilder
    {
        $usage->load([
            'label.cycleItem.cycle.device.site',
            'label.cycleItem.cycle.deviceProgram',
            'label.cycleItem.cycle.operator',
            'label.cycleItem.cycle.controlTests',
            'label.cycleItem.cycle.release.releasedBy',
            'label.cycleItem.batch',
            'patient',
            'practitioner',
        ]);

        $cycle = $usage->label->cycleItem->cycle;

        return Pdf::view('pdf.evidence-dossier', [
            'usage' => $usage,
            'label' => $usage->label,
            'item' => $usage->label->cycleItem,
            'cycle' => $cycle,
            'device' => $cycle->device,
            'site' => $cycle->device->site,
            'controlTests' => $cycle->controlTests,
            'release' => $cycle->release,
            'qrSvg' => LabelCodeRenderer::qrSvg($usage->label->urn()),
        ])
            ->format('a4')
            ->name("evidence-dossier-{$usage->label_id}.pdf");
    }
}
