<?php

use App\Domain\Identity\Actions\SeedTenantRolesAction;
use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Traceability\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

function portalTenantWithMember(TenantRole $role = TenantRole::Practitioner): array
{
    $tenant = Tenant::create(['name' => 'Cabinet Test', 'slug' => 'cabinet-'.Str::lower(Str::random(10))]);
    $user = User::create([
        'name' => 'Dr. Dupont',
        'email' => 'dupont-'.Str::lower(Str::random(10)).'@example.com',
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

function portalStaffToken(Tenant $tenant, User $user): string
{
    return test()->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $user->email,
        'password' => 'correct-password',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('token');
}

test('staff can create a portal account for a patient directly with email and default password', function () {
    [$tenant, $user] = portalTenantWithMember();
    $token = portalStaffToken($tenant, $user);

    $patient = Patient::create(['tenant_id' => $tenant->id, 'reference' => 'PAT-000001']);
    $email = 'patient1-'.Str::lower(Str::random(8)).'@example.com';

    $createResp = $this->withToken($token)->postJson("/api/v1/patients/{$patient->id}/portal-account", [
        'email' => $email,
    ], [
        'Idempotency-Key' => (string) Str::uuid(),
    ]);

    $createResp->assertCreated();
    expect($createResp->json('patient_reference'))->toBe('PAT-000001');
    expect($createResp->json('email'))->toBe($email);
    expect($createResp->json('status'))->toBe('active');
    expect($createResp->json('default_password'))->toBe('password');

    // Patient can log in immediately with email + default "password"
    $loginResp = $this->postJson('/api/v1/patient-portal/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $email,
        'password' => 'password',
    ], [
        'Idempotency-Key' => (string) Str::uuid(),
    ]);

    $loginResp->assertOk();
    expect($loginResp->json('token'))->not->toBeEmpty();
    expect($loginResp->json('patient_reference'))->toBe('PAT-000001');
});

test('cannot create a second portal account for a patient that already has one', function () {
    [$tenant, $user] = portalTenantWithMember();
    $token = portalStaffToken($tenant, $user);

    $patient = Patient::create(['tenant_id' => $tenant->id, 'reference' => 'PAT-000002']);
    $email1 = 'patient2-'.Str::lower(Str::random(8)).'@example.com';
    $email2 = 'patient2b-'.Str::lower(Str::random(8)).'@example.com';

    $this->withToken($token)->postJson("/api/v1/patients/{$patient->id}/portal-account", [
        'email' => $email1,
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $secondResp = $this->withToken($token)->postJson("/api/v1/patients/{$patient->id}/portal-account", [
        'email' => $email2,
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $secondResp->assertUnprocessable();
});

test('email must be unique per tenant', function () {
    [$tenant, $user] = portalTenantWithMember();
    $token = portalStaffToken($tenant, $user);

    $patient1 = Patient::create(['tenant_id' => $tenant->id, 'reference' => 'PAT-000003']);
    $patient2 = Patient::create(['tenant_id' => $tenant->id, 'reference' => 'PAT-000004']);
    $sharedEmail = 'same-email-'.Str::lower(Str::random(8)).'@example.com';

    $this->withToken($token)->postJson("/api/v1/patients/{$patient1->id}/portal-account", [
        'email' => $sharedEmail,
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $duplicateResp = $this->withToken($token)->postJson("/api/v1/patients/{$patient2->id}/portal-account", [
        'email' => $sharedEmail,
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $duplicateResp->assertUnprocessable();
});

test('patient can change password with current password verification and log in with new password', function () {
    [$tenant, $user] = portalTenantWithMember();
    $token = portalStaffToken($tenant, $user);

    $patient = Patient::create(['tenant_id' => $tenant->id, 'reference' => 'PAT-000005']);
    $email = 'patient5-'.Str::lower(Str::random(8)).'@example.com';

    $this->withToken($token)->postJson("/api/v1/patients/{$patient->id}/portal-account", [
        'email' => $email,
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    // Initial Login
    $loginResp = $this->postJson('/api/v1/patient-portal/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $email,
        'password' => 'password',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $patientToken = $loginResp->json('token');

    // Wrong current password fails
    $wrongResp = $this->withToken($patientToken)->patchJson('/api/v1/patient-portal/me/password', [
        'current_password' => 'wrong-pass',
        'new_password' => 'NewSecretPassword123!',
        'new_password_confirmation' => 'NewSecretPassword123!',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $wrongResp->assertUnprocessable();

    // Correct change password succeeds
    $changeResp = $this->withToken($patientToken)->patchJson('/api/v1/patient-portal/me/password', [
        'current_password' => 'password',
        'new_password' => 'NewSecretPassword123!',
        'new_password_confirmation' => 'NewSecretPassword123!',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $changeResp->assertOk();

    // Login with old password now fails
    $oldLoginResp = $this->postJson('/api/v1/patient-portal/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $email,
        'password' => 'password',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $oldLoginResp->assertUnauthorized();

    // Login with new password succeeds
    $newLoginResp = $this->postJson('/api/v1/patient-portal/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $email,
        'password' => 'NewSecretPassword123!',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $newLoginResp->assertOk();
});

test('patient portal account is blocked from staff endpoints', function () {
    [$tenant, $user] = portalTenantWithMember();
    $token = portalStaffToken($tenant, $user);

    $patient = Patient::create(['tenant_id' => $tenant->id, 'reference' => 'PAT-000006']);
    $email = 'patient6-'.Str::lower(Str::random(8)).'@example.com';

    $this->withToken($token)->postJson("/api/v1/patients/{$patient->id}/portal-account", [
        'email' => $email,
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $loginResp = $this->postJson('/api/v1/patient-portal/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $email,
        'password' => 'password',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $patientToken = $loginResp->json('token');

    // Access to staff-only endpoint is 403 Forbidden
    $blockedResp = $this->withToken($patientToken)->getJson('/api/v1/patients');
    $blockedResp->assertForbidden();
});

test('patients table schema remains strictly pseudonymized with no PII columns', function () {
    $columns = Schema::getColumnListing('patients');
    expect($columns)->toContain('id', 'tenant_id', 'reference', 'created_at', 'updated_at', 'deleted_at');
    expect($columns)->not->toContain('name', 'first_name', 'last_name', 'email', 'phone', 'secu_number');
});
