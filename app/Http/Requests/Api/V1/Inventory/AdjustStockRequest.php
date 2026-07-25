<?php

namespace App\Http\Requests\Api\V1\Inventory;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustStockRequest extends FormRequest
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
            'batch_id' => ['required', 'uuid', Rule::exists('batches', 'id')->where('tenant_id', $tenantId)],
            'location_id' => ['required', 'uuid', Rule::exists('storage_locations', 'id')->where('tenant_id', $tenantId)],
            'qty' => ['required', 'integer', 'not_in:0'],
            // Mandatory, unlike issue/transfer: an adjustment is a
            // correction with no other evidence behind it.
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
