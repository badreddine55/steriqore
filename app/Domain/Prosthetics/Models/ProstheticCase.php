<?php

namespace App\Domain\Prosthetics\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Traceability\Models\Patient;
use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProstheticCase extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'case_number',
        'patient_id',
        'patient_name',
        'patient_birth_date',
        'patient_code',
        'practitioner_id',
        'practitioner_name',
        'practitioner_specialty',
        'laboratory_id',
        'laboratory_name',
        'work_type',
        'tooth_number',
        'impression_date',
        'impression_type',
        'sent_to_lab_date',
        'expected_return_date',
        'returned_from_lab_date',
        'planned_placement_date',
        'actual_placement_date',
        'status',
        'priority',
        'clinical_notes',
        'internal_notes',
        'deposit_requested',
        'deposit_received',
        'deposit_amount',
        'total_amount',
        'final_payment_completed',
        'administrative_comments',
        'attachments',
        'status_history',
    ];

    protected $casts = [
        'patient_birth_date' => 'date',
        'impression_date' => 'date',
        'sent_to_lab_date' => 'date',
        'expected_return_date' => 'date',
        'returned_from_lab_date' => 'date',
        'planned_placement_date' => 'date',
        'actual_placement_date' => 'date',
        'deposit_requested' => 'boolean',
        'deposit_received' => 'boolean',
        'deposit_amount' => 'float',
        'total_amount' => 'float',
        'final_payment_completed' => 'boolean',
        'attachments' => 'array',
        'status_history' => 'array',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function laboratory(): BelongsTo
    {
        return $this->belongsTo(Laboratory::class, 'laboratory_id');
    }

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'practitioner_id');
    }
}
