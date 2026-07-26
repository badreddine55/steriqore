<?php

namespace App\Http\Requests\Api\V1\Equipment;

use App\Domain\Equipment\Enums\MaintenanceKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class RecordMaintenanceRequest extends FormRequest
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
            'kind' => ['required', new Enum(MaintenanceKind::class)],
            'technician' => ['nullable', 'string', 'max:255'],
            'performed_at' => ['required', 'date'],
            'next_due_at' => ['nullable', 'date', 'after:performed_at'],
            'description' => ['nullable', 'string'],
        ];
    }
}
