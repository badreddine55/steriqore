<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Tenancy\Models\Room;
use App\Domain\Tenancy\Models\StorageLocation;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;

class CreateStorageLocationAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Room $room, User $actor, array $attributes): StorageLocation
    {
        if (StorageLocation::where('site_id', $room->site_id)->where('name', $attributes['name'])->exists()) {
            throw new ApiException('STORAGE_LOCATION_NAME_TAKEN', 'A storage location with this name already exists at this site.', 409);
        }

        $location = StorageLocation::create($attributes + [
            'tenant_id' => $room->tenant_id,
            'site_id' => $room->site_id,
            'room_id' => $room->id,
        ]);

        $this->recordAuditEvent->execute(
            tenant: $room->tenant,
            actor: $actor,
            action: 'storage_location.created',
            subjectType: StorageLocation::class,
            subjectId: $location->id,
            newValues: $attributes,
            request: $this->request,
        );

        return $location;
    }
}
