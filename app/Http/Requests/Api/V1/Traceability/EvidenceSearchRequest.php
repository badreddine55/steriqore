<?php

namespace App\Http\Requests\Api\V1\Traceability;

use Illuminate\Foundation\Http\FormRequest;

class EvidenceSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'patient_reference' => ['nullable', 'string', 'max:255'],
            'cycle_number' => ['nullable', 'integer', 'min:1'],
            'batch_number' => ['nullable', 'string', 'max:255'],
            'device_id' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
