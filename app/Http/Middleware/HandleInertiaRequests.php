<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\Data\SiteData;
use App\Domain\Tenancy\Data\TenantData;
use App\Domain\Tenancy\Models\Site;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Tenancy\TenantScope;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Spatie\Permission\PermissionRegistrar;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * Tenant/site/permission data is resolved directly from the session
     * here rather than trusting that Web\SetCurrentTenant has already run
     * and bound Tenant::current() — this middleware is registered globally
     * on the 'web' group while Web\SetCurrentTenant is only attached to
     * specific route groups, and Laravel's middleware priority sorting
     * doesn't guarantee one runs before the other just because both are
     * present on the same request. Resolving independently here means the
     * shared props are correct regardless of that ordering; the actual
     * request handling (RLS binding for the controller's own queries)
     * still goes through Web\SetCurrentTenant as normal.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Scramble's /docs/api UI is a Blade page on the `web` middleware
        // group, so this middleware still runs — but it is not an Inertia
        // page and must not resolve tenant-scoped Spatie permissions here.
        // Those queries hit RLS-protected permission tables without
        // Web\SetCurrentTenant having bound app.tenant_id, which crashes
        // the docs UI for logged-in users.
        if ($request->routeIs('scramble.*')) {
            return [
                ...parent::share($request),
                'name' => config('app.name'),
                'auth' => [
                    'user' => null,
                    'permissions' => [],
                    'role' => null,
                ],
                'tenant' => null,
                'tenants' => [],
                'sites' => [],
                'currentSiteId' => null,
                'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            ];
        }

        $user = $request->user();

        $tenant = null;
        $tenants = [];
        $permissions = [];
        $role = null;
        $sites = [];

        if ($user !== null) {
            $tenants = TenantData::collect(
                $user->tenants()->wherePivot('status', 'active')->orderBy('name')->get()
            );

            $tenantId = $request->session()->get('current_tenant_id');
            $tenant = is_string($tenantId) ? Tenant::find($tenantId) : null;

            if ($tenant !== null) {
                app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
                $permissions = $user->getAllPermissions()->pluck('name')->values()->all();
                $role = $user->getRoleNames()->first();

                $sites = SiteData::collect(
                    Site::withoutGlobalScope(TenantScope::class)
                        ->where('tenant_id', $tenant->id)
                        ->orderBy('name')
                        ->get()
                );
            }
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                // A plain snapshot, not the live $user object: calling
                // getAllPermissions()/getRoleNames() above lazy-loads and
                // caches the roles/permissions relations onto $user, and
                // anything later in the request (a Policy check, for
                // instance) can reload them again before the response is
                // actually serialized — unsetting the relation here
                // wouldn't survive that. A plain array can't accidentally
                // pick up Spatie's internal pivot rows the same way.
                'user' => $user !== null ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                    'created_at' => $user->created_at?->toIso8601String(),
                    'updated_at' => $user->updated_at?->toIso8601String(),
                ] : null,
                'permissions' => $permissions,
                'role' => $role,
            ],
            'tenant' => $tenant !== null ? TenantData::fromModel($tenant) : null,
            'tenants' => $tenants,
            'sites' => $sites,
            'currentSiteId' => $request->session()->get('current_site_id'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
