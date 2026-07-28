<?php

namespace App\Http\Controllers\Web\Tenancy;

use App\Domain\Equipment\Models\Device;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Tenancy\Actions\ArchiveSiteAction;
use App\Domain\Tenancy\Actions\CreateSiteAction;
use App\Domain\Tenancy\Actions\UpdateSiteAction;
use App\Domain\Tenancy\Data\RoomData;
use App\Domain\Tenancy\Data\SiteData;
use App\Domain\Tenancy\Data\StorageLocationData;
use App\Domain\Tenancy\Models\Site;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tenancy\ArchiveSiteRequest;
use App\Http\Requests\Api\V1\Tenancy\CreateSiteRequest;
use App\Http\Requests\Api\V1\Tenancy\UpdateSiteRequest;
use App\Support\Data\OptionData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The tenant -> sites -> rooms -> storage_locations hierarchy every other
 * domain hangs off (devices attach to a site, products/batches/movements
 * to a storage location). Admin/owner only (sites.manage) for every
 * mutation; sites.view (granted to every role) still gates read access,
 * unchanged from the existing mobile-facing SitesController.
 */
class SiteController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Site::class);

        $sites = Site::withCount(['rooms', 'storageLocations'])->orderBy('name')->get();

        // devices_count isn't a relation on Site — one grouped query instead
        // of an N+1, matched onto each site afterwards.
        $deviceCounts = Device::selectRaw('site_id, count(*) as aggregate')
            ->whereIn('site_id', $sites->pluck('id'))
            ->groupBy('site_id')
            ->pluck('aggregate', 'site_id');

        $sites->each(fn (Site $site) => $site->setAttribute('devices_count', $deviceCounts->get($site->id, 0)));

        return Inertia::render('tenancy/sites/index', [
            'sites' => SiteData::collect($sites),
            'canManage' => $request->user()->can('create', Site::class),
        ]);
    }

    public function store(CreateSiteRequest $request, CreateSiteAction $action): RedirectResponse
    {
        $this->authorize('create', Site::class);

        $site = $action->execute(Tenant::current(), $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Site \"{$site->name}\" created."]);

        return redirect()->route('sites.show', $site);
    }

    public function show(Site $site, Request $request): Response
    {
        $this->authorize('view', $site);

        $rooms = $site->rooms()->withCount('storageLocations')->orderBy('name')->get();
        $locations = $site->storageLocations()->orderBy('name')->get();
        $devicesCount = Device::where('site_id', $site->id)->count();

        $unitsOnHand = StockLevel::whereIn('location_id', $locations->pluck('id'))
            ->groupBy('location_id')
            ->selectRaw('location_id, sum(quantity) as total')
            ->pluck('total', 'location_id');

        $locations->each(fn ($location) => $location->setAttribute('units_on_hand', (int) $unitsOnHand->get($location->id, 0)));

        $activeBatchesCount = StockLevel::whereIn('location_id', $locations->pluck('id'))
            ->where('quantity', '>', 0)
            ->distinct('batch_id')
            ->count('batch_id');

        $site->setAttribute('rooms_count', $rooms->count());
        $site->setAttribute('storage_locations_count', $locations->count());
        $site->setAttribute('devices_count', $devicesCount);

        return Inertia::render('tenancy/sites/show', [
            'site' => SiteData::fromModel($site),
            'rooms' => RoomData::collect($rooms),
            'storageLocations' => StorageLocationData::collect($locations),
            // site.rooms_count/storage_locations_count double as the archive
            // confirmation's blast radius (UI-23's pattern) — archiving
            // cascades to exactly these; devices aren't cascaded but are
            // shown for visibility.
            'devicesCount' => $devicesCount,
            'activeBatchesCount' => $activeBatchesCount,
            // For the "move this room to another site" picker — every
            // tenant site, not just this one.
            'allSites' => OptionData::collect(
                Site::orderBy('name')->get()->map(fn (Site $s) => new OptionData($s->id, $s->name))
            ),
            'canManage' => $request->user()->can('update', $site),
        ]);
    }

    public function update(Site $site, UpdateSiteRequest $request, UpdateSiteAction $action): RedirectResponse
    {
        $this->authorize('update', $site);

        $action->execute($site, $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Site updated.']);

        return back();
    }

    public function archive(Site $site, ArchiveSiteRequest $request, ArchiveSiteAction $action): RedirectResponse
    {
        $this->authorize('delete', $site);

        $action->execute($site, $request->user(), $request->string('reason')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Site \"{$site->name}\" archived."]);

        return redirect()->route('sites.index');
    }
}
