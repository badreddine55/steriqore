<?php

namespace App\Http\Controllers\Web\Traceability;

use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Traceability\Actions\ArchivePatientAction;
use App\Domain\Traceability\Actions\CreatePatientAction;
use App\Domain\Traceability\Data\PatientData;
use App\Domain\Traceability\Data\PatientUsageEntryData;
use App\Domain\Traceability\Models\LabelUsage;
use App\Domain\Traceability\Models\Patient;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Same Patient model and PatientData DTO the mobile API uses
 * (Api\V1\Traceability\PatientController) — this is a second, web-only
 * presentation of the identical query, not new domain logic.
 * CreatePatientAction takes no input at all (a pseudonym's reference is
 * generated, never client-supplied), so "New patient" is a zero-field
 * confirm, not a form.
 */
class PatientController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Patient::class);

        $patients = QueryBuilder::for(Patient::class)
            ->allowedFilters(AllowedFilter::partial('reference'))
            ->allowedSorts('reference', 'created_at')
            ->defaultSort('-created_at')
            ->cursorPaginate(perPage: 25)
            ->withQueryString();

        return Inertia::render('traceability/patients/index', [
            'patients' => PatientData::collect($patients, CursorPaginatedDataCollection::class),
            'canCreate' => $request->user()->can('create', Patient::class),
            'filters' => $request->input('filter', []),
        ]);
    }

    public function store(Request $request, CreatePatientAction $action): RedirectResponse
    {
        $this->authorize('create', Patient::class);

        $patient = $action->execute(Tenant::current(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Patient \"{$patient->reference}\" created."]);

        return redirect()->route('patients.show', $patient);
    }

    public function show(Patient $patient, Request $request): Response
    {
        $this->authorize('view', $patient);

        $usages = LabelUsage::where('patient_id', $patient->id)
            ->with('practitioner')
            ->orderByDesc('used_at')
            ->get();

        return Inertia::render('traceability/patients/show', [
            'patient' => PatientData::fromModel($patient),
            'usages' => PatientUsageEntryData::collect($usages),
            'canManage' => $request->user()->can('delete', $patient),
        ]);
    }

    public function destroy(Patient $patient, Request $request, ArchivePatientAction $action): RedirectResponse
    {
        $this->authorize('delete', $patient);

        $action->execute($patient, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Patient \"{$patient->reference}\" archived."]);

        return redirect()->route('patients.index');
    }
}
