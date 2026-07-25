<?php

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domain\Identity\Actions\DisableTenantMembershipAction;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Support\Api\ApiException;
use Illuminate\Http\Response;

class MemberController extends Controller
{
    /**
     * Disables a member's access to this practice — see
     * DisableTenantMembershipAction. Not BelongsToTenant-scoped (same
     * bootstrap reason as Invitation), so the tenant match is checked
     * before authorize(), same IDOR-safe pattern as InvitationController.
     */
    public function destroy(TenantUser $tenantUser, DisableTenantMembershipAction $action): Response
    {
        if ($tenantUser->tenant_id !== Tenant::current()->id) {
            throw new ApiException('NOT_FOUND', 'The requested resource was not found.', 404);
        }

        $this->authorize('disable', $tenantUser);

        $action->execute($tenantUser);

        return response()->noContent();
    }
}
