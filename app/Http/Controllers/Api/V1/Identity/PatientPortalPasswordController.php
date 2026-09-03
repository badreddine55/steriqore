<?php

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domain\Identity\Actions\ChangePatientPortalPasswordAction;
use App\Domain\Identity\Models\PatientPortalAccount;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PatientPortalPasswordController extends Controller
{
    public function __invoke(
        Request $request,
        ChangePatientPortalPasswordAction $action,
    ): JsonResponse {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        /** @var PatientPortalAccount $account */
        $account = $request->user();

        if (! ($account instanceof PatientPortalAccount)) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_ACCOUNT_TYPE',
                    'message' => 'Only patient portal accounts can change portal password here.',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        $action->execute(
            account: $account,
            currentPassword: $validated['current_password'],
            newPassword: $validated['new_password'],
            request: $request,
        );

        return response()->json([
            'message' => 'Mot de passe mis à jour avec succès.',
        ], Response::HTTP_OK);
    }
}
