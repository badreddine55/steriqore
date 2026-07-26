<?php

namespace App\Http\Controllers\Web\Sterilization;

use App\Domain\Equipment\Models\Device;
use App\Domain\Sterilization\Data\CycleData;
use App\Domain\Sterilization\Models\Cycle;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Same Cycle model and CycleData DTO the mobile API uses
 * (Api\V1\Sterilization\CycleController) — this is a second, web-only
 * presentation of the identical query, not new domain logic. The extra
 * filters below (operator_id, q, from/to) are additive QueryBuilder
 * configuration only; the frozen API contract (device_id/status only) is
 * untouched.
 */
class CycleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Cycle::class);

        $tenant = Tenant::current();

        $cycles = QueryBuilder::for(Cycle::class)
            ->allowedFilters(
                AllowedFilter::exact('device_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('operator_id'),
                AllowedFilter::callback('q', function ($query, $value): void {
                    $query->whereRaw('cycle_number::text like ?', ['%'.$value.'%']);
                }),
                AllowedFilter::callback('from', function ($query, $value): void {
                    $query->where('started_at', '>=', $value);
                }),
                AllowedFilter::callback('to', function ($query, $value): void {
                    $query->where('started_at', '<=', $value);
                }),
            )
            ->allowedSorts('cycle_number', 'created_at')
            ->defaultSort('-created_at')
            ->cursorPaginate(perPage: 25)
            ->withQueryString();

        return Inertia::render('sterilization/cycles/index', [
            'cycles' => CycleData::collect($cycles, CursorPaginatedDataCollection::class),
            'devices' => Device::orderBy('name')->get(['id', 'name']),
            'operators' => User::whereHas(
                'tenantUsers',
                fn ($query) => $query->where('tenant_id', $tenant->id)->where('status', 'active')
            )->orderBy('name')->get(['id', 'name']),
            // spatie/laravel-query-builder itself reads filter[...] straight
            // off the request; this is purely to hand the current values
            // back to the page for the filter bar's controlled inputs.
            'filters' => $request->input('filter', []),
        ]);
    }
}
