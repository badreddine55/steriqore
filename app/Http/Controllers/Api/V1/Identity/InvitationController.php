<?php

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domain\Identity\Actions\CreateInvitationAction;
use App\Domain\Identity\Actions\RevokeInvitationAction;
use App\Domain\Identity\Data\InvitationData;
use App\Domain\Identity\Models\Invitation;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\CreateInvitationRequest;
use App\Support\Api\ApiException;
use Illuminate\Http\Response;

class InvitationController extends Controller
{
    public function store(CreateInvitationRequest $request, CreateInvitationAction $action): InvitationData
    {
        $invitation = $action->execute(
            tenant: Tenant::current(),
            invitedBy: $request->user(),
            email: $request->string('email')->toString(),
            role: $request->string('role')->toString(),
        );

        return InvitationData::fromModel($invitation);
    }

    public function destroy(Invitation $invitation, RevokeInvitationAction $action): Response
    {
        // Not BelongsToTenant-scoped (see Invitation's docblock) — checked
        // by hand. 404, not 403: a foreign-tenant invitation id must not
        // even reveal that it exists.
        if ($invitation->tenant_id !== Tenant::current()->id) {
            throw new ApiException('NOT_FOUND', 'The requested resource was not found.', 404);
        }

        $action->execute($invitation);

        return response()->noContent();
    }
}
