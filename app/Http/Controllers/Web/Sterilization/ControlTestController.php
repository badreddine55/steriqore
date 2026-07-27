<?php

namespace App\Http\Controllers\Web\Sterilization;

use App\Domain\Equipment\Models\Device;
use App\Domain\Sterilization\Data\ControlTestLogEntryData;
use App\Domain\Sterilization\Models\ControlTest;
use App\Domain\Sterilization\Models\Cycle;
use App\Http\Controllers\Controller;
use App\Support\Data\OptionData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The tenant-wide control-test logbook (CLAUDE.md: "a compliant control-test
 * logbook ... with reminder alerts"). This screen covers the logbook and
 * recording a test (CycleControlTestController::store); it deliberately does
 * NOT compute "missing/overdue" — see OQ-7 in docs/open-questions.md, which
 * already flags that determining "missing" needs a per-tenant test
 * frequency rule that doesn't exist yet. Building that here would mean
 * inventing a regulatory rule, which the per-task contract forbids.
 */
class ControlTestController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Cycle::class);

        $tests = QueryBuilder::for(ControlTest::class)
            ->allowedFilters(
                AllowedFilter::exact('type'),
                AllowedFilter::exact('result'),
                AllowedFilter::callback('device_id', function ($query, $value): void {
                    $query->whereHas('cycle', fn ($q) => $q->where('device_id', $value));
                }),
                AllowedFilter::callback('from', function ($query, $value): void {
                    $query->where('performed_at', '>=', $value);
                }),
                AllowedFilter::callback('to', function ($query, $value): void {
                    $query->where('performed_at', '<=', $value);
                }),
            )
            ->allowedSorts('performed_at')
            ->defaultSort('-performed_at')
            // Placed after every spatie-QueryBuilder-only call (allowedFilters,
            // allowedSorts, defaultSort) — with() is forwarded straight to the
            // underlying Eloquent builder, and Larastan's Eloquent-aware return
            // type resolver narrows the chain to a concrete Builder from this
            // point on, which has no allowedFilters()/allowedSorts() of its own.
            ->with(['cycle.device'])
            ->cursorPaginate(perPage: 25)
            ->withQueryString();

        $devices = Device::orderBy('name')->get()
            ->map(fn (Device $device) => new OptionData($device->id, $device->name));

        return Inertia::render('sterilization/control-tests/index', [
            'tests' => ControlTestLogEntryData::collect($tests, CursorPaginatedDataCollection::class),
            'devices' => OptionData::collect($devices),
            'filters' => $request->input('filter', []),
        ]);
    }
}
