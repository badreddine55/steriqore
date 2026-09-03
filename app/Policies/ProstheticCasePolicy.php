<?php

namespace App\Policies;

use App\Domain\Prosthetics\Models\ProstheticCase;
use App\Models\User;

class ProstheticCasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('prosthetics.view');
    }

    public function view(User $user, ProstheticCase $case): bool
    {
        return $user->can('prosthetics.view');
    }

    public function create(User $user): bool
    {
        return $user->can('prosthetics.create');
    }

    public function update(User $user, ProstheticCase $case): bool
    {
        return $user->can('prosthetics.create');
    }

    public function updateStatus(User $user, ProstheticCase $case): bool
    {
        return $user->can('prosthetics.update_status');
    }

    public function updateFinancials(User $user, ProstheticCase $case): bool
    {
        return $user->can('prosthetics.update_financials');
    }

    public function delete(User $user, ProstheticCase $case): bool
    {
        return $user->hasRole(['owner', 'admin']);
    }
}
