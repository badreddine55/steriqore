<?php

namespace App\Http\Requests\Api\V1\Tenancy;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePracticeIdentityRequest extends FormRequest
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
            'legal_name' => ['nullable', 'string', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'timezone' => ['sometimes', 'string', 'timezone', 'max:100'],
            'locale' => ['sometimes', 'string', 'max:10'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
