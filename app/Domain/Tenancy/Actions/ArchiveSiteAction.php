<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Equipment\Models\Device;
use App\Domain\Tenancy\Models\Room;
use App\Domain\Tenancy\Models\Site;
use App\Domain\Tenancy\Models\StorageLocation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Never a hard delete (CLAUDE.md: past evidence — devices, movements,
 * batches — keeps resolving an archived site/room/location by identity,
 * invariant #6's same reasoning applied to the tenancy hierarchy). Archiving
 * a site cascades to its rooms and storage locations so an archived site
 * never leaves an active room/location still pickable underneath it.
 */
class ArchiveSiteAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(Site $site, User $actor, string $reason): void
    {
        DB::transaction(function () use ($site, $actor, $reason) {
            $rooms = $site->rooms()->get();
            $locations = $site->storageLocations()->get();

            foreach ($locations as $location) {
                $location->delete();

                $this->recordAuditEvent->execute(
                    tenant: $site->tenant,
                    actor: $actor,
                    action: 'storage_location.archived',
                    subjectType: StorageLocation::class,
                    subjectId: $location->id,
                    reason: "Cascaded from archiving site \"{$site->name}\": {$reason}",
                    request: $this->request,
                );
            }

            foreach ($rooms as $room) {
                $room->delete();

                $this->recordAuditEvent->execute(
                    tenant: $site->tenant,
                    actor: $actor,
                    action: 'room.archived',
                    subjectType: Room::class,
                    subjectId: $room->id,
                    reason: "Cascaded from archiving site \"{$site->name}\": {$reason}",
                    request: $this->request,
                );
            }

            $site->delete();

            $this->recordAuditEvent->execute(
                tenant: $site->tenant,
                actor: $actor,
                action: 'site.archived',
                subjectType: Site::class,
                subjectId: $site->id,
                reason: $reason,
                request: $this->request,
            );
        });
    }

    /**
     * Read-only — the count a confirmation dialog shows BEFORE archiving,
     * same pattern as RaiseNonConformityAction::previewBlastRadius(). Must
     * stay in sync with execute()'s cascade above if either changes.
     *
     * @return array{rooms: int, storage_locations: int, devices: int}
     */
    public function previewImpact(Site $site): array
    {
        return [
            'rooms' => $site->rooms()->count(),
            'storage_locations' => $site->storageLocations()->count(),
            'devices' => Device::where('site_id', $site->id)->count(),
        ];
    }
}
