<?php

namespace App\Http\Requests\Api\V1\Catalog;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateProductCategoryRequest extends FormRequest
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
            'parent_category_id' => [
                'nullable', 'uuid',
                Rule::exists('product_categories', 'id')->where('tenant_id', Tenant::current()?->id),
            ],
        ];
    }
}
