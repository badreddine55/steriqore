<?php

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domain\Identity\Actions\RegisterTenantAction;
use App\Domain\Identity\Data\UserData;
use App\Domain\Tenancy\Data\TenantData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\RegisterTenantRequest;
use Illuminate\Http\JsonResponse;

class RegisterTenantController extends Controller
{
    /**
     * Self-serve trial signup. Public — there is no existing account or
     * practice yet. The caller becomes `owner` of the new practice.
     */
    public function __invoke(RegisterTenantRequest $request, RegisterTenantAction $action): JsonResponse
    {
        $result = $action->execute(
            tenantName: $request->string('tenant_name')->toString(),
            tenantSlug: $request->string('tenant_slug')->toString(),
            ownerName: $request->string('owner_name')->toString(),
            ownerEmail: $request->string('owner_email')->toString(),
            password: $request->string('password')->toString(),
        );

        return response()->json([
            'token' => $result['plain_text_token'],
            'token_type' => 'Bearer',
            'user' => UserData::fromModel($result['user']),
            'tenant' => TenantData::fromModel($result['tenant']),
        ], 201);
    }
}
