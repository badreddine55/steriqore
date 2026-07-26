<?php

namespace App\Http\Requests\Api\V1\Equipment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeviceProgramRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'target_temperature_celsius' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'plateau_minutes' => ['sometimes', 'integer', 'min:1', 'max:600'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
