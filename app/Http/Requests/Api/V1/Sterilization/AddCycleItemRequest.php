<?php

namespace App\Http\Requests\Api\V1\Sterilization;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddCycleItemRequest extends FormRequest
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
            'description' => ['required', 'string', 'max:255'],
            // Optional — not every item is a batch-tracked consumable
            // (e.g. reusable instruments with no Inventory batch at all).
            'batch_id' => ['nullable', 'uuid', Rule::exists('batches', 'id')->where('tenant_id', Tenant::current()?->id)],
        ];
    }
}
