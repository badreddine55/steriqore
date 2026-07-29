<?php

namespace App\Policies;

use App\Domain\Reporting\Models\DataExportRequest;
use App\Models\User;

/**
 * An account-wide data export (every table, every file) is more sensitive
 * than any single evidence dossier — its own permission, owner/admin only
 * (see SeedTenantRolesAction). No update()/delete(): a request's outcome
 * is written only by GenerateDataExportJob.
 */
class DataExportRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('data_exports.manage');
    }

    public function view(User $user, DataExportRequest $request): bool
    {
        return $user->can('data_exports.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('data_exports.manage');
    }

    public function download(User $user, DataExportRequest $request): bool
    {
        return $user->can('data_exports.manage');
    }
}
