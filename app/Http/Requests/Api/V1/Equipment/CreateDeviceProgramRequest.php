<?php

namespace App\Http\Requests\Api\V1\Equipment;

use Illuminate\Foundation\Http\FormRequest;

class CreateDeviceProgramRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'target_temperature_celsius' => ['required', 'integer', 'min:1', 'max:200'],
            'plateau_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
