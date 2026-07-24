<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Actions\AuthenticateUserAction;
use App\Domain\Identity\Data\UserData;
use App\Domain\Tenancy\Data\TenantData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    /**
     * Log in and receive a Sanctum token scoped to one practice.
     *
     * The token authenticates for `tenant_slug` only — a user who belongs to
     * more than one practice logs in once per practice and holds a separate
     * token for each. There is no client-supplied tenant on any other
     * endpoint; every subsequent request resolves its tenant from this token.
     */
    public function __invoke(LoginRequest $request, AuthenticateUserAction $action): JsonResponse
    {
        $result = $action->execute(
            $request->string('tenant_slug')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return response()->json([
            'token' => $result['plain_text_token'],
            'token_type' => 'Bearer',
            'user' => UserData::fromModel($result['user']),
            'tenant' => TenantData::fromModel($result['tenant']),
        ], 200);
    }
}
