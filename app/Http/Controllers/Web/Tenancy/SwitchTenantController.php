<?php

namespace App\Http\Controllers\Web\Tenancy;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A member of more than one practice picks which one is "active" for this
 * browser session — there's no mid-request tenant switch (CLAUDE.md's
 * tenancy invariant: the tenant never comes from a client-supplied
 * parameter, only the session), so this just rewrites the session value
 * that Web\SetCurrentTenant reads on the next request, after validating
 * the target tenant is one this user actually has active access to.
 */
class SwitchTenantController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $activeTenantIds = $request->user()->tenantUsers()->where('status', 'active')->pluck('tenant_id');

        $validated = $request->validate([
            'tenant_id' => ['required', 'uuid', Rule::in($activeTenantIds)],
        ]);

        $request->session()->put('current_tenant_id', $validated['tenant_id']);
        $request->session()->forget('current_site_id');

        return redirect()->route('dashboard');
    }
}
