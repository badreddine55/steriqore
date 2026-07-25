<?php

use App\Domain\Tenancy\Models\Site;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * REC-01 / R-06: a user of practice A must never be able to read practice
 * B's data. Two independent layers are exercised here:
 *   - the ORM's TenantScope global scope (App\Support\Tenancy\TenantScope)
 *   - PostgreSQL Row-Level Security, exercised as the restricted,
 *     non-superuser role the running application actually connects as
 *     (App\Http\Middleware\Api\SetCurrentTenant, docker/postgres/init/01-create-app-role.sql)
 *
 * The RLS tests use `SET LOCAL ROLE steriqore_app` rather than a second
 * physical connection to that role: RefreshDatabase's per-test transaction
 * on the default (admin) connection is rolled back, never committed, so a
 * genuinely separate connection could never see the fixture rows anyway.
 * `SET LOCAL ROLE` changes the effective role — and therefore RLS
 * enforcement — for the rest of the current transaction, on the same
 * session, which is the standard way to test RLS as another role without
 * a second connection.
 */
function makeTenantWithSite(string $siteName): Tenant
{
    $tenant = Tenant::create(['name' => 'Practice '.$siteName, 'slug' => 'practice-'.Str::lower(Str::random(10))]);
    $tenant->sites()->create(['name' => $siteName]);

    return $tenant;
}

test('the ORM global scope only returns the current tenant sites', function () {
    $tenantA = makeTenantWithSite('Cabinet A');
    $tenantB = makeTenantWithSite('Cabinet B');

    $tenantA->makeCurrent();
    expect(Site::pluck('name')->all())->toBe(['Cabinet A']);
    Tenant::forgetCurrent();

    $tenantB->makeCurrent();
    expect(Site::pluck('name')->all())->toBe(['Cabinet B']);
    Tenant::forgetCurrent();
});

test('the ORM global scope fails closed with no current tenant bound', function () {
    makeTenantWithSite('Cabinet A');
    Tenant::forgetCurrent();

    expect(Site::count())->toBe(0);
});

test('creating a site auto-fills tenant_id from the current tenant', function () {
    $tenant = makeTenantWithSite('Cabinet A');
    $tenant->makeCurrent();

    $site = Site::create(['name' => 'Cabinet A - Annexe']);

    expect($site->tenant_id)->toBe($tenant->id);

    Tenant::forgetCurrent();
});

test('Row-Level Security enforces isolation for the restricted role, independent of the ORM', function () {
    $tenantA = makeTenantWithSite('Cabinet RLS A');
    $tenantB = makeTenantWithSite('Cabinet RLS B');
    Tenant::forgetCurrent();

    $namesForTenant = function (string $tenantId) {
        DB::statement('SET LOCAL ROLE steriqore_app');
        DB::select("select set_config('app.tenant_id', ?, true)", [$tenantId]);

        return DB::table('sites')->pluck('name')->all();
    };

    // Each call runs in its own transaction (RefreshDatabase's outer test
    // transaction plus a nested one here) so `SET LOCAL ROLE` from the first
    // call doesn't leak into the second.
    expect(DB::transaction(fn () => $namesForTenant($tenantA->id)))->toBe(['Cabinet RLS A']);
    expect(DB::transaction(fn () => $namesForTenant($tenantB->id)))->toBe(['Cabinet RLS B']);
});

test('Row-Level Security denies every row when app.tenant_id is not set, even for the restricted role', function () {
    makeTenantWithSite('Cabinet RLS C');
    Tenant::forgetCurrent();

    $count = DB::transaction(function () {
        DB::statement('SET LOCAL ROLE steriqore_app');

        // Deliberately not calling set_config — app.tenant_id is unset here.
        return DB::table('sites')->count();
    });

    expect($count)->toBe(0);
});

test('Row-Level Security rejects an insert whose tenant_id does not match the session tenant', function () {
    $tenantA = makeTenantWithSite('Cabinet RLS D');
    $tenantB = makeTenantWithSite('Cabinet RLS E');
    Tenant::forgetCurrent();

    expect(function () use ($tenantA, $tenantB) {
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
    })->toThrow(QueryException::class);
});
