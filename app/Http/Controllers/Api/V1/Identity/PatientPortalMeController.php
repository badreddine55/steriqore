<?php

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domain\Identity\Models\PatientPortalAccount;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientPortalMeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $account = $request->user();
        if (! $account instanceof PatientPortalAccount) {
            abort(403);
        }

        $patient = $account->patient;
        $tenant = $account->tenant ?? $patient->tenant;

        $fullName = trim(($patient->first_name ?? '').' '.($patient->last_name ?? ''));
        if (empty($fullName)) {
            $fullName = $patient->reference;
        }

        return response()->json([
            'patient_id' => $patient->id,
            'patient_reference' => $patient->reference,
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'full_name' => $fullName,
            'email' => $account->email ?: $patient->email,
            'phone' => $patient->phone,
            'birth_date' => $patient->birth_date ? (is_string($patient->birth_date) ? $patient->birth_date : $patient->birth_date->format('Y-m-d')) : null,
            'nir' => $patient->nir,
            'status' => $account->status,
            'cabinet_name' => $tenant?->name ?? 'Cabinet Dentaire',
            'cabinet_slug' => $tenant?->slug ?? 'cabinet',
        ], 200);
    }
}
