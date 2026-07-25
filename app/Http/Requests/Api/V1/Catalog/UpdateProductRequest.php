<?php

namespace App\Http\Requests\Api\V1\Catalog;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
        $tenantId = Tenant::current()?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'reference' => ['sometimes', 'string', 'max:100'],
            'unit' => ['sometimes', 'string', 'max:50'],
            'min_threshold' => ['sometimes', 'integer', 'min:0'],
            'is_sterilizable' => ['sometimes', 'boolean'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category_id' => [
                'sometimes', 'nullable', 'uuid',
                Rule::exists('product_categories', 'id')->where('tenant_id', $tenantId),
            ],
            'default_location_id' => [
                'sometimes', 'nullable', 'uuid',
                Rule::exists('storage_locations', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }
}
