<?php

namespace App\Http\Requests\Api\V1\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAlertSettingsRequest extends FormRequest
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
            'near_expiry_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'default_low_stock_threshold' => ['sometimes', 'integer', 'min:0'],
            'control_test_frequency_days' => ['nullable', 'integer', 'min:1'],
            'control_test_overdue_tolerance_days' => ['nullable', 'integer', 'min:0'],
            'failed_cycle_escalation_hours' => ['sometimes', 'integer', 'min:1'],
            'digests_enabled' => ['sometimes', 'boolean'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
