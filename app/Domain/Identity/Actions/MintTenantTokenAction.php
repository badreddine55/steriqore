<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * A Sanctum token authenticates for exactly one tenant, chosen at the moment
 * it's minted (login, invitation acceptance) — see docs/erd.md decision #3.
 * Shared by every flow that needs to hand back a working token.
 */
class MintTenantTokenAction
{
    public function execute(User $user, Tenant $tenant, string $name = 'api'): string
    {
        $plainTextToken = Str::random(40);

        $token = $user->tokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => ['*'],
            'tenant_id' => $tenant->id,
        ]);

        return $token->getKey().'|'.$plainTextToken;
    }
}
