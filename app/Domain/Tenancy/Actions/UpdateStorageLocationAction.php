<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Tenancy\Models\Room;
use App\Domain\Tenancy\Models\StorageLocation;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;

class UpdateStorageLocationAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  May include 'room_id' to move the
     *                                            location to a different room of
     *                                            the SAME site (the room/location
     *                                            tree only reorganizes within a
     *                                            site — moving stock across
     *                                            practice sites is its own,
     *                                            separate operation).
     */
    public function execute(StorageLocation $location, User $actor, array $attributes): StorageLocation
    {
        if (isset($attributes['room_id'])) {
            $room = Room::findOrFail((string) $attributes['room_id']);

            if ($room->site_id !== $location->site_id) {
                throw new ApiException('STORAGE_LOCATION_ROOM_DIFFERENT_SITE', 'A storage location can only move to a room at the same site.', 409);
            }
        }

        if (
            isset($attributes['name'])
            && StorageLocation::where('site_id', $location->site_id)->where('name', $attributes['name'])->where('id', '!=', $location->id)->exists()
        ) {
            throw new ApiException('STORAGE_LOCATION_NAME_TAKEN', 'A storage location with this name already exists at this site.', 409);
        }

        $oldValues = $location->only(array_keys($attributes));
        $location->update($attributes);

        $this->recordAuditEvent->execute(
            tenant: $location->tenant,
            actor: $actor,
            action: 'storage_location.updated',
            subjectType: StorageLocation::class,
            subjectId: $location->id,
            oldValues: $oldValues,
            newValues: $attributes,
            request: $this->request,
        );

        return $location;
    }
}
