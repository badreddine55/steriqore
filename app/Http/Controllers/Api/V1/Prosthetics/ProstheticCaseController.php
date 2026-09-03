<?php

namespace App\Http\Controllers\Api\V1\Prosthetics;

use App\Domain\Prosthetics\Actions\ChangeProstheticCaseStatusAction;
use App\Domain\Prosthetics\Actions\CreateProstheticCaseAction;
use App\Domain\Prosthetics\Actions\UpdateProstheticCaseAction;
use App\Domain\Prosthetics\Data\ProstheticCaseData;
use App\Domain\Prosthetics\Models\ProstheticCase;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProstheticCaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ProstheticCase::class);

        $tenant = Tenant::current() ?? $request->user()?->tenants()->first();
        $tenantId = $tenant->id;

        $cases = QueryBuilder::for(ProstheticCase::class)
            ->where('tenant_id', $tenantId)
            ->allowedFilters(
                AllowedFilter::partial('patient_name'),
                AllowedFilter::partial('case_number'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('work_type'),
                AllowedFilter::exact('priority'),
                AllowedFilter::exact('practitioner_id'),
                AllowedFilter::exact('laboratory_id')
            )
            ->latest()
            ->get();

        // Custom search query filter if passed
        $q = $request->query('search');
        if ($q) {
            $q = strtolower(trim($q));
            $cases = $cases->filter(function ($c) use ($q) {
                return str_contains(strtolower($c->patient_name), $q)
                    || str_contains(strtolower($c->case_number), $q)
                    || ($c->patient_code && str_contains(strtolower($c->patient_code), $q));
            });
        }

        $result = $cases->map(fn ($c) => ProstheticCaseData::fromModel($c))->values();
        return response()->json($result);
    }

    public function waitingPlacement(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ProstheticCase::class);

        $tenant = Tenant::current() ?? $request->user()?->tenants()->first();
        $tenantId = $tenant->id;

        // Inclusion rule: returned_from_lab_date IS NOT NULL AND actual_placement_date IS NULL AND status != 'Cancelled' (and not 'placed')
        $cases = ProstheticCase::where('tenant_id', $tenantId)
            ->whereNotNull('returned_from_lab_date')
            ->whereNull('actual_placement_date')
            ->whereNotIn('status', ['cancelled', 'placed'])
            ->get();

        $agingFilter = $request->query('aging_filter');
        if ($agingFilter && $agingFilter !== 'all') {
            $cases = $cases->filter(function ($c) use ($agingFilter) {
                $days = (int) Carbon::parse($c->returned_from_lab_date)->diffInDays(now());
                if ($agingFilter === '0-7') return $days <= 7;
                if ($agingFilter === '8-14') return $days >= 8 && $days <= 14;
                if ($agingFilter === '15+') return $days >= 15;
                return true;
            });
        }

        $q = $request->query('patient_query');
        if ($q) {
            $q = strtolower(trim($q));
            $cases = $cases->filter(fn ($c) => str_contains(strtolower($c->patient_name), $q) || str_contains(strtolower($c->case_number), $q));
        }

        $sorted = $cases->sortByDesc(fn ($c) => Carbon::parse($c->returned_from_lab_date)->diffInDays(now()))
            ->map(fn ($c) => ProstheticCaseData::fromModel($c))
            ->values();

        return response()->json($sorted);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenant = Tenant::current() ?? $request->user()?->tenants()->first();
        $case = ProstheticCase::where('tenant_id', $tenant->id)->findOrFail($id);

        $this->authorize('view', $case);

        return response()->json(ProstheticCaseData::fromModel($case));
    }

    public function store(Request $request, CreateProstheticCaseAction $action): JsonResponse
    {
        $this->authorize('create', ProstheticCase::class);

        $validated = $request->validate([
            'patient_id' => ['nullable', 'string'],
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_birth_date' => ['nullable', 'date'],
            'patient_code' => ['nullable', 'string', 'max:255'],
            'practitioner_id' => ['nullable'],
            'practitioner_name' => ['nullable', 'string', 'max:255'],
            'practitioner_specialty' => ['nullable', 'string', 'max:255'],
            'laboratory_id' => ['nullable', 'integer'],
            'laboratory_name' => ['nullable', 'string', 'max:255'],
            'work_type' => ['required', 'string', 'max:255'],
            'tooth_number' => ['nullable', 'string', 'max:50'],
            'impression_date' => ['nullable', 'date'],
            'impression_type' => ['nullable', 'string', 'max:50'],
            'sent_to_lab_date' => ['nullable', 'date'],
            'expected_return_date' => ['nullable', 'date'],
            'planned_placement_date' => ['nullable', 'date'],
            'priority' => ['nullable', 'string', 'max:50'],
            'clinical_notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'deposit_requested' => ['nullable', 'boolean'],
            'deposit_received' => ['nullable', 'boolean'],
            'deposit_amount' => ['nullable', 'numeric'],
            'total_amount' => ['nullable', 'numeric'],
            'final_payment_completed' => ['nullable', 'boolean'],
            'administrative_comments' => ['nullable', 'string'],
        ]);

        $tenant = Tenant::current() ?? $request->user()?->tenants()->first();
        $case = $action->execute($tenant, $request->user(), $validated);

        return response()->json(ProstheticCaseData::fromModel($case), 201);
    }

    public function update(Request $request, int $id, UpdateProstheticCaseAction $action): JsonResponse
    {
        $tenant = Tenant::current() ?? $request->user()?->tenants()->first();
        $case = ProstheticCase::where('tenant_id', $tenant->id)->findOrFail($id);

        $financialKeys = [
            'deposit_requested',
            'deposit_received',
            'deposit_amount',
            'total_amount',
            'final_payment_completed',
            'administrative_comments',
        ];

        $clinicalKeys = [
            'work_type',
            'clinical_notes',
            'internal_notes',
            'planned_placement_date',
            'actual_placement_date',
        ];

        if ($request->hasAny($financialKeys)) {
            $this->authorize('updateFinancials', $case);
        }

        if ($request->hasAny($clinicalKeys)) {
            $this->authorize('update', $case);
        }

        if (!$request->hasAny($financialKeys) && !$request->hasAny($clinicalKeys)) {
            $this->authorize('update', $case);
        }

        $validated = $request->validate([
            'work_type' => ['nullable', 'string'],
            'clinical_notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'deposit_requested' => ['nullable', 'boolean'],
            'deposit_received' => ['nullable', 'boolean'],
            'deposit_amount' => ['nullable', 'numeric'],
            'total_amount' => ['nullable', 'numeric'],
            'final_payment_completed' => ['nullable', 'boolean'],
            'administrative_comments' => ['nullable', 'string'],
            'planned_placement_date' => ['nullable', 'date'],
            'actual_placement_date' => ['nullable', 'date'],
        ]);

        $updated = $action->execute($case, $request->user(), $validated);
        return response()->json(ProstheticCaseData::fromModel($updated));
    }

    public function changeStatus(Request $request, int $id, ChangeProstheticCaseStatusAction $action): JsonResponse
    {
        $tenant = Tenant::current() ?? $request->user()?->tenants()->first();
        $case = ProstheticCase::where('tenant_id', $tenant->id)->findOrFail($id);

        $this->authorize('updateStatus', $case);

        $validated = $request->validate([
            'status' => ['required', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        $updated = $action->execute($case, $request->user(), $validated['status'], $validated['note'] ?? null);
        return response()->json(ProstheticCaseData::fromModel($updated));
    }

    public function history(Request $request, int $id): JsonResponse
    {
        $tenant = Tenant::current() ?? $request->user()?->tenants()->first();
        $case = ProstheticCase::where('tenant_id', $tenant->id)->findOrFail($id);

        $this->authorize('view', $case);

        return response()->json($case->status_history ?? []);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenant = Tenant::current() ?? $request->user()?->tenants()->first();
        $case = ProstheticCase::where('tenant_id', $tenant->id)->findOrFail($id);

        $this->authorize('delete', $case);

        $case->delete();
        return response()->json(['message' => 'Dossier prothétique supprimé avec succès.']);
    }
}
