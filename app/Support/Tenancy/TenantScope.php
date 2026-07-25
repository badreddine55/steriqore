<?php

namespace App\Support\Tenancy;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Fails closed: with no current tenant bound (a forgotten middleware, a
 * console context that never called Tenant::current()), this returns zero
 * rows rather than silently falling back to an unscoped, cross-tenant query.
 * Legitimate system contexts opt out explicitly via
 * Model::withoutGlobalScope(self::class), the same way Postgres RLS
 * defaults to denying when app.tenant_id isn't set (see docs/erd.md §4).
 *
 * @implements Scope<Model>
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = Tenant::current();

        $column = $model->getTable().'.tenant_id';

        if ($tenant === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($column, $tenant->getKey());
    }
}
