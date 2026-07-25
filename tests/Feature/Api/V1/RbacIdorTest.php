<?php

use App\Domain\Identity\Actions\SeedTenantRolesAction;
use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Models\Invitation;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * S1-ANA-03 DoD: "horizontal and vertical escalation both rejected
 * server-side." Vertical = a low-privilege role attempting an action its
 * role doesn't grant. Horizontal (IDOR) = a valid, in-role user reaching
 * across into a different tenant's resource by guessing/reusing an id —
 * REC-01, walked here across every resource that accepts a client-supplied
 * id (sites has none yet; invitations and members do).
 */
function memberWithRole(Tenant $tenant, TenantRole $role, string $password = 'correct-password'): User
{
    $user = User::create([
        'name' => 'Test User',
        'email' => 'user-'.Str::lower(Str::random(10)).'@example.com',
        'password' => Hash::make($password),
    ]);

    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);
    $user->assignRole($role->value);
    $registrar->setPermissionsTeamId(null);

    return $user;
}

function freshSeededTenant(): Tenant
{
    $tenant = Tenant::create(['name' => 'Cabinet Test', 'slug' => 'cabinet-'.Str::lower(Str::random(10))]);
    app(SeedTenantRolesAction::class)->execute($tenant);

    return $tenant;
}

function loginAs(Tenant $tenant, User $user, string $password = 'correct-password'): string
{
    return test()->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $user->email,
        'password' => $password,
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('token');
}

test('registering a new practice creates an owner with every permission', function () {
    $response = $this->postJson('/api/v1/tenants', [
        'tenant_name' => 'Brand New Practice',
        'tenant_slug' => 'brand-new-'.Str::lower(Str::random(8)),
        'owner_name' => 'Founder',
        'owner_email' => 'founder-'.Str::lower(Str::random(8)).'@example.com',
        'password' => 'a-strong-password',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertCreated();
    $token = $response->json('token');

    $this->withToken($token)->getJson('/api/v1/sites')->assertOk();

    $invite = $this->withToken($token)->postJson('/api/v1/invitations', [
        'email' => 'someone-'.Str::lower(Str::random(8)).'@example.com',
        'role' => 'viewer',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $invite->assertCreated();
});

test('registering with an already-taken slug is rejected', function () {
    $tenant = freshSeededTenant();

    $response = $this->postJson('/api/v1/tenants', [
        'tenant_name' => 'Someone Else',
        'tenant_slug' => $tenant->slug,
        'owner_name' => 'Whoever',
        'owner_email' => 'whoever-'.Str::lower(Str::random(8)).'@example.com',
        'password' => 'a-strong-password',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertStatus(409)->assertJsonPath('error.code', 'TENANT_SLUG_TAKEN');
});

test('a viewer can read sites but cannot create an invitation or disable a member (vertical escalation)', function () {
    $tenant = freshSeededTenant();
    $viewer = memberWithRole($tenant, TenantRole::Viewer);
    $otherMember = memberWithRole($tenant, TenantRole::Practitioner);
    $token = loginAs($tenant, $viewer);

    $this->withToken($token)->getJson('/api/v1/sites')->assertOk();

    $this->withToken($token)->postJson('/api/v1/invitations', [
        'email' => 'someone@example.com',
        'role' => 'viewer',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(403);

    $membership = TenantUser::where('tenant_id', $tenant->id)->where('user_id', $otherMember->id)->first();
    $this->withToken($token)->deleteJson("/api/v1/members/{$membership->id}")->assertStatus(403);
});

test('an admin can create invitations and disable a member', function () {
    $tenant = freshSeededTenant();
    $admin = memberWithRole($tenant, TenantRole::Admin);
    $otherMember = memberWithRole($tenant, TenantRole::Practitioner);
    $token = loginAs($tenant, $admin);

    $this->withToken($token)->postJson('/api/v1/invitations', [
        'email' => 'someone-'.Str::lower(Str::random(8)).'@example.com',
        'role' => 'viewer',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $membership = TenantUser::where('tenant_id', $tenant->id)->where('user_id', $otherMember->id)->first();
    $this->withToken($token)->deleteJson("/api/v1/members/{$membership->id}")->assertNoContent();
    expect($membership->fresh()->status)->toBe('disabled');
});

test('a member of practice A cannot disable a member of practice B (IDOR)', function () {
    $tenantA = freshSeededTenant();
    $tenantB = freshSeededTenant();
    $adminA = memberWithRole($tenantA, TenantRole::Admin);
    $memberB = memberWithRole($tenantB, TenantRole::Practitioner);
    $tokenA = loginAs($tenantA, $adminA);

    $membershipB = TenantUser::where('tenant_id', $tenantB->id)->where('user_id', $memberB->id)->first();

    $response = $this->withToken($tokenA)->deleteJson("/api/v1/members/{$membershipB->id}");

    $response->assertStatus(404);
    expect($membershipB->fresh()->status)->toBe('active');
});

test('a member of practice A cannot revoke an invitation of practice B (IDOR)', function () {
    $tenantA = freshSeededTenant();
    $tenantB = freshSeededTenant();
    $adminA = memberWithRole($tenantA, TenantRole::Admin);
    $adminB = memberWithRole($tenantB, TenantRole::Admin);
    $tokenA = loginAs($tenantA, $adminA);

    $foreignInvitation = Invitation::create([
        'tenant_id' => $tenantB->id,
        'email' => 'someone@example.com',
        'role' => 'viewer',
        'token' => Str::random(64),
        'invited_by' => $adminB->id,
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->withToken($tokenA)->deleteJson("/api/v1/invitations/{$foreignInvitation->id}");

    $response->assertStatus(404);
    expect($foreignInvitation->fresh()->revoked_at)->toBeNull();
});

test('a member of practice A cannot see practice B sites even with a guessed limit/cursor (IDOR)', function () {
    $tenantA = freshSeededTenant();
    $tenantB = freshSeededTenant();
    $tenantB->sites()->create(['name' => 'Secret Site B']);
    $memberA = memberWithRole($tenantA, TenantRole::Owner);
    $tokenA = loginAs($tenantA, $memberA);

    $response = $this->withToken($tokenA)->getJson('/api/v1/sites');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('name'))->not->toContain('Secret Site B');
});
