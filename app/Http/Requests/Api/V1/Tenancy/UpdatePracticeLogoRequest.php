<?php

namespace App\Http\Requests\Api\V1\Tenancy;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePracticeLogoRequest extends FormRequest
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
            'logo' => ['required', 'image', 'max:2048'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
