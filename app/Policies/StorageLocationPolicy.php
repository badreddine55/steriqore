<?php

namespace App\Policies;

use App\Domain\Tenancy\Models\StorageLocation;
use App\Models\User;

/**
 * Same reasoning as RoomPolicy — only reached nested under a site's detail
 * page, managed by the same admin/owner-only sites.manage permission.
 */
class StorageLocationPolicy
{
    public function create(User $user): bool
    {
        return $user->can('sites.manage');
    }

    public function update(User $user, StorageLocation $location): bool
    {
        return $user->can('sites.manage');
    }

    public function delete(User $user, StorageLocation $location): bool
    {
        return $user->can('sites.manage');
    }
}
