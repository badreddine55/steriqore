<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Models\PersonalAccessToken;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LogoutEverywhereController extends Controller
{
    /**
     * Revoke every token for this practice — not just the one used for this
     * request. Tokens for other practices the same user belongs to are
     * untouched (a token authenticates for exactly one tenant).
     */
    public function __invoke(Request $request): Response
    {
        /** @var PersonalAccessToken $token */
        $token = $request->user()->currentAccessToken();

        $request->user()->tokens()->where('tenant_id', $token->tenant_id)->delete();

        return response()->noContent();
    }
}
