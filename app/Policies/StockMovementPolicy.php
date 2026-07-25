<?php

namespace App\Policies;

use App\Models\User;

class StockMovementPolicy
{
    public function manage(User $user): bool
    {
        return $user->can('inventory.manage');
    }
}
