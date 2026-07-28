<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Tenancy\Models\Room;
use App\Domain\Tenancy\Models\StorageLocation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Never a hard delete — cascades to the room's storage locations so an
 * archived room never leaves an active location still pickable underneath
 * it, same reasoning as ArchiveSiteAction.
 */
class ArchiveRoomAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(Room $room, User $actor, string $reason): void
    {
        DB::transaction(function () use ($room, $actor, $reason) {
            $locations = $room->storageLocations()->get();

            foreach ($locations as $location) {
                $location->delete();

                $this->recordAuditEvent->execute(
                    tenant: $room->tenant,
                    actor: $actor,
                    action: 'storage_location.archived',
                    subjectType: StorageLocation::class,
                    subjectId: $location->id,
                    reason: "Cascaded from archiving room \"{$room->name}\": {$reason}",
                    request: $this->request,
                );
            }

            $room->delete();

            $this->recordAuditEvent->execute(
                tenant: $room->tenant,
                actor: $actor,
                action: 'room.archived',
                subjectType: Room::class,
                subjectId: $room->id,
                reason: $reason,
                request: $this->request,
            );
        });
    }

    /**
     * @return array{storage_locations: int}
     */
    public function previewImpact(Room $room): array
    {
        return [
            'storage_locations' => $room->storageLocations()->count(),
        ];
    }
}
