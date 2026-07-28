<?php

namespace App\Http\Controllers\Web\Tenancy;

use App\Domain\Tenancy\Actions\ArchiveRoomAction;
use App\Domain\Tenancy\Actions\CreateRoomAction;
use App\Domain\Tenancy\Actions\UpdateRoomAction;
use App\Domain\Tenancy\Models\Room;
use App\Domain\Tenancy\Models\Site;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tenancy\ArchiveRoomRequest;
use App\Http\Requests\Api\V1\Tenancy\CreateRoomRequest;
use App\Http\Requests\Api\V1\Tenancy\UpdateRoomRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Rooms are only ever reached from their site's own detail page
 * (tenancy/sites/show) — no index/show route of their own.
 */
class RoomController extends Controller
{
    public function store(Site $site, CreateRoomRequest $request, CreateRoomAction $action): RedirectResponse
    {
        $this->authorize('create', Room::class);

        $room = $action->execute($site, $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Room \"{$room->name}\" added."]);

        return back();
    }

    public function update(Room $room, UpdateRoomRequest $request, UpdateRoomAction $action): RedirectResponse
    {
        $this->authorize('update', $room);

        $action->execute($room, $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Room updated.']);

        return back();
    }

    public function archive(Room $room, ArchiveRoomRequest $request, ArchiveRoomAction $action): RedirectResponse
    {
        $this->authorize('delete', $room);

        $action->execute($room, $request->user(), $request->string('reason')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Room \"{$room->name}\" archived."]);

        return back();
    }
}
