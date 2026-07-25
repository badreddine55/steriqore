<?php

use App\Domain\Identity\Actions\SeedTenantRolesAction;
use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

function makeTenantMember(string $status = 'active'): array
{
    $tenant = Tenant::create(['name' => 'Cabinet Test', 'slug' => 'cabinet-test-'.uniqid()]);

    $user = User::create([
        'name' => 'Alice Dupont',
        'email' => 'alice-'.uniqid().'@example.com',
        'password' => Hash::make('correct-password'),
    ]);

    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => $status,
    ]);

    app(SeedTenantRolesAction::class)->execute($tenant);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);
    $user->assignRole(TenantRole::Owner->value);
    $registrar->setPermissionsTeamId(null);

    return [$tenant, $user];
}

test('a member can log in and receive a tenant-scoped token', function () {
    [$tenant, $user] = makeTenantMember();

    $response = $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $user->email,
        'password' => 'correct-password',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertOk()
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonPath('tenant.slug', $tenant->slug)
        ->assertJsonPath('token_type', 'Bearer');

    expect($response->json('token'))->toBeString();
});

test('wrong password is rejected with a stable error code', function () {
    [$tenant, $user] = makeTenantMember();

    $response = $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $user->email,
        'password' => 'wrong-password',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertStatus(401)
        ->assertJsonPath('error.code', 'INVALID_CREDENTIALS')
        ->assertJsonPath('error.request_id', fn ($id) => is_string($id) && $id !== '');
});

test('an unknown tenant slug is rejected', function () {
    [, $user] = makeTenantMember();

    $response = $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => 'does-not-exist',
        'email' => $user->email,
        'password' => 'correct-password',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertStatus(404)->assertJsonPath('error.code', 'TENANT_NOT_FOUND');
});

test('a disabled membership cannot log in', function () {
    [$tenant, $user] = makeTenantMember(status: 'disabled');

    $response = $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $user->email,
        'password' => 'correct-password',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertStatus(403)->assertJsonPath('error.code', 'TENANT_MEMBERSHIP_DISABLED');
});

test('login without an Idempotency-Key is rejected', function () {
    [$tenant, $user] = makeTenantMember();

    $response = $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $user->email,
        'password' => 'correct-password',
    ]);

    $response->assertStatus(400)->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REQUIRED');
});

test('replaying the same Idempotency-Key returns the same response without minting a new token', function () {
    [$tenant, $user] = makeTenantMember();
    $idempotencyKey = (string) Str::uuid();
    $payload = [
        'tenant_slug' => $tenant->slug,
        'email' => $user->email,
        'password' => 'correct-password',
    ];

    $first = $this->postJson('/api/v1/auth/login', $payload, ['Idempotency-Key' => $idempotencyKey]);
    $second = $this->postJson('/api/v1/auth/login', $payload, ['Idempotency-Key' => $idempotencyKey]);

    expect($second->json('token'))->toBe($first->json('token'));
    expect($user->tokens()->count())->toBe(1);
});

test('reusing an Idempotency-Key with a different payload is rejected', function () {
    [$tenant, $user] = makeTenantMember();
    $idempotencyKey = (string) Str::uuid();

    $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $user->email,
        'password' => 'correct-password',
    ], ['Idempotency-Key' => $idempotencyKey]);

    $response = $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $user->email,
        'password' => 'wrong-password',
    ], ['Idempotency-Key' => $idempotencyKey]);

    $response->assertStatus(409)->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REUSED');
});

test('an authenticated member can fetch their own profile and sites, scoped to their tenant', function () {
    [$tenant, $user] = makeTenantMember();
    $tenant->sites()->create(['name' => 'Cabinet Principal']);

    [$otherTenant] = makeTenantMember();
    $otherTenant->sites()->create(['name' => 'Cabinet Autre Pratique']);

    $login = $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $user->email,
        'password' => 'correct-password',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $token = $login->json('token');

    $me = $this->withToken($token)->getJson('/api/v1/me');
    $me->assertOk()->assertJsonPath('tenant.slug', $tenant->slug);

    $sites = $this->withToken($token)->getJson('/api/v1/sites');
    $sites->assertOk();
    expect(collect($sites->json('data'))->pluck('name')->all())->toBe(['Cabinet Principal']);
});

test('an unauthenticated request to a protected endpoint is rejected with the standard error shape', function () {
    $response = $this->getJson('/api/v1/me');

    $response->assertStatus(401)
        ->assertJsonPath('error.code', 'UNAUTHENTICATED')
        ->assertJsonStructure(['error' => ['code', 'message', 'details', 'request_id']]);
});
