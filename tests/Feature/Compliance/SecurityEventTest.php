<?php

use App\Domain\Compliance\Enums\SecurityEventType;
use App\Domain\Compliance\Models\AuditEvent;
use App\Domain\Compliance\Models\SecurityEvent;
use App\Domain\Equipment\Models\Device;
use App\Domain\Identity\Actions\SeedTenantRolesAction;
use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Models\Site;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantScope;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * security_events is the operator-only channel this feature adds — see
 * database/migrations/2026_08_09_000001_create_security_events_table.php
 * and app/Support/Security/SecurityEventRenderer.php for the full
 * reasoning. Never exercised through the app's normal connection: every
 * assertion here reads the table directly (SecurityEvent itself always
 * talks to pgsql_admin), the same way the app can never read it either.
 */
function securityTenantWithMember(TenantRole $role = TenantRole::Owner, string $password = 'correct-password'): array
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

function securityLoginToken(Tenant $tenant, User $user, string $password = 'correct-password'): string
{
    return test()->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $user->email,
        'password' => $password,
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('token');
}

test('an authorization failure writes a security event, not an audit event', function () {
    [$tenant, $viewer] = securityTenantWithMember(TenantRole::Viewer);
    $token = securityLoginToken($tenant, $viewer);

    $auditCountBefore = AuditEvent::withoutGlobalScope(TenantScope::class)->count();

    $this->withToken($token)->postJson('/api/v1/invitations', [
        'email' => 'someone-'.Str::lower(Str::random(8)).'@example.com',
        'role' => 'viewer',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(403);

    $event = SecurityEvent::where('type', SecurityEventType::AuthorizationFailure)
        ->where('actor_id', $viewer->id)
        ->latest('occurred_at')
        ->first();

    expect($event)->not->toBeNull();
    expect($event->tenant_claimed_id)->toBe($tenant->id);
    expect($event->actor_label_snapshot)->toContain($viewer->email);
    expect($event->route_name)->toBe('api.v1.invitations.store');
    expect($event->method)->toBe('POST');
    expect($event->request_id)->not->toBeNull();

    // The 403 is a routine, in-tenant permission denial, not a
    // cross-tenant probe — it belongs on the operator channel, and
    // nowhere on the practice's own business audit trail.
    expect(AuditEvent::withoutGlobalScope(TenantScope::class)->count())->toBe($auditCountBefore);
});

test('a genuine cross-tenant resolution attempt writes a security event with both tenants', function () {
    [$tenantA, $ownerA] = securityTenantWithMember(TenantRole::Owner);
    $tokenA = securityLoginToken($tenantA, $ownerA);

    // Practice B's device has to be genuinely committed, not just sitting
    // inside this test's own uncommitted RefreshDatabase transaction: the
    // renderer confirms a real cross-tenant match through the pgsql_admin
    // connection — a physically separate DB session that can never see an
    // uncommitted row from the default connection's transaction (the same
    // limitation TenantIsolationTest and DataExportRequestTest already
    // document). Created via that same connection so both sessions agree
    // it exists, and torn down explicitly since it won't roll back the
    // way every other fixture here does.
    $tenantB = Tenant::on('pgsql_admin')->create(['name' => 'Cabinet B', 'slug' => 'cabinet-'.Str::lower(Str::random(10))]);
    $siteB = Site::on('pgsql_admin')->create(['tenant_id' => $tenantB->id, 'name' => 'Practice B Site']);
    $deviceB = Device::on('pgsql_admin')->create([
        'tenant_id' => $tenantB->id,
        'site_id' => $siteB->id,
        'name' => 'Autoclave B',
        'serial_number' => 'SN-'.Str::random(8),
        'kind' => 'autoclave',
        'status' => 'active',
    ]);

    try {
        $response = $this->withToken($tokenA)->getJson("/api/v1/devices/{$deviceB->id}");
        $response->assertStatus(404);

        $event = SecurityEvent::where('type', SecurityEventType::CrossTenantResolutionAttempt)
            ->where('subject_id', $deviceB->id)
            ->first();

        expect($event)->not->toBeNull();
        expect($event->tenant_claimed_id)->toBe($tenantA->id);
        expect($event->tenant_actual_id)->toBe($tenantB->id);
        expect($event->actor_id)->toBe($ownerA->id);
        expect($event->subject_type)->toBe(Device::class);
    } finally {
        DB::connection('pgsql_admin')->table('tenants')->where('id', $tenantB->id)->delete();
    }
});

test('a wholly made-up id produces no security event — 404 noise is not a probe', function () {
    [$tenant, $owner] = securityTenantWithMember(TenantRole::Owner);
    $token = securityLoginToken($tenant, $owner);

    $madeUpId = (string) Str::uuid();

    $this->withToken($token)->getJson("/api/v1/devices/{$madeUpId}")->assertStatus(404);

    expect(
        SecurityEvent::where('type', SecurityEventType::CrossTenantResolutionAttempt)
            ->where('subject_id', $madeUpId)
            ->exists()
    )->toBeFalse();
});

test('the restricted app role can neither read nor write security_events — only pgsql_admin can', function () {
    // The test suite's default connection runs as the elevated migrations
    // role (phpunit.xml), same reason TenantIsolationTest exercises RLS via
    // `SET LOCAL ROLE steriqore_app` rather than the default connection —
    // see that file's own docblock. security_events has zero permissive
    // policies at all (unlike every tenant table, which at least allows
    // its own tenant to SELECT), so both directions must fail even with a
    // tenant bound.
    [$tenant] = securityTenantWithMember(TenantRole::Owner);

    // RLS with no policy at all means a default-deny for every command,
    // but SELECT and write commands fail differently: a SELECT with no
    // matching policy simply returns zero rows (Postgres filters silently,
    // no error) while an INSERT/UPDATE/DELETE with no WITH CHECK policy is
    // an outright QueryException — verified below.
    $count = DB::transaction(function () use ($tenant) {
        DB::statement('SET LOCAL ROLE steriqore_app');
        DB::select("select set_config('app.tenant_id', ?, true)", [$tenant->id]);

        return DB::table('security_events')->count();
    });

    expect($count)->toBe(0);

    expect(function () use ($tenant) {
        DB::transaction(function () use ($tenant) {
            DB::statement('SET LOCAL ROLE steriqore_app');
            DB::select("select set_config('app.tenant_id', ?, true)", [$tenant->id]);

            DB::table('security_events')->insert([
                'id' => (string) Str::uuid7(),
                'type' => 'authorization_failure',
                'method' => 'GET',
                'path' => '/x',
                'request_id' => (string) Str::uuid7(),
                'occurred_at' => now(),
            ]);
        });
    })->toThrow(QueryException::class);
});

test('security_events is append-only at the database level, even through the admin connection', function () {
    [$tenantV, $viewer] = securityTenantWithMember(TenantRole::Viewer);
    $tokenV = securityLoginToken($tenantV, $viewer);
    $this->withToken($tokenV)->postJson('/api/v1/invitations', [
        'email' => 'someone-'.Str::lower(Str::random(8)).'@example.com',
        'role' => 'viewer',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(403);

    $event = SecurityEvent::where('type', SecurityEventType::AuthorizationFailure)
        ->where('actor_id', $viewer->id)
        ->firstOrFail();

    expect(fn () => DB::connection('pgsql_admin')->transaction(
        fn () => DB::connection('pgsql_admin')
            ->table('security_events')->where('id', $event->id)->update(['path' => 'tampered'])
    ))->toThrow(QueryException::class);

    expect(fn () => DB::connection('pgsql_admin')->transaction(
        fn () => DB::connection('pgsql_admin')
            ->table('security_events')->where('id', $event->id)->delete()
    ))->toThrow(QueryException::class);

    expect($event->fresh()->path)->not->toBe('tampered');
});

test('a genuine RLS policy rejection writes a security event via the render pipeline', function () {
    // Same technique as TenantIsolationTest's "rejects an insert whose
    // tenant_id does not match" — a real SQLSTATE[42501] under the
    // restricted role, not a hand-typed message, fed into the exact
    // Handler::render() entrypoint a live request would hit.
    [$tenantA, $ownerA] = securityTenantWithMember(TenantRole::Owner);
    [$tenantB] = securityTenantWithMember(TenantRole::Owner);

    $caught = null;

    try {
        DB::transaction(function () use ($tenantA, $tenantB) {
            DB::statement('SET LOCAL ROLE steriqore_app');
            DB::select("select set_config('app.tenant_id', ?, true)", [$tenantA->id]);

            DB::table('sites')->insert([
                'tenant_id' => $tenantB->id, // mismatched on purpose
                'name' => 'Should be rejected',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    } catch (QueryException $e) {
        $caught = $e;
    }

    expect($caught)->not->toBeNull();

    $request = Request::create('/sites', 'POST');
    $request->setUserResolver(fn () => $ownerA);
    // RecordSecurityEventAction resolves Request via constructor injection
    // from the container, same as a real request's own lifecycle binds it
    // (Kernel::handle() does this before render() is ever reached) — a
    // manually built Request here has to be bound the same way, or the
    // action would pick up whatever request the container already held.
    app()->instance('request', $request);

    app(ExceptionHandler::class)->render($request, $caught);

    $event = SecurityEvent::where('type', SecurityEventType::RlsPolicyRejection)
        ->latest('occurred_at')
        ->first();

    expect($event)->not->toBeNull();
    expect($event->method)->toBe('POST');
    expect($event->path)->toBe('sites');
});
