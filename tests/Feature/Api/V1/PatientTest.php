<?php

use App\Domain\Identity\Actions\SeedTenantRolesAction;
use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Traceability\Models\Patient;
use App\Models\User;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

function traceabilityTenantWithMember(TenantRole $role = TenantRole::Practitioner): array
{
    $tenant = Tenant::create(['name' => 'Cabinet Test', 'slug' => 'cabinet-'.Str::lower(Str::random(10))]);
    $user = User::create([
        'name' => 'Alice Dupont',
        'email' => 'alice-'.Str::lower(Str::random(10)).'@example.com',
        'password' => Hash::make('correct-password'),
    ]);
    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);

    app(SeedTenantRolesAction::class)->execute($tenant);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);
    $user->assignRole($role->value);
    $registrar->setPermissionsTeamId(null);

    return [$tenant, $user];
}

function traceabilityTokenFor(Tenant $tenant, User $user): string
{
    return test()->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $user->email,
        'password' => 'correct-password',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('token');
}

test('a practitioner can create a pseudonymized patient reference with no identifying fields accepted', function () {
    [$tenant, $user] = traceabilityTenantWithMember();
    $token = traceabilityTokenFor($tenant, $user);

    $create = $this->withToken($token)->postJson('/api/v1/patients', [
        'name' => 'Jean Dupont', 'date_of_birth' => '1980-01-01', 'email' => 'jean@example.com',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $create->assertCreated();
    expect($create->json('reference'))->toMatch('/^PAT-\d{6}$/');
    expect($create->json())->toHaveKeys(['id', 'reference']);
    expect($create->json())->not->toHaveKey('name');
    expect($create->json())->not->toHaveKey('date_of_birth');
});

test('patient references are sequential per tenant', function () {
    [$tenant, $user] = traceabilityTenantWithMember();
    $token = traceabilityTokenFor($tenant, $user);

    $first = $this->withToken($token)->postJson('/api/v1/patients', [], ['Idempotency-Key' => (string) Str::uuid()]);
    $second = $this->withToken($token)->postJson('/api/v1/patients', [], ['Idempotency-Key' => (string) Str::uuid()]);

    expect($first->json('reference'))->toBe('PAT-000001');
    expect($second->json('reference'))->toBe('PAT-000002');
});

test('two different tenants each start their own reference sequence at 1', function () {
    [$tenantA, $userA] = traceabilityTenantWithMember();
    [$tenantB, $userB] = traceabilityTenantWithMember();
    $tokenA = traceabilityTokenFor($tenantA, $userA);
    $tokenB = traceabilityTokenFor($tenantB, $userB);

    $a = $this->withToken($tokenA)->postJson('/api/v1/patients', [], ['Idempotency-Key' => (string) Str::uuid()]);
    $b = $this->withToken($tokenB)->postJson('/api/v1/patients', [], ['Idempotency-Key' => (string) Str::uuid()]);

    expect($a->json('reference'))->toBe('PAT-000001');
    expect($b->json('reference'))->toBe('PAT-000001');
});

test('a patient can be archived (soft delete) and past evidence keeps its identity', function () {
    [$tenant, $user] = traceabilityTenantWithMember();
    $token = traceabilityTokenFor($tenant, $user);
    $patientId = $this->withToken($token)->postJson('/api/v1/patients', [], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $this->withToken($token)->deleteJson("/api/v1/patients/{$patientId}")->assertNoContent();

    $patient = Patient::withoutGlobalScope(TenantScope::class)->withTrashed()->findOrFail($patientId);
    expect($patient->trashed())->toBeTrue();
    expect($patient->reference)->not->toBeEmpty();
});

test('a viewer can read patients but cannot create one (vertical escalation)', function () {
    [$tenant, $user] = traceabilityTenantWithMember(TenantRole::Viewer);
    $token = traceabilityTokenFor($tenant, $user);

    $this->withToken($token)->getJson('/api/v1/patients')->assertOk();
    $this->withToken($token)->postJson('/api/v1/patients', [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(403);
});

test('a practice never reaches another practice patient, even by guessing an id (IDOR)', function () {
    [$tenantA, $userA] = traceabilityTenantWithMember();
    [$tenantB, $userB] = traceabilityTenantWithMember();
    $tokenA = traceabilityTokenFor($tenantA, $userA);
    $tokenB = traceabilityTokenFor($tenantB, $userB);

    $foreignPatientId = $this->withToken($tokenB)->postJson('/api/v1/patients', [], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $this->withToken($tokenA)->getJson("/api/v1/patients/{$foreignPatientId}")->assertStatus(404);
});
