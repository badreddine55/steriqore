<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Tenancy\Models\Room;
use App\Domain\Tenancy\Models\Site;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UpdateRoomAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  May include 'site_id' to move the
     *                                            room to a different site — refused
     *                                            if any of its storage locations has
     *                                            ever recorded a stock movement, since
     *                                            that would misattribute historical
     *                                            evidence to a site it didn't happen at.
     */
    public function execute(Room $room, User $actor, array $attributes): Room
    {
        $targetSiteId = $attributes['site_id'] ?? $room->site_id;

        if (
            isset($attributes['name'])
            && Room::where('site_id', $targetSiteId)->where('name', $attributes['name'])->where('id', '!=', $room->id)->exists()
        ) {
            throw new ApiException('ROOM_NAME_TAKEN', 'A room with this name already exists at this site.', 409);
        }

        if ($targetSiteId !== $room->site_id) {
            $locationIds = $room->storageLocations()->pluck('id');

            if ($locationIds->isNotEmpty() && StockMovement::whereIn('location_id', $locationIds)->exists()) {
                throw new ApiException('ROOM_HAS_STOCK_HISTORY', 'This room has recorded stock movements and cannot be moved to a different site.', 409);
            }
        }

        return DB::transaction(function () use ($room, $actor, $attributes, $targetSiteId) {
            $originalSiteId = $room->site_id;
            $oldValues = $room->only(array_keys($attributes));
            $room->update($attributes);

            if ($targetSiteId !== $originalSiteId) {
                $room->storageLocations()->update(['site_id' => $targetSiteId]);
            }

            $this->recordAuditEvent->execute(
                tenant: $room->tenant,
                actor: $actor,
                action: 'room.updated',
                subjectType: Room::class,
                subjectId: $room->id,
                oldValues: $oldValues,
                newValues: $attributes,
                request: $this->request,
            );

            return $room;
        });
    }
}
