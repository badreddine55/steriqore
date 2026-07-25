<?php

namespace App\Support\Concerns;

use App\Domain\Tenancy\Models\Tenant;
use App\Support\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applies to every tenant-owned model: adds the global scope that filters
 * reads to the current tenant (fails closed, see TenantScope) and
 * auto-fills tenant_id on create so callers never have to pass it — the
 * tenant never comes from client input (CLAUDE.md's tenancy invariant).
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('tenant_id') === null && $tenant = Tenant::current()) {
                $model->setAttribute('tenant_id', $tenant->getKey());
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
