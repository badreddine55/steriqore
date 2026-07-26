<?php

namespace App\Http\Requests\Api\V1\Compliance;

use Illuminate\Foundation\Http\FormRequest;

class ResolveNonConformityRequest extends FormRequest
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
            'resolution' => ['required', 'string', 'max:2000'],
        ];
    }
}
