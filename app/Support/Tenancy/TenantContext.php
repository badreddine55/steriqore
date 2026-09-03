<?php

namespace App\Support\Tenancy;

use App\Domain\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * The exact binding App\Http\Middleware\Api\SetCurrentTenant does for a
 * real request — spatie/multitenancy's "current tenant", the Postgres RLS
 * session variable app.tenant_id, and spatie/permission's team id —
 * extracted so it can also run outside a request, for the two public
 * endpoints that must create tenant-scoped rows (roles, in particular —
 * RLS-protected since S1-ANA-03) before any "current tenant" exists to
 * scope by: tenant registration and invitation acceptance.
 *
 * `set_config(..., true)` only lasts for the current transaction — hence
 * wrapping the callback in one here, same as the middleware.
 */
class TenantContext
{
    public static function run(Tenant $tenant, Closure $callback): mixed
    {
        $tenant->makeCurrent();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);
        $registrar->forgetCachedPermissions();

        try {
            return DB::transaction(function () use ($tenant, $callback) {
                DB::select("select set_config('app.tenant_id', ?, true)", [$tenant->id]);

                return $callback();
            });
        } finally {
            Tenant::forgetCurrent();
            $registrar->setPermissionsTeamId(null);
            $registrar->forgetCachedPermissions();
        }
    }
}
