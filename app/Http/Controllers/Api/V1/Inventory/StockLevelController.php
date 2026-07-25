<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Domain\Inventory\Actions\RebuildStockLevelsAction;
use App\Domain\Inventory\Data\StockLevelData;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StockLevelController extends Controller
{
    /**
     * @return CursorPaginatedDataCollection<int, StockLevelData>
     */
    public function index(Request $request): CursorPaginatedDataCollection
    {
        $this->authorize('viewAny', StockLevel::class);

        $levels = QueryBuilder::for(StockLevel::class)
            ->allowedFilters(AllowedFilter::exact('batch_id'), AllowedFilter::exact('location_id'))
            ->cursorPaginate(perPage: (int) $request->integer('limit', 20));

        return StockLevelData::collect($levels, CursorPaginatedDataCollection::class);
    }

    /**
     * Rebuilds this tenant's stock_levels from stock_movements — proves
     * CLAUDE.md invariant #1 (the projection is always rebuildable by
     * replay) as a real, callable capability, not just a design claim.
     */
    public function rebuild(RebuildStockLevelsAction $action): JsonResponse
    {
        $this->authorize('manage', StockLevel::class);

        $rowCount = $action->execute(Tenant::current());

        return response()->json(['rows_rebuilt' => $rowCount]);
    }
}
