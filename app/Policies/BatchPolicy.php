<?php

namespace App\Policies;

use App\Domain\Inventory\Models\Batch;
use App\Models\User;

class BatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.view');
    }

    public function view(User $user, Batch $batch): bool
    {
        return $user->can('inventory.view');
    }
}
