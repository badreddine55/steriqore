<?php

namespace App\Http\Requests\Api\V1\Labeling;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDluRuleRequest extends FormRequest
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
            'packaging_type' => ['sometimes', 'string', 'max:255'],
            'storage_condition' => ['sometimes', 'string', 'max:255'],
            'shelf_life_days' => ['sometimes', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
