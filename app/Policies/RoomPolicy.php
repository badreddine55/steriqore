<?php

namespace App\Policies;

use App\Domain\Tenancy\Models\Room;
use App\Models\User;

/**
 * Rooms are only ever reached nested under a site's own detail page — no
 * separate "rooms.view" permission exists, viewing one is implied by
 * being able to view its site (sites.view). Managing is the same
 * admin/owner-only sites.manage permission as Site itself.
 */
class RoomPolicy
{
    public function create(User $user): bool
    {
        return $user->can('sites.manage');
    }

    public function update(User $user, Room $room): bool
    {
        return $user->can('sites.manage');
    }

    public function delete(User $user, Room $room): bool
    {
        return $user->can('sites.manage');
    }
}
