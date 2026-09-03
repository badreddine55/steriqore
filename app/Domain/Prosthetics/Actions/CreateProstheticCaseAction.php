<?php

namespace App\Domain\Prosthetics\Actions;

use App\Domain\Prosthetics\Models\ProstheticCase;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreateProstheticCaseAction
{
    public function execute(Tenant $tenant, User $actor, array $data): ProstheticCase
    {
        return DB::transaction(function () use ($tenant, $actor, $data) {
            $year = now()->year;
            $count = ProstheticCase::where('tenant_id', $tenant->id)
                ->whereYear('created_at', $year)
                ->count();

            $nextSequence = $count + 1;
            $caseNumber = sprintf('DS-%d-%03d', $year, $nextSequence);

            $now = Carbon::now();
            $initialHistory = [
                [
                    'id' => 1,
                    'case_id' => 0,
                    'from_status' => null,
                    'to_status' => 'impression_completed',
                    'changed_by_user_name' => $actor->name,
                    'changed_by_user_role' => $actor->role ?? 'practitioner',
                    'timestamp' => $now->toISOString(),
                    'note' => 'Dossier créé',
                ],
            ];

            $case = ProstheticCase::create([
                'tenant_id' => $tenant->id,
                'case_number' => $caseNumber,
                'patient_id' => $data['patient_id'] ?? null,
                'patient_name' => $data['patient_name'] ?? 'Nouveau Patient',
                'patient_birth_date' => isset($data['patient_birth_date']) ? Carbon::parse($data['patient_birth_date']) : null,
                'patient_code' => $data['patient_code'] ?? null,
                'practitioner_id' => $data['practitioner_id'] ?? $actor->id,
                'practitioner_name' => $data['practitioner_name'] ?? $actor->name,
                'practitioner_specialty' => $data['practitioner_specialty'] ?? null,
                'laboratory_id' => $data['laboratory_id'] ?? null,
                'laboratory_name' => $data['laboratory_name'] ?? null,
                'work_type' => $data['work_type'] ?? 'Couronne Zircone',
                'tooth_number' => $data['tooth_number'] ?? null,
                'impression_date' => isset($data['impression_date']) ? Carbon::parse($data['impression_date']) : $now,
                'impression_type' => $data['impression_type'] ?? 'Numérique',
                'sent_to_lab_date' => isset($data['sent_to_lab_date']) ? Carbon::parse($data['sent_to_lab_date']) : null,
                'expected_return_date' => isset($data['expected_return_date']) ? Carbon::parse($data['expected_return_date']) : $now->copy()->addDays(7),
                'returned_from_lab_date' => isset($data['returned_from_lab_date']) ? Carbon::parse($data['returned_from_lab_date']) : null,
                'planned_placement_date' => isset($data['planned_placement_date']) ? Carbon::parse($data['planned_placement_date']) : null,
                'actual_placement_date' => isset($data['actual_placement_date']) ? Carbon::parse($data['actual_placement_date']) : null,
                'status' => $data['status'] ?? 'impression_completed',
                'priority' => $data['priority'] ?? 'normal',
                'clinical_notes' => $data['clinical_notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'deposit_requested' => $data['deposit_requested'] ?? false,
                'deposit_received' => $data['deposit_received'] ?? false,
                'deposit_amount' => $data['deposit_amount'] ?? 0.00,
                'total_amount' => $data['total_amount'] ?? 0.00,
                'final_payment_completed' => $data['final_payment_completed'] ?? false,
                'administrative_comments' => $data['administrative_comments'] ?? null,
                'attachments' => $data['attachments'] ?? [],
                'status_history' => $initialHistory,
            ]);

            return $case;
        });
    }
}
