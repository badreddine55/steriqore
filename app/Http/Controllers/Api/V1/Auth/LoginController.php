<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Actions\AuthenticateUserAction;
use App\Domain\Identity\Data\UserData;
use App\Domain\Tenancy\Data\TenantData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    /**
     * Log in and receive a Sanctum token scoped to one practice.
     */
    public function __invoke(LoginRequest $request, AuthenticateUserAction $action): JsonResponse
    {
        $result = $action->execute(
            $request->string('tenant_slug')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        $userData = TenantContext::run($result['tenant'], function () use ($result) {
            return UserData::fromModel($result['user']);
        });

        return response()->json([
            'token' => $result['plain_text_token'],
            'token_type' => 'Bearer',
            'user' => $userData,
            'tenant' => TenantData::fromModel($result['tenant']),
        ], 200);
    }
}
