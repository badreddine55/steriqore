<?php

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domain\Identity\Models\PatientPortalAccount;
use App\Domain\Reporting\Actions\RenderEvidenceDossierAction;
use App\Domain\Traceability\Models\LabelUsage;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\LaravelPdf\PdfBuilder;

class PatientPortalDossierController extends Controller
{
    public function __invoke(Request $request, LabelUsage $labelUsage, RenderEvidenceDossierAction $action): PdfBuilder
    {
        $account = $request->user();
        if (! $account instanceof PatientPortalAccount) {
            abort(403);
        }

        // Strict self-scoping: ensure this usage record belongs to the authenticated patient account
        if ($labelUsage->patient_id !== $account->patient_id) {
            abort(404, 'Evidence dossier not found.');
        }

        return $action->execute($labelUsage);
    }
}
