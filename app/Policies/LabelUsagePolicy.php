<?php

namespace App\Policies;

use App\Domain\Traceability\Models\LabelUsage;
use App\Models\User;

/**
 * No update()/delete() — label_usages is an evidence table (CLAUDE.md
 * invariant #7) with no route to authorize for either.
 */
class LabelUsagePolicy
{
    public function view(User $user, LabelUsage $usage): bool
    {
        return $user->can('usages.view');
    }

    public function create(User $user): bool
    {
        return $user->can('usages.manage');
    }
}
