<?php

namespace App\Http\Controllers\Web\Tenancy;

use App\Domain\Tenancy\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Which site is "in view" for screens that filter by site — a display
 * preference stored in the session, not a security boundary (every query
 * is still scoped to the tenant by RLS/TenantScope regardless of this
 * value; a site the request doesn't actually have access to just never
 * validates, since the allowed list below is already tenant-scoped).
 */
class SwitchSiteController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $siteIds = Site::query()->pluck('id');

        $validated = $request->validate([
            'site_id' => ['nullable', 'uuid', Rule::in($siteIds)],
        ]);

        if ($validated['site_id'] ?? null) {
            $request->session()->put('current_site_id', $validated['site_id']);
        } else {
            $request->session()->forget('current_site_id');
        }

        return redirect()->back();
    }
}
