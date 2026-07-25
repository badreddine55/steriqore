<?php

namespace App\Http\Requests\Api\V1\Inventory;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferStockRequest extends FormRequest
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
            'from_location_id' => ['required', 'uuid', Rule::exists('storage_locations', 'id')->where('tenant_id', $tenantId)],
            'to_location_id' => ['required', 'uuid', 'different:from_location_id', Rule::exists('storage_locations', 'id')->where('tenant_id', $tenantId)],
            'qty' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
