<?php

namespace App\Policies;

use App\Models\User;

class AlertPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('alerts.view');
    }

    public function manage(User $user): bool
    {
        return $user->can('alerts.manage');
    }
}
