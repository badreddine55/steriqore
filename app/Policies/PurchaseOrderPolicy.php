<?php

namespace App\Policies;

use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('purchasing.view');
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchasing.view');
    }

    public function create(User $user): bool
    {
        return $user->can('purchasing.manage');
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchasing.manage');
    }
}
