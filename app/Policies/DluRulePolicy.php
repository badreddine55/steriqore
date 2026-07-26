<?php

namespace App\Policies;

use App\Domain\Labeling\Models\DluRule;
use App\Models\User;

class DluRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('labels.view');
    }

    public function create(User $user): bool
    {
        return $user->can('labels.manage');
    }

    public function update(User $user, DluRule $rule): bool
    {
        return $user->can('labels.manage');
    }

    public function delete(User $user, DluRule $rule): bool
    {
        return $user->can('labels.manage');
    }
}
