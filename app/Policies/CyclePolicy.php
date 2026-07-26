<?php

namespace App\Policies;

use App\Domain\Sterilization\Models\Cycle;
use App\Models\User;

/**
 * No delete() method — cycles are an evidence table (CLAUDE.md invariant
 * #7) and there is deliberately no delete route to authorize.
 */
class CyclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cycles.view');
    }

    public function view(User $user, Cycle $cycle): bool
    {
        return $user->can('cycles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('cycles.manage');
    }

    public function update(User $user, Cycle $cycle): bool
    {
        return $user->can('cycles.manage');
    }
}
