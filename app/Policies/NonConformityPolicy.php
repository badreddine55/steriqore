<?php

namespace App\Policies;

use App\Domain\Compliance\Models\NonConformity;
use App\Models\User;

class NonConformityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('non_conformities.view');
    }

    public function view(User $user, NonConformity $nonConformity): bool
    {
        return $user->can('non_conformities.view');
    }

    public function create(User $user): bool
    {
        return $user->can('non_conformities.manage');
    }

    public function resolve(User $user, NonConformity $nonConformity): bool
    {
        return $user->can('non_conformities.manage');
    }
}
