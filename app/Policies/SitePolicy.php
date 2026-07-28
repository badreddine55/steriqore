<?php

namespace App\Policies;

use App\Domain\Tenancy\Models\Site;
use App\Models\User;

class SitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sites.view');
    }

    public function view(User $user, Site $site): bool
    {
        // Site is BelongsToTenant-scoped (TenantScope) — a foreign-tenant
        // site can never even be fetched to reach this check, but the
        // permission is still required for the ones that can.
        return $user->can('sites.view');
    }

    public function create(User $user): bool
    {
        return $user->can('sites.manage');
    }

    public function update(User $user, Site $site): bool
    {
        return $user->can('sites.manage');
    }

    public function delete(User $user, Site $site): bool
    {
        return $user->can('sites.manage');
    }
}
