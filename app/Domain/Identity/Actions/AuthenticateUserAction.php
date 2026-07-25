<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Support\Facades\Hash;

/**
 * Mints a Sanctum token scoped to exactly one tenant, chosen at login (see
 * docs/erd.md decision #3). There is no mid-session tenant switch: a member
 * of two practices logs in twice and holds two tokens.
 */
class AuthenticateUserAction
{
    public function __construct(private MintTenantTokenAction $mintTenantToken) {}

    /**
     * @return array{plain_text_token: string, user: User, tenant: Tenant}
     */
    public function execute(string $tenantSlug, string $email, string $password): array
    {
        $tenant = Tenant::where('slug', $tenantSlug)->first();

        if (! $tenant) {
            throw new ApiException('TENANT_NOT_FOUND', 'No practice matches this identifier.', 404);
        }

        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new ApiException('INVALID_CREDENTIALS', 'These credentials do not match our records.', 401);
        }

        if ($user->disabled_at !== null) {
            throw new ApiException('USER_DISABLED', 'This account has been disabled.', 403);
        }

        $membership = $user->tenantUsers()->where('tenant_id', $tenant->id)->first();

        if (! $membership) {
            throw new ApiException('TENANT_ACCESS_DENIED', 'This account has no access to this practice.', 403);
        }

        if ($membership->status === 'disabled') {
            throw new ApiException('TENANT_MEMBERSHIP_DISABLED', 'Access to this practice has been disabled.', 403);
        }

        if ($membership->status === 'invited') {
            throw new ApiException('TENANT_MEMBERSHIP_PENDING', 'This invitation has not been accepted yet.', 403);
        }

        return [
            'plain_text_token' => $this->mintTenantToken->execute($user, $tenant, 'mobile'),
            'user' => $user,
            'tenant' => $tenant,
        ];
    }
}
