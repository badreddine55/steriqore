<?php

namespace App\Http\Controllers\Web\Tenancy;

use App\Domain\Tenancy\Actions\ArchiveStorageLocationAction;
use App\Domain\Tenancy\Actions\CreateStorageLocationAction;
use App\Domain\Tenancy\Actions\UpdateStorageLocationAction;
use App\Domain\Tenancy\Models\Room;
use App\Domain\Tenancy\Models\StorageLocation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tenancy\ArchiveStorageLocationRequest;
use App\Http\Requests\Api\V1\Tenancy\CreateStorageLocationRequest;
use App\Http\Requests\Api\V1\Tenancy\UpdateStorageLocationRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Storage locations are only ever reached from their site's own detail
 * page (tenancy/sites/show) — no index/show route of their own.
 */
class StorageLocationController extends Controller
{
    public function store(Room $room, CreateStorageLocationRequest $request, CreateStorageLocationAction $action): RedirectResponse
    {
        $this->authorize('create', StorageLocation::class);

        $location = $action->execute($room, $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Storage location \"{$location->name}\" added."]);

        return back();
    }

    public function update(StorageLocation $storageLocation, UpdateStorageLocationRequest $request, UpdateStorageLocationAction $action): RedirectResponse
    {
        $this->authorize('update', $storageLocation);

        $action->execute($storageLocation, $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Storage location updated.']);

        return back();
    }

    public function archive(StorageLocation $storageLocation, ArchiveStorageLocationRequest $request, ArchiveStorageLocationAction $action): RedirectResponse
    {
        $this->authorize('delete', $storageLocation);

        $action->execute($storageLocation, $request->user(), $request->string('reason')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Storage location \"{$storageLocation->name}\" archived."]);

        return back();
    }
}
