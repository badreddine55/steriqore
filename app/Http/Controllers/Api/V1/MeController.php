<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Data\UserData;
use App\Domain\Identity\Models\PersonalAccessToken;
use App\Domain\Tenancy\Data\TenantData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * The authenticated user and the practice this token is scoped to.
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var PersonalAccessToken $token */
        $token = $request->user()->currentAccessToken();

        return response()->json([
            'user' => UserData::fromModel($request->user()),
            'tenant' => TenantData::fromModel($token->tenant),
        ]);
    }
}
