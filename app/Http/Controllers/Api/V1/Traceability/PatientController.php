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
            ->allowedFilters(
                AllowedFilter::partial('reference'),
                AllowedFilter::partial('first_name'),
                AllowedFilter::partial('last_name'),
                AllowedFilter::partial('email'),
                AllowedFilter::partial('phone')
            )
            ->allowedSorts('reference', 'first_name', 'last_name', 'created_at')
            ->defaultSort('-created_at')
            ->cursorPaginate(perPage: (int) $request->integer('limit', 20));

        return PatientData::collect($patients, CursorPaginatedDataCollection::class);
    }

    public function store(Request $request, CreatePatientAction $action): PatientData
    {
        $this->authorize('create', Patient::class);

        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'nir' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'allergies' => ['nullable', 'array'],
        ]);

        $tenant = Tenant::current() ?? $request->user()?->tenants()->first();

        return PatientData::fromModel($action->execute($tenant, $request->user(), $validated));
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
