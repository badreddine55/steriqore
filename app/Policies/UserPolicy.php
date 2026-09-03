<?php

namespace App\Policies;

use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

class UserPolicy
{
    /**
     * Determine whether the user can list users.
     */
    public function viewAny(User $user): bool
    {
        // super_admin: can list clinic administrators across practices.
        // owner & admin: can list users strictly within their own practice.
        return $user->hasRole(['super_admin', 'owner', 'admin']);
    }

    /**
     * Determine whether the user can view a specific user.
     */
    public function view(User $user, User $target): bool
    {
        if ($user->hasRole('super_admin')) {
            // Super admin manages clinic administrators across clinics.
            // Rank-and-file staff (practitioners, assistants, etc.) are excluded.
            return $this->isClinicAdministrator($target);
        }

        if ($user->hasRole(['owner', 'admin'])) {
            // Practice owner/admin can only view users belonging to their own tenant.
            $tenantId = Tenant::current()?->id;

            return $tenantId !== null && $target->tenantUsers()->where('tenant_id', $tenantId)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create/provision users.
     */
    public function create(User $user): bool
    {
        // Only super administrators can provision new practices and practice administrators.
        // Practice owners manage staff within their own practice via invitations.
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can update a specific user.
     */
    public function update(User $user, User $target): bool
    {
        if ($user->hasRole('super_admin')) {
            // Super admin can deactivate/activate or update clinic administrators.
            return $this->isClinicAdministrator($target);
        }

        if ($user->hasRole(['owner', 'admin'])) {
            // Practice owner/admin can only update users belonging to their own tenant.
            $tenantId = Tenant::current()?->id;

            return $tenantId !== null && $target->tenantUsers()->where('tenant_id', $tenantId)->exists();
        }

        return false;
    }

    /**
     * Check if the target user is a clinic administrator (owner or admin of at least one clinic).
     */
    public function isClinicAdministrator(User $target): bool
    {
        foreach ($target->tenants as $tenant) {
            $hasAdmin = TenantContext::run($tenant, function () use ($target) {
                return $target->hasRole(['owner', 'admin']);
            });

            if ($hasAdmin) {
                return true;
            }
        }

        return false;
    }
}
