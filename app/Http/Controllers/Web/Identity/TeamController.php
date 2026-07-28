<?php

namespace App\Http\Controllers\Web\Identity;

use App\Domain\Identity\Actions\CreateInvitationAction;
use App\Domain\Identity\Actions\DisableTenantMembershipAction;
use App\Domain\Identity\Actions\RevokeInvitationAction;
use App\Domain\Identity\Data\InvitationData;
use App\Domain\Identity\Data\TeamMemberData;
use App\Domain\Identity\Models\Invitation;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\CreateInvitationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Same CreateInvitationAction/RevokeInvitationAction/
 * DisableTenantMembershipAction the mobile API uses (Api\V1\Identity) —
 * this is the first list view for either TenantUser or Invitation
 * anywhere (the API only ever had store/destroy, no index), so the
 * membership + invitation queries here are new, read-only additions,
 * not new business logic. TenantUser/Invitation are deliberately NOT
 * BelongsToTenant-scoped (resolved during auth bootstrap, before any
 * "current tenant" exists) — every query and every mutation here checks
 * tenant_id by hand, exactly like MemberController/InvitationController
 * already do.
 */
class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('create', Invitation::class);

        $tenant = Tenant::current();

        $members = TenantUser::where('tenant_id', $tenant->id)
            ->with('user')
            ->orderBy('joined_at')
            ->get()
            ->map(fn (TenantUser $membership) => TeamMemberData::fromModel($membership, $membership->user->getRoleNames()->first()));

        $invitations = Invitation::where('tenant_id', $tenant->id)
            ->with('invitedBy')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('team/index', [
            'members' => TeamMemberData::collect($members),
            'invitations' => InvitationData::collect($invitations),
            'currentUserId' => $request->user()->id,
        ]);
    }

    public function storeInvitation(CreateInvitationRequest $request, CreateInvitationAction $action): RedirectResponse
    {
        $this->authorize('create', Invitation::class);

        $action->execute(
            tenant: Tenant::current(),
            invitedBy: $request->user(),
            email: $request->string('email')->toString(),
            role: $request->string('role')->toString(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invitation sent.']);

        return back();
    }

    public function revokeInvitation(Invitation $invitation, Request $request, RevokeInvitationAction $action): RedirectResponse
    {
        if ($invitation->tenant_id !== Tenant::current()->id) {
            abort(404);
        }

        $this->authorize('delete', $invitation);

        $action->execute($invitation, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invitation revoked.']);

        return back();
    }

    public function disableMember(TenantUser $tenantUser, Request $request, DisableTenantMembershipAction $action): RedirectResponse
    {
        if ($tenantUser->tenant_id !== Tenant::current()->id) {
            abort(404);
        }

        $this->authorize('disable', $tenantUser);

        $action->execute($tenantUser, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Member access disabled.']);

        return back();
    }
}
