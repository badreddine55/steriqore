<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Domain\Inventory\Actions\DetectAlertsAction;
use App\Domain\Inventory\Actions\ResolveAlertAction;
use App\Domain\Inventory\Data\AlertData;
use App\Domain\Inventory\Models\Alert;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AlertController extends Controller
{
    /**
     * @return CursorPaginatedDataCollection<int, AlertData>
     */
    public function index(Request $request): CursorPaginatedDataCollection
    {
        $this->authorize('viewAny', Alert::class);

        $alerts = QueryBuilder::for(Alert::class)
            ->allowedFilters(AllowedFilter::exact('type'), AllowedFilter::exact('state'), AllowedFilter::exact('severity'))
            ->defaultSort('-created_at')
            ->allowedSorts('created_at')
            ->cursorPaginate(perPage: (int) $request->integer('limit', 20));

        return AlertData::collect($alerts, CursorPaginatedDataCollection::class);
    }

    public function resolve(Alert $alert, Request $request, ResolveAlertAction $action): AlertData
    {
        $this->authorize('manage', Alert::class);

        return AlertData::fromModel($action->execute($alert, $request->user()));
    }

    /**
     * Manual trigger — in production this also runs on a schedule
     * (routes/console.php), but a stock manager can run it on demand too.
     */
    public function detect(Request $request, DetectAlertsAction $action): JsonResponse
    {
        $this->authorize('manage', Alert::class);

        return response()->json($action->execute(Tenant::current()));
    }
}
