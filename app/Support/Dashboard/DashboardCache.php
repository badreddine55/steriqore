<?php

namespace App\Support\Dashboard;

use App\Domain\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * UI-30's dashboard rolls up counts across Sterilization/Inventory/
 * Compliance in one request — cached per tenant with a short TTL as the
 * safety net, and explicitly forgotten by every write that could change
 * what it shows (cycle release, non-conformity raise/resolve, stock
 * movement, alert detect/resolve) so the attention queue is never stale on
 * purpose. This is the first cache-invalidation-on-domain-event pattern in
 * the app; kept as a tiny standalone helper (not tied to any one domain)
 * so Sterilization/Inventory/Compliance Actions can depend on it without a
 * dependency back into the Reporting domain that owns the dashboard itself.
 */
class DashboardCache
{
    private const int TTL_SECONDS = 60;

    /**
     * @template TValue of array<string, mixed>
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public static function remember(Tenant $tenant, Closure $callback): array
    {
        return Cache::remember(self::key($tenant), self::TTL_SECONDS, $callback);
    }

    public static function forget(Tenant $tenant): void
    {
        Cache::forget(self::key($tenant));
    }

    private static function key(Tenant $tenant): string
    {
        return "dashboard:{$tenant->id}";
    }
}
