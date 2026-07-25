<?php

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domain\Identity\Actions\AcceptInvitationAction;
use App\Domain\Identity\Data\UserData;
use App\Domain\Tenancy\Data\TenantData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\AcceptInvitationRequest;
use Illuminate\Http\JsonResponse;

class AcceptInvitationController extends Controller
{
    /**
     * Public — the invitee has no session/token yet. For an email that
     * already belongs to an account, this still requires that account's
     * real password (see AcceptInvitationAction) rather than trusting
     * whoever clicked the link.
     */
    public function __invoke(AcceptInvitationRequest $request, AcceptInvitationAction $action): JsonResponse
    {
        $result = $action->execute(
            token: $request->string('token')->toString(),
            name: $request->string('name')->toString(),
            password: $request->string('password')->toString(),
        );

        return response()->json([
            'token' => $result['plain_text_token'],
            'token_type' => 'Bearer',
            'user' => UserData::fromModel($result['user']),
            'tenant' => TenantData::fromModel($result['tenant']),
        ], 200);
    }
}
