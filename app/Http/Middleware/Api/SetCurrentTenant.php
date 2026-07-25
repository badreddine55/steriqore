<?php

namespace App\Http\Middleware\Api;

use App\Domain\Identity\Models\PersonalAccessToken;
use App\Domain\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the request's tenant — read from the Sanctum token, never a
 * client-supplied parameter (CLAUDE.md's tenancy invariant) — as the
 * "current tenant" (spatie/laravel-multitenancy) and as the Postgres RLS
 * session variable app.tenant_id (App\Support\Tenancy\TenantScope /
 * database/migrations/2026_07_25_000001_add_row_level_security_to_tenant_tables.php).
 *
 * `SET LOCAL`/`set_config(..., true)` only lasts for the current
 * transaction, and Octane/FrankenPHP keeps the DB connection alive across
 * requests — so the whole request has to run inside one transaction, or the
 * setting would either vanish immediately (autocommit) or leak into the
 * next request on the same worker (a plain, non-local SET). Runs after
 * auth:sanctum, so $request->user() is already resolved.
 */
class SetCurrentTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        abort_if(! $token instanceof PersonalAccessToken, 401);

        $tenant = Tenant::find($token->tenant_id);

        abort_if($tenant === null, 401);

        $tenant->makeCurrent();

        try {
            return DB::transaction(function () use ($tenant, $next, $request) {
                DB::select("select set_config('app.tenant_id', ?, true)", [$tenant->id]);

                return $next($request);
            });
        } finally {
            Tenant::forgetCurrent();
        }
    }
}
