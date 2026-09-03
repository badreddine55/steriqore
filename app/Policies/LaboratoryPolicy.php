<?php

namespace App\Policies;

use App\Domain\Prosthetics\Models\Laboratory;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;

class LaboratoryPolicy
{
    /**
     * Determine whether the user can view any laboratories.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['owner', 'admin', 'stock_manager', 'practitioner', 'releaser', 'viewer', 'reception'])
            || $user->can('prosthetics.view');
    }

    /**
     * Determine whether the user can view the laboratory.
     */
    public function view(User $user, Laboratory $laboratory): bool
    {
        $tenantId = Tenant::current()?->id;

        if ($tenantId !== null && $laboratory->tenant_id !== $tenantId) {
            return false;
        }

        return $user->can('prosthetics.view');
    }

    /**
     * Determine whether the user can create laboratories.
     */
    public function create(User $user): bool
    {
        return $user->can('prosthetics.create');
    }

    /**
     * Determine whether the user can update the laboratory.
     */
    public function update(User $user, Laboratory $laboratory): bool
    {
        $tenantId = Tenant::current()?->id;

        if ($tenantId !== null && $laboratory->tenant_id !== $tenantId) {
            return false;
        }

        return $user->can('prosthetics.create');
    }

    /**
     * Determine whether the user can delete the laboratory.
     */
    public function delete(User $user, Laboratory $laboratory): bool
    {
        $tenantId = Tenant::current()?->id;

        if ($tenantId !== null && $laboratory->tenant_id !== $tenantId) {
            return false;
        }

        return $user->hasRole(['owner', 'admin']);
    }
}
