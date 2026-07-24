<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tenancy\Data\SiteData;
use App\Domain\Tenancy\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\LaravelData\CursorPaginatedDataCollection;

class SitesController extends Controller
{
    /**
     * Sites for the practice the current token is scoped to. Cursor-paginated
     * per the API conventions — never offset pagination.
     *
     * @return CursorPaginatedDataCollection<int, SiteData>
     */
    public function index(Request $request): CursorPaginatedDataCollection
    {
        $tenantId = $request->user()->currentAccessToken()->tenant_id;

        $sites = Site::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->cursorPaginate(perPage: (int) $request->integer('limit', 20));

        return SiteData::collect($sites, CursorPaginatedDataCollection::class);
    }
}
