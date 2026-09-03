<?php

namespace App\Policies;

use App\Domain\Traceability\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('patients.view');
    }

    public function view(User $user, Patient $patient): bool
    {
        return $user->can('patients.view');
    }

    public function create(User $user): bool
    {
        return $user->can('patients.manage');
    }

    public function delete(User $user, Patient $patient): bool
    {
        return $user->can('patients.manage');
    }

    /**
     * Determine whether the user can provision a portal account for the patient.
     */
    public function createPortalAccount(User $user, Patient $patient): bool
    {
        $tenantId = \App\Domain\Tenancy\Models\Tenant::current()?->id;

        if (! $tenantId || $patient->tenant_id !== $tenantId) {
            return false;
        }

        return $user->hasRole(['owner', 'admin']);
    }
}
