<?php

namespace App\Http\Middleware\Web;

use App\Domain\Tenancy\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The web equivalent of Api\SetCurrentTenant. A browser session has no
 * per-token tenant_id to read (unlike a mobile Sanctum token, which is
 * minted for exactly one practice at login) — a session authenticates a
 * user, who may hold active membership in more than one tenant, so the
 * tenant is resolved from the session instead: whichever tenant_id was
 * last selected (see Web\SwitchTenantController), defaulting to the
 * user's first active membership the first time. The tenant still never
 * comes from a client-supplied route/query parameter (CLAUDE.md's tenancy
 * invariant) — the session value itself is only ever written server-side,
 * validated against the user's own active memberships on every request.
 */
class SetCurrentTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null, 401);

        $activeTenantIds = $user->tenantUsers()->where('status', 'active')->pluck('tenant_id');

        abort_if($activeTenantIds->isEmpty(), 403, 'This account has no active practice access.');

        $tenantId = $request->session()->get('current_tenant_id');

        if (! is_string($tenantId) || ! $activeTenantIds->contains($tenantId)) {
            $tenantId = (string) $activeTenantIds->first();
            $request->session()->put('current_tenant_id', $tenantId);
        }

        $tenant = Tenant::find($tenantId);

        abort_if($tenant === null, 403);

        return TenantContext::run($tenant, fn () => $next($request));
    }
}
