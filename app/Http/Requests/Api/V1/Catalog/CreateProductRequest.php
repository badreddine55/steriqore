<?php

namespace App\Http\Requests\Api\V1\Catalog;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateProductRequest extends FormRequest
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
        // Rule::exists runs a raw DB query, not an Eloquent one — it does
        // NOT see BelongsToTenant's global scope. Scoping by tenant_id here
        // explicitly is what stops a category/location id from a different
        // practice validating as "exists".
        $tenantId = Tenant::current()?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'reference' => ['required', 'string', 'max:100'],
            'unit' => ['sometimes', 'string', 'max:50'],
            'min_threshold' => ['sometimes', 'integer', 'min:0'],
            'is_sterilizable' => ['sometimes', 'boolean'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'category_id' => [
                'nullable', 'uuid',
                Rule::exists('product_categories', 'id')->where('tenant_id', $tenantId),
            ],
            'default_location_id' => [
                'nullable', 'uuid',
                Rule::exists('storage_locations', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }
}
