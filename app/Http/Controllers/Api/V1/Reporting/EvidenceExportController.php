<?php

namespace App\Http\Controllers\Api\V1\Reporting;

use App\Domain\Reporting\Exports\EvidenceExport;
use App\Domain\Traceability\Models\LabelUsage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reporting\ExportEvidenceRequest;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * A bulk export of evidence is more sensitive than viewing one record at
 * a time — gated on its own exports.manage permission, not usages.view
 * (REC acceptance: "complete export controlled by permissions").
 */
class EvidenceExportController extends Controller
{
    public function index(ExportEvidenceRequest $request): BinaryFileResponse
    {
        $this->authorize('export', LabelUsage::class);

        $format = $request->string('format', 'xlsx')->toString();
        $writerType = $format === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX;

        return Excel::download(
            new EvidenceExport($request->safe()->except('format')),
            "evidence-export.{$format}",
            $writerType,
        );
    }
}
