<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Tenancy\Models\StorageLocation;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Never a hard delete. Unlike Site/Room, a storage location is a leaf —
 * nothing cascades. Archiving doesn't move or zero its stock:
 * stock_levels/stock_movements keep resolving this location by identity
 * (invariant #6's reasoning), it's just no longer offered as a destination
 * for new stock. The confirmation dialog shows current units on hand —
 * SiteController::show() already computes that per location for the tree
 * view, so no separate preview query is needed here.
 */
class ArchiveStorageLocationAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(StorageLocation $location, User $actor, string $reason): void
    {
        $location->delete();

        $this->recordAuditEvent->execute(
            tenant: $location->tenant,
            actor: $actor,
            action: 'storage_location.archived',
            subjectType: StorageLocation::class,
            subjectId: $location->id,
            reason: $reason,
            request: $this->request,
        );
    }
}
