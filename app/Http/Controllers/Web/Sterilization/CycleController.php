<?php

namespace App\Http\Controllers\Web\Sterilization;

use App\Domain\Compliance\Actions\RaiseNonConformityAction;
use App\Domain\Equipment\Data\DeviceProgramData;
use App\Domain\Equipment\Models\Device;
use App\Domain\Equipment\Models\DeviceProgram;
use App\Domain\Sterilization\Actions\CompleteCycleAction;
use App\Domain\Sterilization\Actions\CreateCycleAction;
use App\Domain\Sterilization\Actions\ReleaseCycleAction;
use App\Domain\Sterilization\Actions\StartCycleAction;
use App\Domain\Sterilization\Actions\SubmitCycleForReleaseAction;
use App\Domain\Sterilization\Data\ControlTestData;
use App\Domain\Sterilization\Data\CycleAttachmentData;
use App\Domain\Sterilization\Data\CycleData;
use App\Domain\Sterilization\Data\CycleItemData;
use App\Domain\Sterilization\Data\CycleReleaseData;
use App\Domain\Sterilization\Enums\CycleReleaseDecision;
use App\Domain\Sterilization\Models\ControlTest;
use App\Domain\Sterilization\Models\Cycle;
use App\Domain\Sterilization\Models\CycleItem;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Sterilization\CreateCycleRequest;
use App\Http\Requests\Api\V1\Sterilization\ReleaseCycleRequest;
use App\Models\User;
use App\Support\Data\OptionData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
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

        $devices = Device::orderBy('name')->get()
            ->map(fn (Device $device) => new OptionData($device->id, $device->name));

        $operators = User::whereHas(
            'tenantUsers',
            fn ($query) => $query->where('tenant_id', $tenant->id)->where('status', 'active')
        )->orderBy('name')->get()
            ->map(fn (User $user) => new OptionData($user->id, $user->name));

        // For the "new cycle" dialog's device -> program cascading picker.
        // DeviceProgramData already exists (Api\V1\Equipment) — reused as-is.
        $devicePrograms = DeviceProgram::where('is_active', true)->orderBy('name')->get()
            ->map(fn (DeviceProgram $program) => DeviceProgramData::fromModel($program));

        return Inertia::render('sterilization/cycles/index', [
            'cycles' => CycleData::collect($cycles, CursorPaginatedDataCollection::class),
            'devices' => OptionData::collect($devices),
            'devicePrograms' => DeviceProgramData::collect($devicePrograms),
            'operators' => OptionData::collect($operators),
            'canCreate' => $request->user()->can('create', Cycle::class),
            // spatie/laravel-query-builder itself reads filter[...] straight
            // off the request; this is purely to hand the current values
            // back to the page for the filter bar's controlled inputs.
            'filters' => $request->input('filter', []),
        ]);
    }

    public function store(CreateCycleRequest $request, CreateCycleAction $action): RedirectResponse
    {
        $this->authorize('create', Cycle::class);

        $device = Device::findOrFail($request->string('device_id')->toString());

        $cycle = $action->execute(Tenant::current(), $device, $request->user(), $request->safe()->except('device_id'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Cycle #{$cycle->cycle_number} created."]);

        return redirect()->route('cycles.show', $cycle);
    }

    public function start(Cycle $cycle, Request $request, StartCycleAction $action): RedirectResponse
    {
        $this->authorize('update', $cycle);

        $action->execute($cycle, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cycle started.']);

        return back();
    }

    public function complete(Cycle $cycle, Request $request, CompleteCycleAction $action): RedirectResponse
    {
        $this->authorize('update', $cycle);

        $action->execute($cycle, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cycle completed.']);

        return back();
    }

    public function submitForRelease(Cycle $cycle, Request $request, SubmitCycleForReleaseAction $action): RedirectResponse
    {
        $this->authorize('update', $cycle);

        $action->execute($cycle, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Submitted for release.']);

        return back();
    }

    public function release(Cycle $cycle, ReleaseCycleRequest $request, ReleaseCycleAction $action): RedirectResponse
    {
        $this->authorize('release', $cycle);

        $decision = CycleReleaseDecision::from($request->string('decision')->toString());
        $reason = $request->string('reason')->toString() ?: null;

        $action->execute($cycle, $request->user(), $decision, $reason);

        Inertia::flash('toast', [
            'type' => $decision === CycleReleaseDecision::Compliant ? 'success' : 'error',
            'message' => $decision === CycleReleaseDecision::Compliant
                ? "Cycle #{$cycle->cycle_number} released as compliant."
                : "Cycle #{$cycle->cycle_number} rejected.",
        ]);

        return back();
    }

    /**
     * One page assembling everything the mobile API exposes as separate
     * endpoints (items, control-tests, attachments, release) — a web
     * detail view has no reason to make the browser round-trip four
     * times for one screen the way a mobile client's own navigation does.
     */
    public function show(Cycle $cycle, RaiseNonConformityAction $raiseNonConformity): Response
    {
        $this->authorize('view', $cycle);

        $cycle->loadMissing(['device', 'deviceProgram', 'operator']);

        $release = $cycle->release()->first();
        $releasedBy = $release ? User::find($release->released_by_user_id) : null;

        return Inertia::render('sterilization/cycles/show', [
            'nonConformityBlastRadius' => $raiseNonConformity->previewBlastRadius($cycle),
            'cycle' => CycleData::fromModel($cycle),
            'device' => new OptionData($cycle->device->id, $cycle->device->name),
            'program' => $cycle->deviceProgram ? new OptionData($cycle->deviceProgram->id, $cycle->deviceProgram->name) : null,
            'operator' => new OptionData($cycle->operator->id, $cycle->operator->name),
            'items' => CycleItemData::collect(
                $cycle->items()->orderBy('sequence_in_cycle')->get()
                    ->map(fn (CycleItem $item) => CycleItemData::fromModel($item))
            ),
            'controlTests' => ControlTestData::collect(
                $cycle->controlTests()->orderBy('performed_at')->get()
                    ->map(fn (ControlTest $test) => ControlTestData::fromModel($test))
            ),
            'attachments' => CycleAttachmentData::collect(
                $cycle->getMedia('attachments')->map(fn (Media $media) => CycleAttachmentData::fromModel($media))
            ),
            'release' => $release ? CycleReleaseData::fromModel($release) : null,
            'releasedBy' => $releasedBy ? new OptionData($releasedBy->id, $releasedBy->name) : null,
        ]);
    }
}
