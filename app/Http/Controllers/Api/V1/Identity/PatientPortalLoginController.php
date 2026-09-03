<?php

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domain\Identity\Actions\AuthenticatePatientPortalAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientPortalLoginController extends Controller
{
    public function __invoke(Request $request, AuthenticatePatientPortalAction $action): JsonResponse
    {
        $validated = $request->validate([
            'tenant_slug' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $result = $action->execute(
            $validated['tenant_slug'],
            $validated['email'],
            $validated['password'],
        );

        return response()->json([
            'token' => $result['plain_text_token'],
            'token_type' => 'Bearer',
            'patient_reference' => $result['patient_reference'],
            'email' => $result['account']->email,
            'status' => $result['account']->status,
        ], 200);
    }
}
