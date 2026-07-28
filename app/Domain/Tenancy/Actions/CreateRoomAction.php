<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Tenancy\Models\Room;
use App\Domain\Tenancy\Models\Site;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;

class CreateRoomAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Site $site, User $actor, array $attributes): Room
    {
        if (Room::where('site_id', $site->id)->where('name', $attributes['name'])->exists()) {
            throw new ApiException('ROOM_NAME_TAKEN', 'A room with this name already exists at this site.', 409);
        }

        $room = Room::create($attributes + ['tenant_id' => $site->tenant_id, 'site_id' => $site->id]);

        $this->recordAuditEvent->execute(
            tenant: $site->tenant,
            actor: $actor,
            action: 'room.created',
            subjectType: Room::class,
            subjectId: $room->id,
            newValues: $attributes,
            request: $this->request,
        );

        return $room;
    }
}
