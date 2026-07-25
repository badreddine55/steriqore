<?php

use App\Domain\Compliance\Models\AuditEvent;
use App\Domain\Identity\Actions\SeedTenantRolesAction;
use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantScope;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

function auditTenantWithMember(TenantRole $role = TenantRole::Owner, string $password = 'correct-password'): array
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

test('a successful login writes an audit event with the actor and tenant', function () {
    [$tenant, $user] = auditTenantWithMember();

    $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $user->email,
        'password' => 'correct-password',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertOk();

    $event = AuditEvent::withoutGlobalScope(TenantScope::class)
        ->where('action', 'auth.login_succeeded')
        ->where('tenant_id', $tenant->id)
        ->first();

    expect($event)->not->toBeNull();
    expect($event->actor_id)->toBe($user->id);
    expect($event->actor_label_snapshot)->toContain($user->email);
});

test('a denied login (wrong password) writes an audit event with the reason', function () {
    [$tenant, $user] = auditTenantWithMember();

    $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $user->email,
        'password' => 'wrong-password',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(401);

    $event = AuditEvent::withoutGlobalScope(TenantScope::class)
        ->where('action', 'auth.login_denied')
        ->where('tenant_id', $tenant->id)
        ->first();

    expect($event)->not->toBeNull();
    expect($event->reason)->toBe('INVALID_CREDENTIALS');
});

test('the actor label snapshot survives a later rename — history keeps the identity as it was', function () {
    [$tenant, $user] = auditTenantWithMember();

    $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $user->email,
        'password' => 'correct-password',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $event = AuditEvent::withoutGlobalScope(TenantScope::class)
        ->where('action', 'auth.login_succeeded')->firstOrFail();
    $originalSnapshot = $event->actor_label_snapshot;

    $user->update(['name' => 'Someone Completely Different']);

    expect($event->fresh()->actor_label_snapshot)->toBe($originalSnapshot);
});

test('an owner can list this practice audit events and a viewer cannot', function () {
    [$tenant, $owner] = auditTenantWithMember(TenantRole::Owner);
    $viewer = User::create(['name' => 'V', 'email' => 'v-'.Str::lower(Str::random(8)).'@example.com', 'password' => Hash::make('pw')]);
    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $viewer->id, 'status' => 'active']);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);
    $viewer->assignRole(TenantRole::Viewer->value);
    $registrar->setPermissionsTeamId(null);

    $ownerToken = $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug, 'email' => $owner->email, 'password' => 'correct-password',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('token');

    $viewerToken = $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug, 'email' => $viewer->email, 'password' => 'pw',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('token');

    $this->withToken($ownerToken)->getJson('/api/v1/audit-events')->assertOk();
    $this->withToken($viewerToken)->getJson('/api/v1/audit-events')->assertStatus(403);
});

test('a practice never sees another practice audit events', function () {
    [$tenantA, $ownerA] = auditTenantWithMember();
    [$tenantB] = auditTenantWithMember();

    $tokenA = $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenantA->slug, 'email' => $ownerA->email, 'password' => 'correct-password',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('token');

    $response = $this->withToken($tokenA)->getJson('/api/v1/audit-events');

    $response->assertOk();
    $tenantIds = collect($response->json('data'))->pluck('tenant_id')->filter();
    expect($tenantIds->contains($tenantB->id))->toBeFalse();
});

test('audit_events is append-only at the database level: update and delete are refused even for the admin role', function () {
    [$tenant, $user] = auditTenantWithMember();

    $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $user->email,
        'password' => 'correct-password',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $event = AuditEvent::withoutGlobalScope(TenantScope::class)->first();

    expect($event)->not->toBeNull();

    // Each attempt runs in its own transaction (a Postgres savepoint nested
    // inside RefreshDatabase's outer per-test transaction): once a
    // statement fails, Postgres poisons the rest of that transaction
    // ("current transaction is aborted") until it's rolled back, which
    // would otherwise break the next assertion in this same test.
    expect(fn () => DB::transaction(
        fn () => DB::table('audit_events')->where('id', $event->id)->update(['reason' => 'tampered'])
    ))->toThrow(QueryException::class);

    expect(fn () => DB::transaction(
        fn () => DB::table('audit_events')->where('id', $event->id)->delete()
    ))->toThrow(QueryException::class);

    expect(AuditEvent::withoutGlobalScope(TenantScope::class)->find($event->id)->reason)
        ->not->toBe('tampered');
});
