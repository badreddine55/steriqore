<?php

namespace App\Http\Requests\Api\V1\Tenancy;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStorageLocationRequest extends FormRequest
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
            'kind' => ['nullable', 'string', 'max:100'],
            'room_id' => ['sometimes', 'uuid', Rule::exists('rooms', 'id')->where('tenant_id', Tenant::current()?->id)],
        ];
    }
}
