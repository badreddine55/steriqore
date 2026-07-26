<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Api\ApiException;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Self-serve trial signup: a brand new practice plus its first user, who
 * always becomes `owner` — there is no "invite the first person" case,
 * someone has to be able to create the practice in the first place.
 */
class RegisterTenantAction
{
    public function __construct(
        private SeedTenantRolesAction $seedTenantRoles,
        private MintTenantTokenAction $mintTenantToken,
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @return array{plain_text_token: string, user: User, tenant: Tenant}
     */
    public function execute(string $tenantName, string $tenantSlug, string $ownerName, string $ownerEmail, string $password): array
    {
        if (Tenant::where('slug', $tenantSlug)->exists()) {
            throw new ApiException('TENANT_SLUG_TAKEN', 'This practice identifier is already in use.', 409);
        }

        if (User::where('email', $ownerEmail)->exists()) {
            throw new ApiException('EMAIL_ALREADY_REGISTERED', 'An account already exists for this email.', 409);
        }

        [$tenant, $owner] = DB::transaction(function () use ($tenantName, $tenantSlug, $ownerName, $ownerEmail, $password) {
            $tenant = Tenant::create([
                'name' => $tenantName,
                'slug' => $tenantSlug,
                'plan' => 'trial',
                'status' => 'trialing',
                'trial_ends_at' => now()->addDays(30),
            ]);

            // roles/model_has_roles are RLS-protected (S1-ANA-03) — nothing
            // is "the current tenant" yet at this point in a brand new
            // registration, so without this, SeedTenantRolesAction's own
            // inserts and assignRole() below fail against the restricted
            // DB role the app actually runs as (only ever masked in tests,
            // whose default connection is the elevated migrations role).
            return TenantContext::run($tenant, function () use ($tenant, $ownerName, $ownerEmail, $password) {
                $owner = User::create([
                    'name' => $ownerName,
                    'email' => $ownerEmail,
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                ]);

                TenantUser::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $owner->id,
                    'status' => 'active',
                    'joined_at' => now(),
                ]);

                $this->seedTenantRoles->execute($tenant);
                $owner->assignRole(TenantRole::Owner->value);

                return [$tenant, $owner];
            });
        });

        $this->recordAuditEvent->execute(
            tenant: $tenant,
            actor: $owner,
            action: 'tenant.registered',
            subjectType: Tenant::class,
            subjectId: $tenant->id,
            newValues: ['name' => $tenantName, 'slug' => $tenantSlug],
            request: $this->request,
        );

        return [
            'plain_text_token' => $this->mintTenantToken->execute($owner, $tenant, 'mobile'),
            'user' => $owner,
            'tenant' => $tenant,
        ];
    }
}
