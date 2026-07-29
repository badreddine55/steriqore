<?php

use App\Domain\Identity\Actions\SeedTenantRolesAction;
use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

function apiDocsTenantWithMember(): array
{
    $tenant = Tenant::create(['name' => 'Cabinet Docs', 'slug' => 'cabinet-'.Str::lower(Str::random(10))]);

    $user = User::create([
        'name' => 'Docs User',
        'email' => 'docs-'.Str::lower(Str::random(10)).'@example.com',
        'password' => Hash::make('correct-password'),
    ]);

    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);

    app(SeedTenantRolesAction::class)->execute($tenant);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);
    $user->assignRole(TenantRole::Owner->value);
    $registrar->setPermissionsTeamId(null);

    return [$tenant, $user];
}

test('authenticated users can open the Scramble docs UI without resolving tenant permissions', function () {
    [$tenant, $user] = apiDocsTenantWithMember();

    // RestrictedDocsAccess only opens freely in local.
    $this->app->detectEnvironment(fn (): string => 'local');

    $this->actingAs($user)
        ->withSession(['current_tenant_id' => $tenant->id])
        ->get('/docs/api')
        ->assertOk();
});

test('HandleInertiaRequests skips tenant permission sharing on scramble routes', function () {
    [$tenant, $user] = apiDocsTenantWithMember();

    $request = Request::create('/docs/api', 'GET');
    $request->setLaravelSession($this->app['session']->driver());
    $request->session()->put('current_tenant_id', $tenant->id);
    $request->setUserResolver(fn () => $user);
    $request->setRouteResolver(function () {
        $route = new Route(['GET'], '/docs/api', fn () => response('ok'));
        $route->name('scramble.docs.ui');

        return $route;
    });

    $shared = app(HandleInertiaRequests::class)->share($request);

    expect($shared['auth']['user'])->toBeNull()
        ->and($shared['auth']['permissions'])->toBe([])
        ->and($shared['tenant'])->toBeNull()
        ->and($shared['tenants'])->toBe([]);
});
