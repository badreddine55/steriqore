<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Identity\Models\PersonalAccessToken;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LogoutEverywhereController extends Controller
{
    /**
     * Revoke every token for this practice — not just the one used for this
     * request. Tokens for other practices the same user belongs to are
     * untouched (a token authenticates for exactly one tenant).
     */
    public function __invoke(Request $request, RecordAuditEventAction $recordAuditEvent): Response
    {
        /** @var User $user */
        $user = $request->user();

        /** @var PersonalAccessToken $token */
        $token = $user->currentAccessToken();

        $recordAuditEvent->execute(
            tenant: Tenant::current(),
            actor: $user,
            action: 'auth.logout_everywhere',
            subjectType: User::class,
            subjectId: $user->id,
            request: $request,
        );

        $user->tokens()->where('tenant_id', $token->tenant_id)->delete();

        return response()->noContent();
    }
}
