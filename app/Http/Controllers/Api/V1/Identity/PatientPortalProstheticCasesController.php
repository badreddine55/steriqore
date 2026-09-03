<?php

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domain\Identity\Models\PatientPortalAccount;
use App\Domain\Prosthetics\Data\ProstheticCaseData;
use App\Domain\Prosthetics\Models\ProstheticCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientPortalProstheticCasesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $account = $request->user();
        if (! $account instanceof PatientPortalAccount) {
            abort(403);
        }

        $patient = $account->patient;
        $tenantId = $account->tenant_id;

        $cases = ProstheticCase::where('tenant_id', $tenantId)
            ->where(function ($q) use ($patient) {
                if ($patient->id) {
                    $q->where('patient_id', (string) $patient->id);
                }
                if ($patient->reference) {
                    $q->orWhere('patient_code', $patient->reference);
                }
            })
            ->latest()
            ->get();

        $result = $cases->map(fn ($c) => ProstheticCaseData::fromModel($c))->values();
        return response()->json($result);
    }
}
