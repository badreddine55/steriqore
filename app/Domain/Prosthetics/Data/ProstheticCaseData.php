<?php

namespace App\Domain\Prosthetics\Data;

use App\Domain\Prosthetics\Models\ProstheticCase;
use Carbon\Carbon;
use Spatie\LaravelData\Data;

class ProstheticCaseData extends Data
{
    public function __construct(
        public int $id,
        public string $case_number,
        public ?string $patient_id,
        public string $patient_name,
        public ?string $patient_birth_date,
        public ?string $patient_code,
        public mixed $practitioner_id,
        public ?string $practitioner_name,
        public ?string $practitioner_specialty,
        public ?int $laboratory_id,
        public ?string $laboratory_name,
        public string $work_type,
        public ?string $tooth_number,
        public string $impression_date,
        public string $impression_type,
        public ?string $sent_to_lab_date,
        public ?string $expected_return_date,
        public ?string $returned_from_lab_date,
        public ?string $planned_placement_date,
        public ?string $actual_placement_date,
        public string $status,
        public string $priority,
        public ?string $clinical_notes,
        public ?string $internal_notes,
        public bool $deposit_requested,
        public bool $deposit_received,
        public float $deposit_amount,
        public float $total_amount,
        public bool $final_payment_completed,
        public ?string $administrative_comments,
        public int $days_elapsed_since_return,
        public ?array $attachments,
        public ?array $status_history,
        public ?string $created_at,
        public ?string $updated_at,
    ) {}

    public static function fromModel(ProstheticCase $case): self
    {
        $daysElapsed = 0;
        if ($case->returned_from_lab_date && !$case->actual_placement_date) {
            $daysElapsed = (int) Carbon::parse($case->returned_from_lab_date)->diffInDays(now());
        }

        return new self(
            id: $case->id,
            case_number: $case->case_number,
            patient_id: $case->patient_id,
            patient_name: $case->patient_name,
            patient_birth_date: $case->patient_birth_date?->toDateString(),
            patient_code: $case->patient_code,
            practitioner_id: $case->practitioner_id,
            practitioner_name: $case->practitioner_name,
            practitioner_specialty: $case->practitioner_specialty,
            laboratory_id: $case->laboratory_id,
            laboratory_name: $case->laboratory_name,
            work_type: $case->work_type,
            tooth_number: $case->tooth_number,
            impression_date: $case->impression_date ? Carbon::parse($case->impression_date)->toDateString() : now()->toDateString(),
            impression_type: $case->impression_type ?? 'Numérique',
            sent_to_lab_date: $case->sent_to_lab_date?->toDateString(),
            expected_return_date: $case->expected_return_date?->toDateString(),
            returned_from_lab_date: $case->returned_from_lab_date?->toDateString(),
            planned_placement_date: $case->planned_placement_date?->toDateString(),
            actual_placement_date: $case->actual_placement_date?->toDateString(),
            status: $case->status,
            priority: $case->priority ?? 'normal',
            clinical_notes: $case->clinical_notes,
            internal_notes: $case->internal_notes,
            deposit_requested: (bool) $case->deposit_requested,
            deposit_received: (bool) $case->deposit_received,
            deposit_amount: (float) $case->deposit_amount,
            total_amount: (float) $case->total_amount,
            final_payment_completed: (bool) $case->final_payment_completed,
            administrative_comments: $case->administrative_comments,
            days_elapsed_since_return: $daysElapsed,
            attachments: $case->attachments ?? [],
            status_history: $case->status_history ?? [],
            created_at: $case->created_at?->toISOString(),
            updated_at: $case->updated_at?->toISOString(),
        );
    }
}
