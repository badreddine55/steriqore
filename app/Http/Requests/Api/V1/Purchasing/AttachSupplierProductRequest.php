<?php

namespace App\Http\Requests\Api\V1\Purchasing;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachSupplierProductRequest extends FormRequest
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
            'product_id' => [
                'required', 'uuid',
                Rule::exists('products', 'id')->where('tenant_id', Tenant::current()?->id),
            ],
            'supplier_reference' => ['nullable', 'string', 'max:255'],
            'pack_size' => ['sometimes', 'integer', 'min:1'],
            'price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
