<?php

namespace App\Http\Requests\Api\V1\Labeling;

use Illuminate\Foundation\Http\FormRequest;

class CreateDluRuleRequest extends FormRequest
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
            'packaging_type' => ['required', 'string', 'max:255'],
            'storage_condition' => ['required', 'string', 'max:255'],
            'shelf_life_days' => ['required', 'integer', 'min:1'],
        ];
    }
}
