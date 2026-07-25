<?php

use App\Domain\Identity\Actions\DisableTenantMembershipAction;
use App\Domain\Identity\Actions\SeedTenantRolesAction;
use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Mail\InvitationMail;
use App\Domain\Identity\Models\Invitation;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Owner by default: has every permission this file's tests exercise
 * (invitations.create/revoke). Pass a different role to test the
 * permission boundary itself.
 */
function tenantWithActiveMember(string $password = 'correct-password', TenantRole $role = TenantRole::Owner): array
{
    $tenant = Tenant::create(['name' => 'Cabinet Test', 'slug' => 'cabinet-'.Str::lower(Str::random(10))]);

    $user = User::create([
        'name' => 'Alice Dupont',
        'email' => 'alice-'.Str::lower(Str::random(10)).'@example.com',
        'password' => Hash::make($password),
    ]);

    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);

    app(SeedTenantRolesAction::class)->execute($tenant);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);
    $user->assignRole($role->value);
    $registrar->setPermissionsTeamId(null);

    return [$tenant, $user];
}

function tokenFor(Tenant $tenant, User $user, string $password = 'correct-password'): string
{
    return test()->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $user->email,
        'password' => $password,
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('token');
}

test('a member can invite a new person and an email is queued', function () {
    Mail::fake();
    [$tenant, $user] = tenantWithActiveMember();
    $token = tokenFor($tenant, $user);

    $response = $this->withToken($token)->postJson('/api/v1/invitations', [
        'email' => 'new-person@example.com',
        'role' => 'practitioner',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertCreated()->assertJsonPath('email', 'new-person@example.com');
    Mail::assertQueued(InvitationMail::class);
    expect(Invitation::where('email', 'new-person@example.com')->where('tenant_id', $tenant->id)->exists())->toBeTrue();
});

test('inviting an existing member is rejected', function () {
    [$tenant, $user] = tenantWithActiveMember();
    $token = tokenFor($tenant, $user);

    $response = $this->withToken($token)->postJson('/api/v1/invitations', [
        'email' => $user->email,
        'role' => 'practitioner',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertStatus(409)->assertJsonPath('error.code', 'USER_ALREADY_MEMBER');
});

test('a second pending invitation for the same email is rejected', function () {
    [$tenant, $user] = tenantWithActiveMember();
    $token = tokenFor($tenant, $user);

    $this->withToken($token)->postJson('/api/v1/invitations', [
        'email' => 'dup@example.com', 'role' => 'viewer',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $response = $this->withToken($token)->postJson('/api/v1/invitations', [
        'email' => 'dup@example.com', 'role' => 'viewer',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertStatus(409)->assertJsonPath('error.code', 'INVITATION_ALREADY_PENDING');
});

test('accepting an invitation for a brand new email creates the account and a tenant-scoped token', function () {
    [$tenant, $inviter] = tenantWithActiveMember();
    $invitation = Invitation::create([
        'tenant_id' => $tenant->id,
        'email' => 'new-person@example.com',
        'role' => 'practitioner',
        'token' => Str::random(64),
        'invited_by' => $inviter->id,
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->postJson('/api/v1/invitations/accept', [
        'token' => $invitation->token,
        'name' => 'New Person',
        'password' => 'a-brand-new-password',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertOk()->assertJsonPath('tenant.slug', $tenant->slug);
    expect(User::where('email', 'new-person@example.com')->exists())->toBeTrue();
    expect(TenantUser::where('tenant_id', $tenant->id)
        ->whereHas('user', fn ($q) => $q->where('email', 'new-person@example.com'))
        ->where('status', 'active')->exists())->toBeTrue();
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('accepting an invitation for an existing email requires that account real password', function () {
    [$tenantA, $existingUser] = tenantWithActiveMember('the-real-password');
    [$tenantB, $inviter] = tenantWithActiveMember();

    $invitation = Invitation::create([
        'tenant_id' => $tenantB->id,
        'email' => $existingUser->email,
        'role' => 'viewer',
        'token' => Str::random(64),
        'invited_by' => $inviter->id,
        'expires_at' => now()->addDays(7),
    ]);

    $wrongPassword = $this->postJson('/api/v1/invitations/accept', [
        'token' => $invitation->token,
        'name' => 'Doesnt matter',
        'password' => 'guessed-wrong-password',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $wrongPassword->assertStatus(401)->assertJsonPath('error.code', 'INVALID_CREDENTIALS');

    $rightPassword = $this->postJson('/api/v1/invitations/accept', [
        'token' => $invitation->token,
        'name' => 'Doesnt matter',
        'password' => 'the-real-password',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $rightPassword->assertOk()->assertJsonPath('tenant.slug', $tenantB->slug);
});

test('accepting an expired invitation is rejected', function () {
    [$tenant, $inviter] = tenantWithActiveMember();
    $invitation = Invitation::create([
        'tenant_id' => $tenant->id,
        'email' => 'late@example.com',
        'role' => 'viewer',
        'token' => Str::random(64),
        'invited_by' => $inviter->id,
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->postJson('/api/v1/invitations/accept', [
        'token' => $invitation->token,
        'name' => 'Late Person',
        'password' => 'whatever-password',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertStatus(410)->assertJsonPath('error.code', 'INVITATION_EXPIRED');
});

test('a revoked invitation can no longer be accepted', function () {
    [$tenant, $user] = tenantWithActiveMember();
    $token = tokenFor($tenant, $user);

    $invitation = Invitation::create([
        'tenant_id' => $tenant->id,
        'email' => 'revoke-me@example.com',
        'role' => 'viewer',
        'token' => Str::random(64),
        'invited_by' => $user->id,
        'expires_at' => now()->addDays(7),
    ]);

    $this->withToken($token)->deleteJson("/api/v1/invitations/{$invitation->id}")->assertNoContent();

    $response = $this->postJson('/api/v1/invitations/accept', [
        'token' => $invitation->token,
        'name' => 'Nope',
        'password' => 'whatever-password',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertStatus(410)->assertJsonPath('error.code', 'INVITATION_REVOKED');
});

test('revoking a foreign tenant invitation is refused without leaking its existence', function () {
    [$tenantA, $userA] = tenantWithActiveMember();
    [$tenantB, $userB] = tenantWithActiveMember();
    $tokenA = tokenFor($tenantA, $userA);

    $foreignInvitation = Invitation::create([
        'tenant_id' => $tenantB->id,
        'email' => 'someone@example.com',
        'role' => 'viewer',
        'token' => Str::random(64),
        'invited_by' => $userB->id,
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->withToken($tokenA)->deleteJson("/api/v1/invitations/{$foreignInvitation->id}");

    $response->assertStatus(404);
    expect($foreignInvitation->fresh()->revoked_at)->toBeNull();
});

test('logout-everywhere revokes every token for the current tenant but not other tenants', function () {
    [$tenant, $user] = tenantWithActiveMember();
    [$otherTenant] = tenantWithActiveMember();
    TenantUser::create(['tenant_id' => $otherTenant->id, 'user_id' => $user->id, 'status' => 'active']);

    $tokenTenantA1 = tokenFor($tenant, $user);
    $tokenTenantA2 = tokenFor($tenant, $user);
    $tokenOtherTenant = tokenFor($otherTenant, $user);

    $this->withToken($tokenTenantA1)->deleteJson('/api/v1/auth/tokens')->assertNoContent();

    $this->withToken($tokenTenantA2)->getJson('/api/v1/me')->assertStatus(401);
    $this->withToken($tokenOtherTenant)->getJson('/api/v1/me')->assertOk();
});

test('disabling a membership revokes its tokens for that tenant only', function () {
    [$tenant, $user] = tenantWithActiveMember();
    [$otherTenant] = tenantWithActiveMember();
    TenantUser::create(['tenant_id' => $otherTenant->id, 'user_id' => $user->id, 'status' => 'active']);

    $tokenA = tokenFor($tenant, $user);
    $tokenOther = tokenFor($otherTenant, $user);

    $membership = TenantUser::where('tenant_id', $tenant->id)->where('user_id', $user->id)->first();
    app(DisableTenantMembershipAction::class)->execute($membership);

    $this->withToken($tokenA)->getJson('/api/v1/me')->assertStatus(401);
    $this->withToken($tokenOther)->getJson('/api/v1/me')->assertOk();
    expect($membership->fresh()->status)->toBe('disabled');
});
