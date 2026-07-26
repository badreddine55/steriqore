<?php

namespace App\Http\Controllers\Api\V1\Traceability;

use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Traceability\Actions\ArchivePatientAction;
use App\Domain\Traceability\Actions\CreatePatientAction;
use App\Domain\Traceability\Data\PatientData;
use App\Domain\Traceability\Models\Patient;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PatientController extends Controller
{
    /**
     * @return CursorPaginatedDataCollection<int, PatientData>
     */
    public function index(Request $request): CursorPaginatedDataCollection
    {
        $this->authorize('viewAny', Patient::class);

        $patients = QueryBuilder::for(Patient::class)
            ->allowedFilters(AllowedFilter::partial('reference'))
            ->allowedSorts('reference', 'created_at')
            ->defaultSort('-created_at')
            ->cursorPaginate(perPage: (int) $request->integer('limit', 20));

        return PatientData::collect($patients, CursorPaginatedDataCollection::class);
    }

    public function store(Request $request, CreatePatientAction $action): PatientData
    {
        $this->authorize('create', Patient::class);

        return PatientData::fromModel($action->execute(Tenant::current(), $request->user()));
    }

    public function show(Patient $patient): PatientData
    {
        $this->authorize('view', $patient);

        return PatientData::fromModel($patient);
    }

    public function destroy(Patient $patient, Request $request, ArchivePatientAction $action): Response
    {
        $this->authorize('delete', $patient);

        $action->execute($patient, $request->user());

        return response()->noContent();
    }
}
