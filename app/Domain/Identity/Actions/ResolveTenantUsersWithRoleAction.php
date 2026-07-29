<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Extracted from SendDigests (UI-28), which had this as a private method —
 * now shared with RequestDataExportAction (UI-29), which needs the exact
 * same "who currently holds this role" resolution to notify practice
 * owners. Raw query rather than spatie/permission's own role-users
 * lookup since that's scoped by the CURRENT permission team id, and
 * queued/scheduled callers often don't have one bound.
 */
class ResolveTenantUsersWithRoleAction
{
    /**
     * @return Collection<int, User>
     */
    public function execute(Tenant $tenant, string $role): Collection
    {
        $userIds = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.tenant_id', $tenant->id)
            ->where('roles.name', $role)
            ->pluck('model_has_roles.model_uuid');

        return User::whereIn('id', $userIds)->get();
    }
}
