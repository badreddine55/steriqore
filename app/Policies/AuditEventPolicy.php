<?php

namespace App\Policies;

use App\Domain\Compliance\Models\AuditEvent;
use App\Models\User;

class AuditEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('audit.view');
    }

    public function view(User $user, AuditEvent $auditEvent): bool
    {
        // AuditEvent is BelongsToTenant-scoped, so a foreign-tenant event
        // can never be fetched to reach this check in the first place.
        return $user->can('audit.view');
    }
}
