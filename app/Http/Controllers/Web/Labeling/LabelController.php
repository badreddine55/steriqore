<?php

namespace App\Http\Controllers\Web\Labeling;

use App\Domain\Equipment\Models\Device;
use App\Domain\Labeling\Actions\PrintLabelAction;
use App\Domain\Labeling\Actions\RenderLabelPdfAction;
use App\Domain\Labeling\Data\LabelData;
use App\Domain\Labeling\Data\LabelListEntryData;
use App\Domain\Labeling\Data\LabelPrintData;
use App\Domain\Labeling\Enums\LabelStatus;
use App\Domain\Labeling\Models\Label;
use App\Domain\Labeling\Support\LabelCodeRenderer;
use App\Domain\Sterilization\Data\CycleData;
use App\Domain\Sterilization\Data\CycleItemData;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Traceability\Data\LabelUsageData;
use App\Domain\Traceability\Models\LabelUsage;
use App\Domain\Traceability\Models\Patient;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Labeling\PrintLabelRequest;
use App\Models\User;
use App\Support\Data\OptionData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\LaravelPdf\PdfBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class LabelController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', Label::class);

        $labels = QueryBuilder::for(Label::class)
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::callback('device_id', function ($query, $value): void {
                    $query->whereHas('cycleItem.cycle', fn ($q) => $q->where('device_id', $value));
                }),
            )
            ->allowedSorts('use_by_date', 'created_at')
            ->defaultSort('-created_at')
            ->with(['cycleItem.cycle.device'])
            ->cursorPaginate(perPage: 25)
            ->withQueryString();

        $devices = Device::orderBy('name')->get()
            ->map(fn (Device $device) => new OptionData($device->id, $device->name));

        return Inertia::render('sterilization/labels/index', [
            'labels' => LabelListEntryData::collect($labels, CursorPaginatedDataCollection::class),
            'devices' => OptionData::collect($devices),
            'filters' => $request->input('filter', []),
        ]);
    }

    public function show(Label $label, Request $request): InertiaResponse
    {
        $this->authorize('view', $label);

        $label->loadMissing(['cycleItem.cycle.device.site', 'cycleItem.batch']);
        $cycleItem = $label->cycleItem;
        $cycle = $cycleItem->cycle;

        $prints = $label->prints()->orderBy('sequence')->get();
        $printedByUsers = User::whereIn('id', $prints->pluck('printed_by_user_id')->unique())->get()
            ->map(fn (User $user) => new OptionData($user->id, $user->name));

        $usage = $label->usage()->first();
        // withTrashed(): the usage keeps its patient's identity even if
        // the pseudonym was later archived (same as LabelUsage::patient()).
        $patient = $usage ? Patient::withTrashed()->find($usage->patient_id) : null;
        $practitioner = $usage ? User::find($usage->practitioner_id) : null;

        $tenant = Tenant::current();
        $patients = Patient::orderBy('reference')->get()
            ->map(fn (Patient $p) => new OptionData($p->id, $p->reference));
        $practitioners = User::whereHas(
            'tenantUsers',
            fn ($query) => $query->where('tenant_id', $tenant->id)->where('status', 'active')
        )->orderBy('name')->get()
            ->map(fn (User $user) => new OptionData($user->id, $user->name));

        return Inertia::render('sterilization/labels/show', [
            'label' => LabelData::fromModel($label),
            'cycle' => CycleData::fromModel($cycle),
            'device' => new OptionData($cycle->device->id, $cycle->device->name),
            'site' => new OptionData($cycle->device->site->id, $cycle->device->site->name),
            'item' => CycleItemData::fromModel($cycleItem),
            'prints' => LabelPrintData::collect($prints),
            'printedByUsers' => OptionData::collect($printedByUsers),
            'usage' => $usage ? LabelUsageData::fromModel($usage) : null,
            'patient' => $patient ? new OptionData($patient->id, $patient->reference) : null,
            'practitioner' => $practitioner ? new OptionData($practitioner->id, $practitioner->name) : null,
            'canViewDossier' => $usage ? $request->user()->can('view', $usage) : false,
            'patients' => OptionData::collect($patients),
            'practitioners' => OptionData::collect($practitioners),
            'canRecordUsage' => $request->user()->can('create', LabelUsage::class),
        ]);
    }

    public function print(Label $label, PrintLabelRequest $request, PrintLabelAction $action): RedirectResponse
    {
        $this->authorize('print', $label);

        $isReprint = $label->status === LabelStatus::Printed;

        $action->execute($label, $request->user(), $request->string('reason')->toString() ?: null);

        Inertia::flash('toast', ['type' => 'success', 'message' => $isReprint ? 'Label reprinted.' : 'Label printed.']);

        return back();
    }

    public function pdf(Label $label, RenderLabelPdfAction $action): PdfBuilder
    {
        $this->authorize('view', $label);

        return $action->execute($label);
    }

    public function qrCode(Label $label): Response
    {
        $this->authorize('view', $label);

        return response(LabelCodeRenderer::qrSvg($label->urn()), 200, ['Content-Type' => 'image/svg+xml']);
    }

    public function dataMatrix(Label $label): Response
    {
        $this->authorize('view', $label);

        return response(LabelCodeRenderer::dataMatrixSvg($label->urn()), 200, ['Content-Type' => 'image/svg+xml']);
    }
}
