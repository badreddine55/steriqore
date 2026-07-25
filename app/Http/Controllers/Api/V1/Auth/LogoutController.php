<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Identity\Models\PersonalAccessToken;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LogoutController extends Controller
{
    /**
     * Revoke the token used for this request. Other tokens (other devices,
     * other practices) are untouched.
     */
    public function __invoke(Request $request, RecordAuditEventAction $recordAuditEvent): Response
    {
        /** @var User $user */
        $user = $request->user();

        $recordAuditEvent->execute(
            tenant: Tenant::current(),
            actor: $user,
            action: 'auth.logout',
            subjectType: User::class,
            subjectId: $user->id,
            request: $request,
        );

        /** @var PersonalAccessToken $token */
        $token = $user->currentAccessToken();
        $token->delete();

        return response()->noContent();
    }
}
