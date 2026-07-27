<?php

namespace App\Http\Controllers\Web\Inventory;

use App\Domain\Inventory\Actions\ResolveAlertAction;
use App\Domain\Inventory\Data\AlertData;
use App\Domain\Inventory\Models\Alert;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * low_stock/near_expiry/expired (DetectAlertsAction) and failed_cycle
 * (RecordControlTestAction) are the four alert types actually raised
 * today — missing_test is deliberately deferred (OQ-7: no per-tenant test
 * frequency rule exists yet to determine "missing" against). The alert's
 * own `message` is already a complete, human-readable sentence, so this
 * screen doesn't need to re-derive subject names the way the batches/
 * control-test logbooks do.
 */
class AlertController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Alert::class);

        $alerts = QueryBuilder::for(Alert::class)
            ->allowedFilters(
                AllowedFilter::exact('type'),
                AllowedFilter::exact('severity'),
                AllowedFilter::exact('state'),
            )
            ->allowedSorts('created_at')
            ->defaultSort('-created_at')
            ->cursorPaginate(perPage: 25)
            ->withQueryString();

        return Inertia::render('inventory/alerts/index', [
            'alerts' => AlertData::collect($alerts, CursorPaginatedDataCollection::class),
            'canManage' => $request->user()->can('manage', Alert::class),
            'filters' => $request->input('filter', []),
        ]);
    }

    public function resolve(Alert $alert, Request $request, ResolveAlertAction $action): RedirectResponse
    {
        $this->authorize('manage', Alert::class);

        $action->execute($alert, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Alert resolved.']);

        return back();
    }
}
