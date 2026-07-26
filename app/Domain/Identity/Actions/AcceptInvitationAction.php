<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Identity\Models\Invitation;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Api\ApiException;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AcceptInvitationAction
{
    public function __construct(
        private MintTenantTokenAction $mintTenantToken,
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @return array{plain_text_token: string, user: User, tenant: Tenant}
     */
    public function execute(string $token, string $name, string $password): array
    {
        $invitation = Invitation::where('token', $token)->first();

        if (! $invitation) {
            throw new ApiException('INVITATION_NOT_FOUND', 'This invitation does not exist.', 404);
        }

        if ($invitation->revoked_at !== null) {
            throw new ApiException('INVITATION_REVOKED', 'This invitation has been revoked.', 410);
        }

        if ($invitation->accepted_at !== null) {
            throw new ApiException('INVITATION_ALREADY_ACCEPTED', 'This invitation has already been accepted.', 409);
        }

        if ($invitation->expires_at->isPast()) {
            throw new ApiException('INVITATION_EXPIRED', 'This invitation has expired.', 410);
        }

        $user = User::where('email', $invitation->email)->first();

        if (! $user) {
            $user = User::create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => Hash::make($password),
                // A targeted invitation to a known address stands in for
                // explicit email verification.
                'email_verified_at' => now(),
            ]);
        } else {
            // The email already belongs to an account — whoever invited it
            // doesn't get to choose that account's password. Accepting on
            // behalf of an existing user still requires proving you know
            // it, exactly like a normal login; otherwise inviting someone
            // else's known email would silently mint a token as them.
            if ($user->disabled_at !== null) {
                throw new ApiException('USER_DISABLED', 'This account has been disabled.', 403);
            }

            if (! Hash::check($password, $user->password)) {
                throw new ApiException('INVALID_CREDENTIALS', 'These credentials do not match our records.', 401);
            }
        }

        $tenant = $invitation->tenant;

        // model_has_roles is RLS-protected (S1-ANA-03) — this whole flow
        // runs before any middleware binds "the current tenant", so
        // syncRoles() below fails against the restricted DB role the app
        // actually runs as without this (only ever masked in tests, whose
        // default connection is the elevated migrations role).
        TenantContext::run($tenant, function () use ($tenant, $user, $invitation) {
            TenantUser::updateOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                ['status' => 'active', 'invited_at' => $invitation->created_at, 'joined_at' => now(), 'disabled_at' => null],
            );

            $user->syncRoles([$invitation->role]);

            $invitation->update(['accepted_at' => now()]);
        });

        $this->recordAuditEvent->execute(
            tenant: $tenant,
            actor: $user,
            action: 'invitation.accepted',
            subjectType: Invitation::class,
            subjectId: $invitation->id,
            newValues: ['role' => $invitation->role],
            request: $this->request,
        );

        return [
            'plain_text_token' => $this->mintTenantToken->execute($user, $tenant, 'mobile'),
            'user' => $user,
            'tenant' => $tenant,
        ];
    }
}
