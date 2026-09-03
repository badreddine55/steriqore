<?php

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domain\Identity\Actions\CreatePatientPortalAccountAction;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Traceability\Models\Patient;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PatientPortalAccountController extends Controller
{
    public function __invoke(
        Request $request,
        Patient $patient,
        CreatePatientPortalAccountAction $action,
    ): JsonResponse {
        $this->authorize('createPortalAccount', $patient);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $tenant = Tenant::current();

        if (! $tenant || $patient->tenant_id !== $tenant->id) {
            abort(403, 'Patient does not belong to the active practice tenant.');
        }

        $account = $action->execute(
            tenant: $tenant,
            patient: $patient,
            email: $validated['email'],
            actor: $request->user(),
            request: $request,
        );

        return response()->json([
            'id' => $account->id,
            'patient_id' => $patient->id,
            'patient_reference' => $patient->reference,
            'email' => $account->email,
            'status' => $account->status,
            'default_password' => 'password',
            'message' => 'Compte portail créé avec succès. Mot de passe initial par défaut : password',
        ], Response::HTTP_CREATED);
    }
}
